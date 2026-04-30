<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSaleGoodsRequest;
use App\Http\Requests\StoreSaleGoodsRequest;
use App\Http\Requests\UpdateSaleGoodsRequest;
use App\Models\Good;
use App\Models\OpeningStockBalance;
use App\Services\BranchReportService;
use App\Services\OpeningBalanceService;
use App\Services\SaleGoodsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleGoodsController extends Controller
{
    private const PER_PAGE = 80;

    /** Query-string value for goods without category (folder). */
    private const NO_CATEGORY_QUERY = '__no_category__';

    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function index(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $q = trim((string) $request->query('q', ''));
        $rawCategory = $request->query('category');

        $placeholderId = 9_999_999_001;
        $editUrlTemplate = str_replace(
            (string) $placeholderId,
            '__ID__',
            route('admin.sale-goods.edit', ['good' => $placeholderId], true)
        );

        $goodsSearchConfig = [
            'searchUrl' => route('admin.goods.search', ['exclude_services' => true]),
            'editUrlTemplate' => $editUrlTemplate,
            'initialQuery' => $q,
            'openModalEvent' => true,
        ];

        if ($rawCategory === null || ! is_string($rawCategory) || trim($rawCategory) === '') {
            $categories = $this->saleGoodsCategoryFolders($branchId);

            return view('admin.sale-goods.index', [
                'viewMode' => 'categories',
                'categories' => $categories,
                'goods' => null,
                'searchQuery' => '',
                'selectedCategoryKey' => null,
                'categoryTitle' => null,
                'goodsSearchConfig' => $goodsSearchConfig,
                'goodModalConfig' => null,
            ]);
        }

        $categoryQueryParam = trim($rawCategory);
        $categoryFilter = $categoryQueryParam === self::NO_CATEGORY_QUERY
            ? ''
            : $categoryQueryParam;

        $query = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false);

        if ($categoryFilter === '') {
            $query->where(function ($w) {
                $w->whereNull('category')
                    ->orWhere('category', '')
                    ->orWhereRaw("TRIM(COALESCE(category, '')) = ''");
            });
        } else {
            $query->where('category', $categoryFilter);
        }

        if ($q !== '') {
            $like = '%'.$this->escapeLikePattern($q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)
                    ->orWhere('article_code', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('oem', 'like', $like)
                    ->orWhere('factory_number', 'like', $like);
            });
        }

        $goods = $query
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        [$stockByGood, $purchaseByGood] = $this->stockQuantityAndWeightedPurchaseCostByGood($branchId);

        $goods->getCollection()->transform(function (Good $g) use ($stockByGood, $purchaseByGood): Good {
            $gid = (int) $g->id;
            $g->setAttribute(
                'aggregated_stock',
                $stockByGood->has($gid) ? (float) $stockByGood[$gid] : 0.0
            );
            $g->setAttribute(
                'aggregated_purchase_price',
                $purchaseByGood->has($gid) ? $purchaseByGood[$gid] : null
            );
            $g->setAttribute('display_name', $this->displayGoodName($g));

            return $g;
        });

        $categoryTitle = $categoryFilter === '' ? 'Без категории' : $categoryFilter;

        $modalPlaceholderId = 9_999_999_002;
        $goodModalConfig = [
            'dataUrlTemplate' => str_replace(
                (string) $modalPlaceholderId,
                '__ID__',
                route('admin.sale-goods.modal-data', ['good' => $modalPlaceholderId], true)
            ),
            'updateUrlTemplate' => str_replace(
                (string) $placeholderId,
                '__ID__',
                route('admin.sale-goods.update', ['good' => $placeholderId], true)
            ),
            'csrf' => csrf_token(),
        ];

        return view('admin.sale-goods.index', [
            'viewMode' => 'goods',
            'categories' => collect(),
            'goods' => $goods,
            'searchQuery' => $q,
            'selectedCategoryKey' => $categoryQueryParam,
            'categoryTitle' => $categoryTitle,
            'goodsSearchConfig' => $goodsSearchConfig,
            'goodModalConfig' => $goodModalConfig,
        ]);
    }

    /**
     * @return Collection<int, array{label: string, count: int, category_key: string}>
     */
    private function saleGoodsCategoryFolders(int $branchId): Collection
    {
        $groups = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->get(['category'])
            ->groupBy(function (Good $g): string {
                $c = $g->category;
                if ($c === null) {
                    return '';
                }
                $t = trim((string) $c);

                return $t === '' ? '' : $t;
            })
            ->map(fn (Collection $group, string $key): array => [
                'label' => $key === '' ? 'Без категории' : $key,
                'count' => $group->count(),
                'category_key' => $key === '' ? self::NO_CATEGORY_QUERY : $key,
            ]);

        return $groups->values()->sort(function (array $a, array $b): int {
            $aUncat = $a['category_key'] === self::NO_CATEGORY_QUERY;
            $bUncat = $b['category_key'] === self::NO_CATEGORY_QUERY;
            if ($aUncat !== $bUncat) {
                return $aUncat ? 1 : -1;
            }

            return strcasecmp($a['label'], $b['label']);
        })->values();
    }

    /**
     * @return array{0: Collection<int, float>, 1: Collection<int, float|null>}
     */
    private function stockQuantityAndWeightedPurchaseCostByGood(int $branchId): array
    {
        $stockByGood = OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->selectRaw('good_id, SUM(quantity) as stock_sum')
            ->groupBy('good_id')
            ->pluck('stock_sum', 'good_id')
            ->map(fn ($v) => (float) $v);

        $costRows = OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('unit_cost')
            ->get(['good_id', 'quantity', 'unit_cost']);

        $acc = [];
        foreach ($costRows as $row) {
            $gid = (int) $row->good_id;
            $qty = (float) $row->quantity;
            if ($qty <= 0) {
                continue;
            }
            $uc = (float) $row->unit_cost;
            if (! isset($acc[$gid])) {
                $acc[$gid] = ['num' => 0.0, 'den' => 0.0];
            }
            $acc[$gid]['num'] += $qty * $uc;
            $acc[$gid]['den'] += $qty;
        }

        $purchaseByGood = collect();
        foreach ($acc as $gid => $x) {
            $purchaseByGood[$gid] = $x['den'] > 0 ? round($x['num'] / $x['den'], 2) : null;
        }

        return [$stockByGood, $purchaseByGood];
    }

    public function modalData(int $good, BranchReportService $reports): JsonResponse
    {
        $model = $this->goodsGoodOrAbort($good);
        $branchId = (int) auth()->user()->branch_id;
        [$stockByGood, $purchaseByGood] = $this->stockQuantityAndWeightedPurchaseCostByGood($branchId);
        $gid = (int) $model->id;

        $fmtOpt = static function ($v): string {
            if ($v === null || $v === '') {
                return '';
            }

            return number_format((float) $v, 2, ',', ' ');
        };
        $fmtStock = static function ($v): string {
            if ($v === null || $v === '') {
                return '';
            }

            return number_format((float) $v, 4, ',', ' ');
        };

        $goodPayload = [
            'id' => $gid,
            'article_code' => (string) $model->article_code,
            'name' => (string) $model->name,
            'display_name' => $this->displayGoodName($model),
            'barcode' => $model->barcode ?? '',
            'category' => $model->category ?? '',
            'unit' => (string) ($model->unit ?? 'шт.'),
            'sale_price' => $fmtOpt($model->sale_price),
            'wholesale_price' => $fmtOpt($model->wholesale_price),
            'min_sale_price' => $fmtOpt($model->min_sale_price),
            'oem' => $model->oem ?? '',
            'factory_number' => $model->factory_number ?? '',
            'min_stock' => $fmtStock($model->min_stock),
            'aggregated_stock' => $stockByGood->has($gid) ? round((float) $stockByGood[$gid], 2) : 0.0,
            'aggregated_purchase_price' => $purchaseByGood->has($gid) ? $purchaseByGood[$gid] : null,
        ];

        $movements = $reports->goodMovementLedgerForGood($branchId, $gid, 200)->values()->all();

        return response()->json([
            'good' => $goodPayload,
            'movements' => $movements,
            'categories' => $this->distinctSaleGoodCategoryNames($branchId),
        ]);
    }

    /**
     * @return list<string>
     */
    private function distinctSaleGoodCategoryNames(int $branchId): array
    {
        $names = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->whereNotNull('category')
            ->pluck('category');

        return $names
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn (string $s): bool => $s !== '')
            ->unique()
            ->sort(fn (string $a, string $b): int => strcasecmp($a, $b))
            ->values()
            ->all();
    }

    public function create(): View
    {
        return view('admin.sale-goods.create', [
            'good' => new Good([
                'unit' => 'шт.',
                'is_service' => false,
            ]),
        ]);
    }

    public function store(StoreSaleGoodsRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $v = $request->validated();

        Good::query()->create($this->payloadFromRequest($v, $branchId, false));

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', 'Товар добавлен.');
    }

    public function edit(int $good): View
    {
        $model = $this->goodsGoodOrAbort($good);

        return view('admin.sale-goods.edit', [
            'good' => $model,
        ]);
    }

    public function update(UpdateSaleGoodsRequest $request, int $good): RedirectResponse|JsonResponse
    {
        $model = $this->goodsGoodOrAbort($good);
        $v = $request->validated();

        $model->update(
            $this->payloadFromRequest($v, (int) $model->branch_id, true)
        );

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Товар сохранён.']);
        }

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', 'Товар сохранён.');
    }

    public function destroy(int $good): RedirectResponse
    {
        $model = $this->goodsGoodOrAbort($good);

        if ($this->goodHasBlockingReferences($model)) {
            return redirect()
                ->route('admin.sale-goods.index')
                ->withErrors(['delete' => 'Нельзя удалить товар: он используется в документах или на складе. Сначала исправьте справочные данные или оформите корректировки.']);
        }

        $model->delete();

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', 'Товар удалён.');
    }

    public function import(ImportSaleGoodsRequest $request, SaleGoodsImportService $importService): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => 'Не удалось прочитать файл.']);
        }

        $result = $importService->importFromFile($path, $branchId);

        $message = 'Импортировано товаров: '.$result['imported'].'.';
        if ($result['skipped'] > 0) {
            $message .= ' Пропущено пустых строк: '.$result['skipped'].'.';
        }

        if ($result['errors'] !== []) {
            session()->flash('import_errors', array_slice($result['errors'], 0, 40));
        }

        return redirect()
            ->route('admin.sale-goods.index')
            ->with('status', $message);
    }

    public function sampleImport(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Артикул', 'Наименование *', 'Ед.', 'Цена, сом', 'Штрихкод', 'Категория'],
            ['ART-001', 'Фильтр масляный', 'шт.', '450', '', 'Расходники'],
            ['', 'Колодки передние', ' компл.', '2500,50', '', ''],
        ], null, 'A1', true);

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'obrazec_tovarov.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function goodsGoodOrAbort(int $id): Good
    {
        $branchId = (int) auth()->user()->branch_id;

        return Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function payloadFromRequest(array $v, int $branchId, bool $forUpdate): array
    {
        $unit = trim((string) ($v['unit'] ?? 'шт.'));

        $salePrice = $this->openingBalanceService->parseOptionalMoney($v['sale_price'] ?? null);
        $wholesalePrice = $this->openingBalanceService->parseOptionalMoney($v['wholesale_price'] ?? null);
        $minSalePrice = $this->openingBalanceService->parseOptionalMoney($v['min_sale_price'] ?? null);
        $minStock = $this->openingBalanceService->parseOptionalNonNegativeDecimal($v['min_stock'] ?? null);

        $barcode = isset($v['barcode']) ? trim((string) $v['barcode']) : '';
        $barcode = $barcode === '' ? null : $barcode;
        $category = isset($v['category']) ? trim((string) $v['category']) : '';
        $category = $category === '' ? null : $category;
        $oem = isset($v['oem']) ? trim((string) $v['oem']) : '';
        $oem = $oem === '' ? null : $oem;
        $factory = isset($v['factory_number']) ? trim((string) $v['factory_number']) : '';
        $factory = $factory === '' ? null : $factory;

        $data = [
            'article_code' => trim((string) $v['article_code']),
            'name' => trim((string) $v['name']),
            'barcode' => $barcode,
            'category' => $category,
            'unit' => $unit !== '' ? $unit : 'шт.',
            'sale_price' => $salePrice,
            'wholesale_price' => $wholesalePrice,
            'is_service' => false,
            'min_sale_price' => $minSalePrice,
            'oem' => $oem,
            'factory_number' => $factory,
            'min_stock' => $minStock,
        ];

        if (! $forUpdate) {
            $data['branch_id'] = $branchId;
        }

        return $data;
    }

    private function goodHasBlockingReferences(Good $good): bool
    {
        return $good->retailSaleLines()->exists()
            || $good->legalEntitySaleLines()->exists()
            || $good->serviceOrderLines()->exists()
            || $good->purchaseReceiptLines()->exists()
            || $good->customerReturnLines()->exists()
            || $good->openingStockBalances()->exists();
    }

    private function displayGoodName(Good $g): string
    {
        $n = (string) $g->name;
        $code = trim((string) ($g->article_code ?? ''));
        if ($code === '') {
            return $n;
        }
        foreach ([' '.$code, ' - '.$code, '-'.$code, ' – '.$code] as $suff) {
            if ($suff !== '' && Str::endsWith($n, $suff)) {
                return rtrim(Str::substr($n, 0, Str::length($n) - Str::length($suff)), " \t\-–—,");
            }
        }
        if (Str::endsWith($n, $code)) {
            return rtrim(Str::substr($n, 0, Str::length($n) - Str::length($code)), " \t\-–—,");
        }

        return $n;
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
