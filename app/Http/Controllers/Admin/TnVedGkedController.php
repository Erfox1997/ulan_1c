<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyUniversalTnVedRequest;
use App\Http\Requests\StoreTnVedKeywordRuleRequest;
use App\Http\Requests\UpdateServiceTnVedRequest;
use App\Models\Branch;
use App\Models\Good;
use App\Models\TnvedKeywordRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TnVedGkedController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) $request->user()->branch_id;
        $branch = Branch::query()->findOrFail($branchId);

        $rules = TnvedKeywordRule::query()
            ->where('branch_id', $branchId)
            ->orderBy('keyword')
            ->get();

        $goodsWithoutTnved = (int) $this->goodsWithoutTnvedQuery($branchId)->count();

        return view('admin.accounting.tn-ved-gked-codes.index', [
            'branch' => $branch,
            'rules' => $rules,
            'goodsWithoutTnved' => $goodsWithoutTnved,
            'activeTab' => $request->query('tab') === 'services' ? 'services' : 'goods',
        ]);
    }

    public function storeRule(StoreTnVedKeywordRuleRequest $request): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $p = $request->validatedPayload();

        TnvedKeywordRule::query()->create([
            'branch_id' => $branchId,
            'keyword' => $p['keyword'],
            'tnved_code' => $p['tnved_code'],
        ]);

        return redirect()
            ->route('admin.accounting.tn-ved-gked-codes', ['tab' => 'goods'])
            ->with('status', 'Правило добавлено в справочник.');
    }

    public function destroyRule(Request $request, TnvedKeywordRule $tnvedKeywordRule): RedirectResponse
    {
        $this->ensureRuleBranch($request, $tnvedKeywordRule);
        $tnvedKeywordRule->delete();

        return redirect()
            ->route('admin.accounting.tn-ved-gked-codes', ['tab' => 'goods'])
            ->with('status', 'Правило удалено.');
    }

    public function previewRule(Request $request, TnvedKeywordRule $tnvedKeywordRule): JsonResponse
    {
        $this->ensureRuleBranch($request, $tnvedKeywordRule);
        $branchId = (int) $request->user()->branch_id;
        $count = $this->matchedGoodsForKeyword($branchId, $tnvedKeywordRule->keyword)->count();

        return response()->json([
            'count' => $count,
            'keyword' => $tnvedKeywordRule->keyword,
            'tnved_code' => $tnvedKeywordRule->tnved_code,
        ]);
    }

    public function applyRule(Request $request, TnvedKeywordRule $tnvedKeywordRule): RedirectResponse
    {
        $this->ensureRuleBranch($request, $tnvedKeywordRule);
        $branchId = (int) $request->user()->branch_id;
        $code = $tnvedKeywordRule->tnved_code;
        $keyword = $tnvedKeywordRule->keyword;

        $affected = 0;
        DB::transaction(function () use ($branchId, $keyword, $code, &$affected) {
            $affected = $this->matchedGoodsForKeyword($branchId, $keyword)->update(['tnved_code' => $code]);
        });

        $word = $this->russianGoodsWord($affected);
        $msg = $affected > 0
            ? "ТНВЭД «{$code}» проставлен для {$affected} {$word}."
            : 'Под выбранное ключевое слово не подошло ни одного товара.';

        return redirect()
            ->route('admin.accounting.tn-ved-gked-codes', ['tab' => 'goods'])
            ->with('status', $msg);
    }

    public function previewUniversal(Request $request): JsonResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $count = $this->goodsWithoutTnvedQuery($branchId)->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    public function applyUniversal(ApplyUniversalTnVedRequest $request): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $code = $request->validated('universal_tnved_code');
        $affected = 0;
        DB::transaction(function () use ($branchId, $code, &$affected) {
            Branch::query()->whereKey($branchId)->update(['universal_tnved_code' => $code]);
            $affected = $this->goodsWithoutTnvedQuery($branchId)->update(['tnved_code' => $code]);
        });
        $word = $this->russianGoodsWord($affected);
        $msg = $affected > 0
            ? "Универсальный ТНВЭД «{$code}» проставлен для {$affected} {$word}."
            : 'Нет товаров без ТНВЭД. Код сохранён в настройке.';

        return redirect()
            ->route('admin.accounting.tn-ved-gked-codes', ['tab' => 'goods'])
            ->with('status', $msg);
    }

    public function updateServiceTnved(UpdateServiceTnVedRequest $request): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $data = $request->validated();

        DB::transaction(function () use ($branchId, $data) {
            Branch::query()->whereKey($branchId)->update([
                'service_tnved_code' => $data['service_tnved_code'],
                'service_esf_export_name' => $data['service_esf_export_name'] ?? null,
            ]);
            Good::query()
                ->where('branch_id', $branchId)
                ->where('is_service', true)
                ->update(['tnved_code' => $data['service_tnved_code']]);
        });

        $n = (int) Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', true)
            ->count();

        $suffix = " (обновлено позиций услуг: {$n})";

        return redirect()
            ->route('admin.accounting.tn-ved-gked-codes', ['tab' => 'services'])
            ->with('status', "Единый код ГКЭД для услуг сохранён.{$suffix}");
    }

    private function ensureRuleBranch(Request $request, TnvedKeywordRule $rule): void
    {
        abort_unless(
            (int) $rule->branch_id === (int) $request->user()->branch_id,
            404
        );
    }

    /**
     * @return Builder<Good>
     */
    private function goodsWithoutTnvedQuery(int $branchId): Builder
    {
        return Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->where(function ($q) {
                $q->whereNull('tnved_code')->orWhere('tnved_code', '');
            });
    }

    /**
     * @return Builder<Good>
     */
    private function matchedGoodsForKeyword(int $branchId, string $keyword)
    {
        $like = '%'.$this->escapeLikePattern($keyword).'%';

        return Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('article_code', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('oem', 'like', $like)
                    ->orWhere('factory_number', 'like', $like);
            });
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function russianGoodsWord(int $n): string
    {
        $n100 = $n % 100;
        $n10 = $n % 10;
        if ($n100 >= 11 && $n100 <= 14) {
            return 'товаров';
        }
        if ($n10 === 1) {
            return 'товар';
        }
        if ($n10 >= 2 && $n10 <= 4) {
            return 'товара';
        }

        return 'товаров';
    }
}
