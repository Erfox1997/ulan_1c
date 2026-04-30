<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodsCharacteristicsRequest;
use App\Models\CashShift;
use App\Models\Good;
use App\Models\OrganizationBankAccount;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BranchReportService;
use App\Services\CashLedgerService;
use App\Services\GoodsCharacteristicsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    private const GOODS_STOCK_PER_PAGE = 100;

    /** Ограничено из‑за PHP max_input_vars (часто 1000): полная строка формы даёт ~14 полей, иначе хвост страницы «обрезается» и валидация падает на lines.N. */
    private const GOODS_CHARACTERISTICS_PER_PAGE = 50;

    private const GOODS_MOVEMENT_PER_PAGE = 75;

    /** Совпадает с логикой trade.sale-goods: параметр категории «без категории». */
    private const GOODS_MOVEMENT_NO_CATEGORY = '__no_category__';

    private const GOODS_STOCK_HISTORICAL_PER_PAGE = 100;

    public function __construct(
        private readonly BranchReportService $reports,
        private readonly CashLedgerService $cashLedger,
        private readonly GoodsCharacteristicsService $goodsCharacteristicsService
    ) {}

    public function goodsStock(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->integer('warehouse_id');

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($warehouseId !== 0 && ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = 0;
        }

        $searchQuery = trim((string) $request->query('q', ''));

        $minStockFilter = (string) $request->query('min_stock_filter', '');
        if (in_array($minStockFilter, ['oem', 'line'], true)) {
            // прямой URL / закладка
        } elseif ($request->boolean('min_stock_oem')) {
            $minStockFilter = 'oem';
        } elseif ($request->boolean('min_stock_line')) {
            $minStockFilter = 'line';
        } elseif ($request->has('oem_min_low')) {
            $minStockFilter = $request->boolean('oem_min_low') ? 'oem' : '';
        } else {
            $minStockFilter = '';
        }
        if (! in_array($minStockFilter, ['', 'oem', 'line'], true)) {
            $minStockFilter = '';
        }

        $qtySort = (string) $request->query('qty_sort', '');
        if (! in_array($qtySort, ['', 'asc', 'desc'], true)) {
            $qtySort = '';
        }

        $allRows = $this->reports->goodsStock($branchId, $warehouseId);
        $allRows = $this->reports->enrichGoodsStockWithOemGroupLow($allRows);
        if ($searchQuery !== '') {
            $allRows = $this->filterGoodsStockRowsBySearchQuery($allRows, $searchQuery);
        }
        if ($minStockFilter === 'oem') {
            $allRows = $allRows->filter(function (array $r): bool {
                return (bool) ($r['oem_group_low'] ?? false);
            })->values();
        } elseif ($minStockFilter === 'line') {
            $allRows = $allRows->filter(function (array $r): bool {
                return (bool) ($r['line_below_min'] ?? false);
            })->values();
        }

        if ($qtySort === 'asc') {
            $allRows = $allRows->sortBy(fn (array $r): float => (float) ($r['quantity'] ?? 0))->values();
        } elseif ($qtySort === 'desc') {
            $allRows = $allRows->sortByDesc(fn (array $r): float => (float) ($r['quantity'] ?? 0))->values();
        }

        $page = max(1, (int) $request->integer('page', 1));
        $total = $allRows->count();
        $lastPage = max(1, (int) ceil($total / self::GOODS_STOCK_PER_PAGE));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $rowsPaginator = new LengthAwarePaginator(
            $allRows->forPage($page, self::GOODS_STOCK_PER_PAGE)->values(),
            $total,
            self::GOODS_STOCK_PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $rowsPaginator->onEachSide = 1;
        $rowsPaginator->withQueryString();

        $purchaseModalRows = $rowsPaginator->getCollection()->map(function (array $r): array {
            $bid = (int) ($r['opening_stock_balance_id'] ?? 0);

            return [
                'row_key' => (string) ($r['row_key'] ?? ($bid > 0 ? 'b'.$bid : 'g'.(int) ($r['good_id'] ?? 0).'w'.(int) ($r['warehouse_id'] ?? 0))),
                'balance_id' => $bid,
                'selectable' => (bool) ($r['has_balance_record'] ?? ($bid > 0)),
                'name' => (string) ($r['name'] ?? ''),
                'unit' => (string) ($r['unit'] ?? ''),
                'stock_qty' => (float) ($r['quantity'] ?? 0),
                'oem' => ($r['oem'] ?? '') !== '' ? (string) $r['oem'] : null,
                'warehouse' => (string) ($r['warehouse'] ?? ''),
            ];
        })->values()->all();

        return view('admin.reports.goods-stock', [
            'pageTitle' => 'Остатки товаров',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'searchQuery' => $searchQuery,
            'minStockFilter' => $minStockFilter,
            'qtySort' => $qtySort,
            'rowsPaginator' => $rowsPaginator,
            'purchaseModalRows' => $purchaseModalRows,
        ]);
    }

    public function goodsStockHistorical(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->integer('warehouse_id');

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($warehouseId !== 0 && ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = 0;
        }

        $asOf = $request->query('as_of')
            ? Carbon::parse($request->query('as_of'))->startOfDay()
            : Carbon::today()->startOfDay();

        $deltaMap = $this->reports->netGoodsStockDeltaAfterDate($branchId, $warehouseId, $asOf);

        $searchQuery = trim((string) $request->query('q', ''));

        $allRows = $this->reports->goodsStock($branchId, $warehouseId);
        $allRows = $allRows->map(function (array $r) use ($deltaMap): array {
            $k = (int) ($r['good_id'] ?? 0).'|'.(int) ($r['warehouse_id'] ?? 0);
            $delta = (float) ($deltaMap[$k] ?? 0.0);
            $qtyNow = (float) ($r['quantity'] ?? 0);
            $qtyThen = round($qtyNow - $delta, 4);

            return $r + [
                'quantity_as_of' => $qtyThen,
                'movement_after_date' => round($delta, 4),
            ];
        });

        if ($searchQuery !== '') {
            $allRows = $this->filterGoodsStockRowsBySearchQuery($allRows, $searchQuery);
        }

        $page = max(1, (int) $request->integer('page', 1));
        $total = $allRows->count();
        $lastPage = max(1, (int) ceil($total / self::GOODS_STOCK_HISTORICAL_PER_PAGE));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $rowsPaginator = new LengthAwarePaginator(
            $allRows->forPage($page, self::GOODS_STOCK_HISTORICAL_PER_PAGE)->values(),
            $total,
            self::GOODS_STOCK_HISTORICAL_PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $rowsPaginator->onEachSide = 1;
        $rowsPaginator->withQueryString();

        return view('admin.reports.goods-stock-historical', [
            'pageTitle' => 'Остатки задним числом',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'asOf' => $asOf,
            'searchQuery' => $searchQuery,
            'rowsPaginator' => $rowsPaginator,
        ]);
    }

    public function goodsMovement(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $warehouseId = (int) $request->integer('warehouse_id');

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($warehouseId !== 0 && ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = 0;
        }

        $data = $this->reports->goodsMovement($branchId, $warehouseId, $from, $to);

        $searchQuery = trim((string) $request->query('q', ''));
        $onlyWithMovement = $request->boolean('only_with_movement', true);

        $allRows = $data['rows'];
        $catalogGoodsCount = $allRows->count();

        if ($onlyWithMovement) {
            $allRows = $allRows->filter(fn (array $r) => $this->goodsMovementRowHasActivity($r))->values();
        }
        if ($searchQuery !== '') {
            $needle = mb_strtolower($searchQuery, 'UTF-8');
            $allRows = $allRows->filter(function (array $r) use ($needle): bool {
                $hay = mb_strtolower(
                    ($r['article'] ?? '')
                    .' '.($r['name'] ?? '')
                    .' '.($r['category'] ?? ''),
                    'UTF-8'
                );

                return str_contains($hay, $needle);
            })->values();
        }

        $totals = $this->aggregateGoodsMovementTotals($allRows);

        $detailGoodId = max(0, (int) $request->integer('good', 0));
        if ($detailGoodId > 0) {
            $detailRow = $allRows->first(fn (array $r) => (int) ($r['good_id'] ?? 0) === $detailGoodId);
            if ($detailRow === null) {
                return redirect()->route('admin.reports.goods-movement', Arr::except($request->query(), ['good', 'category', 'page']));
            }
            $catRaw = trim((string) ($detailRow['category'] ?? ''));
            $detailCategoryKey = $catRaw === '' ? self::GOODS_MOVEMENT_NO_CATEGORY : $catRaw;
            $detailCategoryTitle = $catRaw === '' ? 'Без категории' : $catRaw;

            $ledgerRows = $this->reports->goodMovementLedgerForGoodInPeriod(
                $branchId,
                $detailGoodId,
                $from,
                $to,
                $warehouseId,
                400
            )->map(fn (array $r): array => [
                'date_human' => (string) $r['date_human'],
                'label' => (string) $r['label'],
                'warehouse' => (string) $r['warehouse'],
                'quantity' => (float) $r['quantity'],
                'direction' => (string) $r['direction'],
            ])->values()->all();

            return view('admin.reports.goods-movement', [
                'pageTitle' => 'Движение товаров',
                'viewMode' => 'good',
                'warehouses' => $warehouses,
                'selectedWarehouseId' => $warehouseId,
                'detailRow' => $detailRow,
                'detailGoodId' => $detailGoodId,
                'detailCategoryKey' => $detailCategoryKey,
                'detailCategoryTitle' => $detailCategoryTitle,
                'ledgerRows' => $ledgerRows,
                'totals' => $totals,
                'catalogGoodsCount' => $catalogGoodsCount,
                'filteredGoodsCount' => $allRows->count(),
                'categoryGoodsCount' => null,
                'searchQuery' => $searchQuery,
                'onlyWithMovement' => $onlyWithMovement,
                'filterFrom' => $from->format('Y-m-d'),
                'filterTo' => $to->format('Y-m-d'),
                'categories' => collect(),
                'rowsPaginator' => null,
                'categoryTitle' => null,
                'selectedCategoryKey' => null,
                'totalsInCategory' => null,
            ]);
        }

        $rawCategory = $request->query('category');

        if ($rawCategory === null || ! is_string($rawCategory) || trim($rawCategory) === '') {
            return view('admin.reports.goods-movement', [
                'pageTitle' => 'Движение товаров',
                'viewMode' => 'categories',
                'categories' => $this->goodsMovementCategoryFolders($allRows),
                'warehouses' => $warehouses,
                'selectedWarehouseId' => $warehouseId,
                'totals' => $totals,
                'catalogGoodsCount' => $catalogGoodsCount,
                'filteredGoodsCount' => $allRows->count(),
                'categoryGoodsCount' => null,
                'searchQuery' => $searchQuery,
                'onlyWithMovement' => $onlyWithMovement,
                'filterFrom' => $from->format('Y-m-d'),
                'filterTo' => $to->format('Y-m-d'),
                'rowsPaginator' => null,
                'detailRow' => null,
                'detailGoodId' => null,
                'detailCategoryKey' => null,
                'detailCategoryTitle' => null,
                'categoryTitle' => null,
                'selectedCategoryKey' => null,
                'totalsInCategory' => null,
            ]);
        }

        $categoryQueryParam = trim($rawCategory);
        $categoryFilter = $categoryQueryParam === self::GOODS_MOVEMENT_NO_CATEGORY ? '' : $categoryQueryParam;

        $categoryRows = $allRows->filter(function (array $r) use ($categoryFilter): bool {
            $c = trim((string) ($r['category'] ?? ''));

            if ($categoryFilter === '') {
                return $c === '';
            }

            return $c === $categoryFilter;
        })->values();

        $categoryTitle = $categoryFilter === '' ? 'Без категории' : $categoryFilter;
        $totalsInCategory = $this->aggregateGoodsMovementTotals($categoryRows);

        $page = max(1, (int) $request->integer('page', 1));
        $total = $categoryRows->count();
        $lastPage = max(1, (int) ceil($total / self::GOODS_MOVEMENT_PER_PAGE));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $rowsPaginator = new LengthAwarePaginator(
            $categoryRows->forPage($page, self::GOODS_MOVEMENT_PER_PAGE)->values(),
            $total,
            self::GOODS_MOVEMENT_PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        $rowsPaginator->onEachSide = 1;
        $rowsPaginator->withQueryString();

        return view('admin.reports.goods-movement', [
            'pageTitle' => 'Движение товаров',
            'viewMode' => 'goods',
            'categories' => collect(),
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'rowsPaginator' => $rowsPaginator,
            'totals' => $totals,
            'totalsInCategory' => $totalsInCategory,
            'catalogGoodsCount' => $catalogGoodsCount,
            'filteredGoodsCount' => $allRows->count(),
            'categoryGoodsCount' => $total,
            'searchQuery' => $searchQuery,
            'onlyWithMovement' => $onlyWithMovement,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
            'detailRow' => null,
            'detailGoodId' => null,
            'detailCategoryKey' => null,
            'detailCategoryTitle' => null,
            'categoryTitle' => $categoryTitle,
            'selectedCategoryKey' => $categoryQueryParam,
        ]);
    }

    /** JSON: журнал движений по товару (для модального окна отчёта «Движение товаров»). */
    public function goodsMovementLedgerData(Request $request): JsonResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $warehouseId = (int) $request->integer('warehouse_id');
        $goodId = max(0, (int) $request->integer('good_id'));

        $allowedWh = Warehouse::query()
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->all();

        if ($warehouseId !== 0 && ! in_array($warehouseId, $allowedWh, true)) {
            $warehouseId = 0;
        }

        if ($goodId <= 0) {
            return response()->json(['message' => 'Не указан товар.'], 422);
        }

        $rows = $this->reports->goodMovementLedgerForGoodInPeriod(
            $branchId,
            $goodId,
            $from,
            $to,
            $warehouseId,
            400
        );

        return response()->json([
            'rows' => $rows->map(fn (array $r): array => [
                'date_human' => (string) $r['date_human'],
                'label' => (string) $r['label'],
                'warehouse' => (string) $r['warehouse'],
                'quantity' => (float) $r['quantity'],
                'direction' => (string) $r['direction'],
            ])->values()->all(),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{label: string, count: int, category_key: string}>
     */
    private function goodsMovementCategoryFolders(Collection $rows): Collection
    {
        $groups = $rows->groupBy(function (array $r): string {
            $c = trim((string) ($r['category'] ?? ''));

            return $c === '' ? '' : $c;
        })->map(fn (Collection $group, string $key): array => [
            'label' => $key === '' ? 'Без категории' : $key,
            'count' => $group->count(),
            'category_key' => $key === '' ? self::GOODS_MOVEMENT_NO_CATEGORY : $key,
        ]);

        return $groups->values()->sort(function (array $a, array $b): int {
            $aUncat = $a['category_key'] === self::GOODS_MOVEMENT_NO_CATEGORY;
            $bUncat = $b['category_key'] === self::GOODS_MOVEMENT_NO_CATEGORY;
            if ($aUncat !== $bUncat) {
                return $aUncat ? 1 : -1;
            }

            return strcasecmp((string) $a['label'], (string) $b['label']);
        })->values();
    }

    public function cashBalances(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $on = $request->query('on')
            ? Carbon::parse($request->query('on'))->startOfDay()
            : Carbon::now()->startOfDay();

        $rows = $this->reports->cashBalancesOn($branchId, $on);

        return view('admin.reports.cash-balances', [
            'pageTitle' => 'Остатки по кассе и счетам',
            'rows' => $rows,
            'filterOn' => $on->format('Y-m-d'),
        ]);
    }

    public function salesByGoods(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $data = $this->reports->salesByGoods($branchId, $from, $to);

        $filterGoodId = (int) $request->integer('good_id');
        $filterGood = null;
        $filterGoodSummary = '';
        if ($filterGoodId > 0) {
            $filterGood = Good::query()
                ->where('branch_id', $branchId)
                ->whereKey($filterGoodId)
                ->first();
            if ($filterGood === null) {
                $filterGoodId = 0;
            } else {
                $code = trim((string) $filterGood->article_code);
                $name = trim((string) $filterGood->name);
                $filterGoodSummary = $code !== '' && $name !== ''
                    ? $code.' · '.$name
                    : ($name !== '' ? $name : ($code !== '' ? $code : '—'));
            }
        }

        $goodsCatRaw = $request->query('goods_category');
        $goodsCategorySelected = is_string($goodsCatRaw) && trim($goodsCatRaw) !== ''
            ? trim($goodsCatRaw)
            : null;

        $svcCatRaw = $request->query('services_category');
        $servicesCategorySelected = is_string($svcCatRaw) && trim($svcCatRaw) !== ''
            ? trim($svcCatRaw)
            : null;

        $goodsRows = $data['rows'];
        $serviceRows = $data['serviceRows'];

        if ($filterGoodId > 0 && $filterGood !== null) {
            $goodsRows = $goodsRows->where('good_id', $filterGoodId)->values();
            $serviceRows = $serviceRows->where('good_id', $filterGoodId)->values();
            $cat = trim((string) $filterGood->category);
            if ($cat === '') {
                $cat = 'Без категории';
            }
            if ($filterGood->is_service) {
                $servicesCategorySelected = $cat;
                $goodsCategorySelected = null;
            } else {
                $goodsCategorySelected = $cat;
                $servicesCategorySelected = null;
            }
        }

        $goodsBlock = $this->recomputeSalesByGoodsAggregates($goodsRows);
        $svcBlock = $this->recomputeSalesByGoodsAggregates($serviceRows);

        $goodsByCategory = $goodsBlock['rows']->groupBy('category');
        $goodsFolderRows = collect($goodsBlock['categoryRows'])
            ->map(function (array $cr) use ($goodsByCategory): array {
                $grp = $goodsByCategory->get($cr['category']) ?? collect();

                return array_merge($cr, [
                    'goods_count' => $grp->count(),
                    'quantity_sum' => round((float) $grp->sum('quantity'), 2),
                ]);
            })
            ->values();

        $servicesByCategory = $svcBlock['rows']->groupBy('category');
        $serviceFolderRows = collect($svcBlock['categoryRows'])
            ->map(function (array $cr) use ($servicesByCategory): array {
                $grp = $servicesByCategory->get($cr['category']) ?? collect();

                return array_merge($cr, [
                    'goods_count' => $grp->count(),
                    'quantity_sum' => round((float) $grp->sum('quantity'), 2),
                ]);
            })
            ->values();

        $filteredGoodsRows = collect();
        if ($goodsCategorySelected !== null) {
            $filteredGoodsRows = (($goodsByCategory->get($goodsCategorySelected)) ?? collect())->values();
        }

        $filteredServiceRows = collect();
        if ($servicesCategorySelected !== null) {
            $filteredServiceRows = (($servicesByCategory->get($servicesCategorySelected)) ?? collect())->values();
        }

        return view('admin.reports.sales-by-goods', [
            'pageTitle' => 'Продажи по товарам и услугам',
            'rows' => $goodsBlock['rows'],
            'categoryRows' => $goodsBlock['categoryRows'],
            'totalRevenue' => $goodsBlock['totalRevenue'],
            'serviceRows' => $svcBlock['rows'],
            'serviceCategoryRows' => $svcBlock['categoryRows'],
            'totalServiceRevenue' => $svcBlock['totalRevenue'],
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
            'goodsFolderRows' => $goodsFolderRows,
            'serviceFolderRows' => $serviceFolderRows,
            'goodsCategorySelected' => $goodsCategorySelected,
            'servicesCategorySelected' => $servicesCategorySelected,
            'filteredGoodsRows' => $filteredGoodsRows,
            'filteredServiceRows' => $filteredServiceRows,
            'filterGoodId' => $filterGoodId,
            'filterGoodSummary' => $filterGoodSummary,
        ]);
    }

    public function salesByClients(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $data = $this->reports->salesByClients($branchId, $from, $to);

        return view('admin.reports.sales-by-clients', [
            'pageTitle' => 'Продажи по клиентам',
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
    }

    public function grossProfit(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $data = $this->reports->grossProfit($branchId, $from, $to);

        return view('admin.reports.gross-profit', [
            'pageTitle' => 'Валовая прибыль',
            'revenue' => $data['revenue'],
            'cost' => $data['cost'],
            'profit' => $data['profit'],
            'lines' => $data['lines'],
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
    }

    public function netProfit(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $summary = $this->reports->netProfitCashSummary($branchId, $from, $to);

        return view('admin.reports.net-profit', [
            'pageTitle' => 'Чистая прибыль',
            'summary' => $summary,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
    }

    public function netProfitDetail(Request $request): JsonResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $kind = trim((string) $request->query('kind', ''));

        try {
            $data = $this->reports->netProfitCashDetail($branchId, $from, $to, $kind);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Некорректный запрос.'], 422);
        }

        return response()->json($data);
    }

    public function turnover(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $osv = $this->reports->fullTurnoverOsv($branchId, $from, $to);
        $user = $request->user();
        $user?->loadMissing('branch');
        $branch = $user?->branch;

        return view('admin.reports.turnover', [
            'pageTitle' => 'Оборотно-сальдовая ведомость',
            'osv' => $osv,
            'branchName' => $branch?->name,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
            'periodLabel' => 'с '.$from->format('d.m.Y').' по '.$to->format('d.m.Y'),
        ]);
    }

    public function turnoverDetail(Request $request): JsonResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $accountId = $request->integer('account_id');
        if ($accountId === 0) {
            return response()->json(['message' => 'Укажите счёт.'], 422);
        }
        $from = Carbon::parse((string) $request->query('from'))->startOfDay();
        $to = Carbon::parse((string) $request->query('to'))->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $kind = (string) $request->query('kind');

        try {
            if ($accountId < 0) {
                $data = $this->reports->turnoverOsvSyntheticDetail($branchId, $accountId, $kind, $from, $to);
            } else {
                $data = $this->cashLedger->turnoverOsvCellDetail($branchId, $accountId, $from, $to, $kind);
            }
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Счёт не найден или недоступен для этого филиала.'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Неизвестный тип ячейки.'], 422);
        }

        return response()->json($data);
    }

    public function shiftReport(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $cashierId = (int) $request->integer('user_id');

        $users = User::query()
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        $shifts = CashShift::query()
            ->where('branch_id', $branchId)
            ->where('opened_at', '>=', $from->copy()->startOfDay())
            ->where('opened_at', '<=', $to->copy()->endOfDay())
            ->when($cashierId > 0, fn ($q) => $q->where('user_id', $cashierId))
            ->with('user')
            ->orderByDesc('opened_at')
            ->paginate(20)
            ->withQueryString();

        $summaries = $this->shiftSummariesForCollection($branchId, $shifts->getCollection());

        return view('admin.reports.shift-report', [
            'pageTitle' => 'Сменный отчёт',
            'shifts' => $shifts,
            'summaries' => $summaries,
            'users' => $users,
            'selectedUserId' => $cashierId,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
    }

    public function salesByClientsPdf(Request $request): Response
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $data = $this->reports->salesByClients($branchId, $from, $to);
        $filename = 'prodazhi-po-klientam-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.reports.pdf.sales-by-clients', [
            'pageTitle' => 'Продажи по клиентам',
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'periodLabel' => 'с '.$from->format('d.m.Y').' по '.$to->format('d.m.Y'),
            'branchName' => $request->user()?->branch?->name,
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    public function grossProfitPdf(Request $request): Response
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $data = $this->reports->grossProfit($branchId, $from, $to);
        $filename = 'valovaya-pribyl-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.reports.pdf.gross-profit', [
            'pageTitle' => 'Валовая прибыль',
            'revenue' => $data['revenue'],
            'cost' => $data['cost'],
            'profit' => $data['profit'],
            'lines' => $data['lines'],
            'periodLabel' => 'с '.$from->format('d.m.Y').' по '.$to->format('d.m.Y'),
            'branchName' => $request->user()?->branch?->name,
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    public function netProfitPdf(Request $request): Response
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $summary = $this->reports->netProfitCashSummary($branchId, $from, $to);
        $filename = 'chistaya-pribyl-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.reports.pdf.net-profit', [
            'pageTitle' => 'Чистая прибыль',
            'summary' => $summary,
            'periodLabel' => 'с '.$from->format('d.m.Y').' по '.$to->format('d.m.Y'),
            'branchName' => $request->user()?->branch?->name,
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    public function turnoverPdf(Request $request): Response
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $osv = $this->reports->fullTurnoverOsv($branchId, $from, $to);
        $user = $request->user();
        $user?->loadMissing('branch');
        $filename = 'oborotno-saldovaya-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.reports.pdf.turnover', [
            'pageTitle' => 'Оборотно-сальдовая ведомость',
            'osv' => $osv,
            'branchName' => $user?->branch?->name,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
            'periodLabel' => 'с '.$from->format('d.m.Y').' по '.$to->format('d.m.Y'),
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    public function shiftReportPdf(Request $request): Response
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $cashierId = (int) $request->integer('user_id');

        $users = User::query()
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        $shifts = CashShift::query()
            ->where('branch_id', $branchId)
            ->where('opened_at', '>=', $from->copy()->startOfDay())
            ->where('opened_at', '<=', $to->copy()->endOfDay())
            ->when($cashierId > 0, fn ($q) => $q->where('user_id', $cashierId))
            ->with('user')
            ->orderByDesc('opened_at')
            ->get();

        $summaries = $this->shiftSummariesForCollection($branchId, collect($shifts->all()));
        $cashierLabel = $cashierId > 0
            ? ($users->firstWhere('id', $cashierId)?->name ?? '—')
            : 'Все';

        $filename = 'smennyy-otchet-'.$from->format('Y-m-d').'-'.$to->format('Y-m-d').'.pdf';

        return Pdf::loadView('admin.reports.pdf.shift-report', [
            'pageTitle' => 'Сменный отчёт',
            'shifts' => $shifts,
            'summaries' => $summaries,
            'periodLabel' => 'с '.$from->format('d.m.Y').' по '.$to->format('d.m.Y'),
            'branchName' => $request->user()?->branch?->name,
            'cashierLabel' => $cashierLabel,
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    public function shiftReportShow(CashShift $cashShift): View
    {
        $branchId = (int) auth()->user()->branch_id;
        $until = $cashShift->closed_at ?? Carbon::now();

        $closingTable = $this->cashLedger->shiftAccountClosingTable($cashShift, $branchId);
        $kindBreakdown = $this->cashLedger->shiftMoneyKindBreakdown(
            $branchId,
            $cashShift->opened_at,
            $until,
            (int) $cashShift->user_id
        );
        $kindDetailLists = $this->cashLedger->shiftKindDetailLists(
            $branchId,
            $cashShift->opened_at,
            $until,
            (int) $cashShift->user_id
        );

        $cashShift->load('user');

        $closingFactRows = [];
        if ($cashShift->closed_at !== null && is_array($cashShift->closing_by_account) && $cashShift->closing_by_account !== []) {
            $accIds = array_map('intval', array_keys($cashShift->closing_by_account));
            $accountsById = OrganizationBankAccount::query()
                ->whereIn('id', $accIds)
                ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
                ->with('organization')
                ->get()
                ->keyBy('id');
            foreach ($cashShift->closing_by_account as $id => $amt) {
                $aid = (int) $id;
                $acc = $accountsById->get($aid);
                $closingFactRows[] = [
                    'label' => $acc
                        ? trim(($acc->organization?->name ?? '—').' — '.$acc->labelWithoutAccountNumber())
                        : 'Счёт № '.$aid,
                    'amount' => round((float) $amt, 2),
                ];
            }
        }

        return view('admin.reports.shift-report-show', [
            'pageTitle' => 'Смена № '.$cashShift->id,
            'shift' => $cashShift,
            'closingTable' => $closingTable,
            'kindBreakdown' => $kindBreakdown,
            'kindDetailLists' => $kindDetailLists,
            'closingFactRows' => $closingFactRows,
        ]);
    }

    public function goodsCharacteristics(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $warehouseId = (int) $request->integer('warehouse_id', 0);
        $defaultId = $warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id;
        if ($warehouseId === 0 || ! $warehouses->contains('id', $warehouseId)) {
            $warehouseId = (int) ($defaultId ?? 0);
        }

        $missingKeys = $this->normalizeGoodsCharacteristicsMissing($request);

        $goodsPaginator = null;
        $linesForForm = [];

        if ($warehouseId !== 0) {
            $query = $this->goodsCharacteristicsService->incompleteGoodsQueryForMissingKeys(
                $branchId,
                $warehouseId,
                $missingKeys
            );
            $query->with(['openingStockBalances' => fn ($q) => $q->where('warehouse_id', $warehouseId)]);
            $goodsPaginator = $query->orderBy('id')->paginate(self::GOODS_CHARACTERISTICS_PER_PAGE)->withQueryString();
            $goodsPaginator->onEachSide = 1;
            $linesForForm = $this->buildGoodsCharacteristicsLinesForForm($goodsPaginator);
        }

        return view('admin.reports.goods-characteristics', [
            'pageTitle' => 'Характеристики товаров',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'missingKeys' => $missingKeys,
            'filterOptions' => GoodsCharacteristicsService::FILTER_OPTIONS,
            'goodsPaginator' => $goodsPaginator,
            'linesForForm' => $linesForForm,
        ]);
    }

    public function goodsCharacteristicsStore(StoreGoodsCharacteristicsRequest $request): RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;
        $warehouseId = (int) $request->validated('warehouse_id');
        $page = max(1, (int) $request->integer('page', 1));

        $missingKeys = [];
        if (! $request->boolean('all_incomplete')) {
            $allowed = GoodsCharacteristicsService::missingFilterKeyList();
            $raw = $request->input('missing', []);
            if (is_array($raw)) {
                foreach ($raw as $v) {
                    $k = (string) $v;
                    if (in_array($k, $allowed, true)) {
                        $missingKeys[] = $k;
                    }
                }
            }
            $missingKeys = array_values(array_unique($missingKeys));
        }

        DB::transaction(function () use ($branchId, $warehouseId, $request): void {
            foreach ($request->input('lines', []) as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $this->goodsCharacteristicsService->syncRow($branchId, $warehouseId, $line);
            }
        });

        $query = [
            'warehouse_id' => $warehouseId,
            'page' => $page,
        ];
        foreach ($missingKeys as $m) {
            $query['missing'][] = $m;
        }

        return redirect()->route('admin.reports.goods-characteristics', $query)->with('status', 'Сохранено.');
    }

    /**
     * @return list<string>
     */
    private function normalizeGoodsCharacteristicsMissing(Request $request): array
    {
        $allowed = GoodsCharacteristicsService::missingFilterKeyList();

        if ($request->boolean('all_incomplete')) {
            return [];
        }

        $raw = $request->query('missing', []);
        if (! is_array($raw)) {
            $raw = $raw !== null && $raw !== '' ? [(string) $raw] : [];
        }

        $out = [];
        foreach ($raw as $v) {
            $k = (string) $v;
            if (in_array($k, $allowed, true)) {
                $out[] = $k;
            }
        }
        $out = array_values(array_unique($out));
        if ($out !== []) {
            return $out;
        }

        if (! $request->has('missing') && $request->has('filter')) {
            $f = (string) $request->query('filter', 'all');
            if ($f === 'all') {
                return [];
            }
            if (in_array($f, $allowed, true)) {
                return [$f];
            }
        }

        return [];
    }

    /**
     * @param  LengthAwarePaginator<Good>  $paginator
     * @return list<array<string, mixed>>
     */
    private function buildGoodsCharacteristicsLinesForForm(LengthAwarePaginator $paginator): array
    {
        $oldLines = old('lines');
        if (is_array($oldLines) && $oldLines !== []) {
            $lines = [];
            foreach ($oldLines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $lines[] = [
                    'good_id' => isset($line['good_id']) ? (int) $line['good_id'] : null,
                    'article_code' => (string) ($line['article_code'] ?? ''),
                    'name' => (string) ($line['name'] ?? ''),
                    'barcode' => (string) ($line['barcode'] ?? ''),
                    'category' => (string) ($line['category'] ?? ''),
                    'unit_cost' => isset($line['unit_cost']) ? (string) $line['unit_cost'] : '',
                    'wholesale_price' => isset($line['wholesale_price']) ? (string) $line['wholesale_price'] : '',
                    'sale_price' => isset($line['sale_price']) ? (string) $line['sale_price'] : '',
                    'oem' => (string) ($line['oem'] ?? ''),
                    'factory_number' => (string) ($line['factory_number'] ?? ''),
                    'min_stock' => isset($line['min_stock']) ? (string) $line['min_stock'] : '',
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: 'шт.',
                ];
            }

            return $lines;
        }

        $lines = [];
        foreach ($paginator->items() as $good) {
            $bal = $good->openingStockBalances->first();
            $lines[] = [
                'good_id' => $good->id,
                'article_code' => $good->article_code,
                'name' => $good->name,
                'barcode' => (string) ($good->barcode ?? ''),
                'category' => (string) ($good->category ?? ''),
                'unit_cost' => $bal && $bal->unit_cost !== null ? (string) $bal->unit_cost : '',
                'wholesale_price' => $good->wholesale_price !== null ? (string) $good->wholesale_price : '',
                'sale_price' => $good->sale_price !== null ? (string) $good->sale_price : '',
                'oem' => (string) ($good->oem ?? ''),
                'factory_number' => (string) ($good->factory_number ?? ''),
                'min_stock' => $good->min_stock !== null ? (string) $good->min_stock : '',
                'unit' => trim((string) ($good->unit ?? '')) ?: 'шт.',
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function goodsMovementRowHasActivity(array $row): bool
    {
        foreach ([
            'purchase', 'purchase_return', 'transfer_out', 'transfer_in', 'surplus',
            'customer_return', 'retail_sale', 'legal_sale', 'writeoff',
        ] as $k) {
            if (abs((float) ($row[$k] ?? 0)) > 0.000001) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function aggregateGoodsMovementTotals(Collection $rows): array
    {
        return [
            'purchase' => round((float) $rows->sum('purchase'), 2),
            'purchase_return' => round((float) $rows->sum('purchase_return'), 2),
            'transfer_out' => round((float) $rows->sum('transfer_out'), 2),
            'transfer_in' => round((float) $rows->sum('transfer_in'), 2),
            'transfer_net' => round((float) $rows->sum('transfer_net'), 2),
            'surplus' => round((float) $rows->sum('surplus'), 2),
            'customer_return' => round((float) $rows->sum('customer_return'), 2),
            'retail_sale' => round((float) $rows->sum('retail_sale'), 2),
            'legal_sale' => round((float) $rows->sum('legal_sale'), 2),
            'sold_total' => round((float) $rows->sum('sold_total'), 2),
            'writeoff' => round((float) $rows->sum('writeoff'), 2),
        ];
    }

    /**
     * Поиск по остаткам: слова разделяются пробелами — должны найтись все (логика «И»).
     * Учитываются id номенклатуры, артикул и штрихкод без пробелов.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function filterGoodsStockRowsBySearchQuery(Collection $rows, string $searchQuery): Collection
    {
        $tokens = $this->parseGoodsStockSearchTokens($searchQuery);
        if ($tokens === []) {
            return $rows;
        }

        return $rows->filter(function (array $r) use ($tokens): bool {
            $hay = $this->goodsStockRowSearchHaystack($r);
            foreach ($tokens as $t) {
                if (str_contains($hay, $t)) {
                    continue;
                }
                $tCompact = $this->goodsStockSearchCompactToken($t);
                if ($tCompact !== '' && str_contains($hay, $tCompact)) {
                    continue;
                }

                return false;
            }

            return true;
        })->values();
    }

    /**
     * @return list<string>
     */
    private function parseGoodsStockSearchTokens(string $searchQuery): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', $searchQuery) ?? '');
        if ($q === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = [];
        foreach ($parts as $p) {
            $t = mb_strtolower(trim((string) $p), 'UTF-8');
            if ($t !== '') {
                $tokens[] = $t;
            }
        }

        return $tokens;
    }

    private function goodsStockRowSearchHaystack(array $r): string
    {
        $article = (string) ($r['article'] ?? '');
        $barcode = (string) ($r['barcode'] ?? '');
        $oem = (string) ($r['oem'] ?? '');
        $factory = (string) ($r['factory_number'] ?? '');
        $articleCompact = (string) (preg_replace('/\s+/u', '', $article) ?? '');
        $barcodeCompact = (string) (preg_replace('/\s+/u', '', $barcode) ?? '');

        $oemTrim = trim($oem);
        $oemCompact = $this->goodsStockSearchCompactToken($oemTrim);
        $factoryCompact = $this->goodsStockSearchCompactToken($factory);
        $articleTokenCompact = $this->goodsStockSearchCompactToken($article);

        return mb_strtolower(
            $article
            .' '.(string) ($r['name'] ?? '')
            .' '.$barcode
            .' '.(string) ($r['category'] ?? '')
            .' '.$oem
            .' '.$factory
            .' '.(string) ($r['warehouse'] ?? '')
            .' '.(string) ($r['good_id'] ?? '')
            .' '.$articleCompact
            .' '.$barcodeCompact
            // Варианты без пробелов и дефисов — один и тот же ОЭМ в разных карточках часто пишут по-разному
            .' '.$oemCompact
            .' '.$factoryCompact
            .' '.$articleTokenCompact,
            'UTF-8'
        );
    }

    /**
     * Нормализация для сравнения артикула/ОЭМ/штрихкода: без пробелов и типичных разделителей.
     */
    private function goodsStockSearchCompactToken(string $value): string
    {
        $v = mb_strtolower(trim($value), 'UTF-8');

        return (string) (preg_replace('/[\s\x{00A0}\-–—.,_\/\\\\]+/u', '', $v) ?? '');
    }

    private function shiftOpeningTotal(CashShift $shift): float
    {
        if (is_array($shift->opening_by_account) && $shift->opening_by_account !== []) {
            return round((float) array_sum($shift->opening_by_account), 2);
        }

        return round((float) $shift->opening_cash, 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CashShift>  $shifts
     * @return array<int, array{opening_total: float, movement_total: float}>
     */
    private function shiftSummariesForCollection(int $branchId, Collection $shifts): array
    {
        $summaries = [];
        foreach ($shifts as $shift) {
            $until = $shift->closed_at ?? Carbon::now();
            $net = $this->cashLedger->netCashChangeByAccountInShiftWindow(
                $branchId,
                $shift->opened_at,
                $until,
                (int) $shift->user_id
            );
            $summaries[$shift->id] = [
                'opening_total' => $this->shiftOpeningTotal($shift),
                'movement_total' => round((float) array_sum($net), 2),
            ];
        }

        return $summaries;
    }

    /**
     * Пересчёт долей и сводки по категориям после фильтрации строк (например по good_id).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     categoryRows: Collection<int, array{category: string, revenue: float, revenue_share_pct: float}>,
     *     totalRevenue: float
     * }
     */
    private function recomputeSalesByGoodsAggregates(Collection $rows): array
    {
        $totalRevenue = round((float) $rows->sum('revenue'), 2);
        $rows = $rows->map(function (array $row) use ($totalRevenue): array {
            $pct = $totalRevenue > 0.0
                ? round((float) $row['revenue'] / $totalRevenue * 100, 2)
                : 0.0;

            return $row + ['revenue_share_pct' => $pct];
        })->values();

        $categoryRows = $rows
            ->groupBy('category')
            ->map(function (Collection $group) use ($totalRevenue): array {
                $revenue = round((float) $group->sum('revenue'), 2);
                $pct = $totalRevenue > 0.0
                    ? round($revenue / $totalRevenue * 100, 2)
                    : 0.0;

                return [
                    'category' => (string) $group->first()['category'],
                    'revenue' => $revenue,
                    'revenue_share_pct' => $pct,
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        return [
            'rows' => $rows,
            'categoryRows' => $categoryRows,
            'totalRevenue' => $totalRevenue,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parsePeriod(Request $request): array
    {
        [$defaultFrom, $defaultTo] = $this->cashLedger->defaultMovementPeriod();

        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : $defaultFrom;
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->startOfDay()
            : $defaultTo;
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
