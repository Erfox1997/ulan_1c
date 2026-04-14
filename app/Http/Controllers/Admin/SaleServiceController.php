<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleServiceRequest;
use App\Http\Requests\UpdateSaleServiceRequest;
use App\Models\Good;
use App\Services\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaleServiceController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function index(): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $services = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', true)
            ->orderBy('name')
            ->get();

        return view('admin.sale-services.index', [
            'services' => $services,
        ]);
    }

    public function create(): View
    {
        return view('admin.sale-services.create', [
            'service' => new Good([
                'unit' => 'усл.',
                'is_service' => true,
            ]),
        ]);
    }

    public function store(StoreSaleServiceRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $salePrice = $this->openingBalanceService->parseOptionalMoney($request->input('sale_price'));

        Good::query()->create([
            'branch_id' => $branchId,
            'article_code' => $this->generateUniqueServiceArticleCode($branchId),
            'name' => trim((string) $request->validated('name')),
            'unit' => trim((string) ($request->validated('unit') ?: 'усл.')) ?: 'усл.',
            'sale_price' => $salePrice,
            'is_service' => true,
        ]);

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга добавлена.');
    }

    public function edit(int $service): View
    {
        $good = $this->serviceGoodOrAbort($service);

        return view('admin.sale-services.edit', [
            'service' => $good,
        ]);
    }

    public function update(UpdateSaleServiceRequest $request, int $service): RedirectResponse
    {
        $good = $this->serviceGoodOrAbort($service);
        $salePrice = $this->openingBalanceService->parseOptionalMoney($request->input('sale_price'));

        $good->update([
            'name' => trim((string) $request->validated('name')),
            'unit' => trim((string) ($request->validated('unit') ?: 'усл.')) ?: 'усл.',
            'sale_price' => $salePrice,
        ]);

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга сохранена.');
    }

    public function destroy(int $service): RedirectResponse
    {
        $good = $this->serviceGoodOrAbort($service);

        if ($good->retailSaleLines()->exists()) {
            return redirect()
                ->route('admin.sale-services.index')
                ->withErrors(['delete' => 'Нельзя удалить услугу: есть строки в розничных продажах.']);
        }

        $good->delete();

        return redirect()
            ->route('admin.sale-services.index')
            ->with('status', 'Услуга удалена.');
    }

    private function serviceGoodOrAbort(int $id): Good
    {
        $branchId = (int) auth()->user()->branch_id;

        return Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', true)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * Внутренний код для связи с розницей; пользователю не показывается.
     */
    private function generateUniqueServiceArticleCode(int $branchId): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = 'SVC-'.Str::ulid();
            if (! Good::query()->where('branch_id', $branchId)->where('article_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Не удалось сгенерировать код услуги.');
    }
}
