<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodsCharacteristicsRequest;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\OrganizationBankAccount;
use App\Models\RetailSale;
use App\Models\RetailSaleRefund;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BranchReportService;
use App\Services\CashLedgerService;
use App\Services\GoodsCharacteristicsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const GOODS_STOCK_PER_PAGE = 100;

    /** Ограничено из‑за PHP max_input_vars (часто 1000): полная строка формы даёт ~14 полей, иначе хвост страницы «обрезается» и валидация падает на lines.N. */
    private const GOODS_CHARACTERISTICS_PER_PAGE = 50;

    private const GOODS_MOVEMENT_PER_PAGE = 75;

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
                    ($r['article'] ?? '') . ' ' . ($r['name'] ?? ''),
                    'UTF-8'
                );

                return str_contains($hay, $needle);
            })->values();
        }

        $totals = $this->aggregateGoodsMovementTotals($allRows);

        $page = max(1, (int) $request->integer('page', 1));
        $total = $allRows->count();
        $lastPage = max(1, (int) ceil($total / self::GOODS_MOVEMENT_PER_PAGE));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $rowsPaginator = new LengthAwarePaginator(
            $allRows->forPage($page, self::GOODS_MOVEMENT_PER_PAGE)->values(),
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
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'rowsPaginator' => $rowsPaginator,
            'totals' => $totals,
            'catalogGoodsCount' => $catalogGoodsCount,
            'filteredGoodsCount' => $total,
            'searchQuery' => $searchQuery,
            'onlyWithMovement' => $onlyWithMovement,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
    }

    public function cashMovement(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);

        $daily = $this->cashLedger->movementDailyByKind($branchId, $from, $to);

        return view('admin.reports.cash-movement', [
            'summary' => $this->cashLedger->periodAccountSummary($branchId, $from, $to),
            'dailyRows' => $daily['rows'],
            'dailyTotals' => $daily['totals'],
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
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

        return view('admin.reports.sales-by-goods', [
            'pageTitle' => 'Продажи по товарам и услугам',
            'rows' => $data['rows'],
            'categoryRows' => $data['categoryRows'],
            'totalRevenue' => $data['totalRevenue'],
            'serviceRows' => $data['serviceRows'],
            'serviceCategoryRows' => $data['serviceCategoryRows'],
            'totalServiceRevenue' => $data['totalServiceRevenue'],
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
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

    public function expensesByCategory(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $rows = $this->reports->expensesByCategory($branchId, $from, $to);

        return view('admin.reports.expenses-by-category', [
            'pageTitle' => 'Расходы по категориям',
            'rows' => $rows,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
    }

    public function turnover(Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$from, $to] = $this->parsePeriod($request);
        $rows = $this->reports->cashTrialBalance($branchId, $from, $to);

        return view('admin.reports.turnover', [
            'pageTitle' => 'Оборотно-сальдовая ведомость',
            'rows' => $rows,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
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

        $retailSales = RetailSale::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $cashShift->user_id)
            ->where('created_at', '>=', $cashShift->opened_at)
            ->where('created_at', '<=', $until)
            ->with(['payments.organizationBankAccount'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $cashMovements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $cashShift->user_id)
            ->where('created_at', '>=', $cashShift->opened_at)
            ->where('created_at', '<=', $until)
            ->with(['ourAccount', 'fromAccount', 'toAccount', 'counterparty'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $refunds = RetailSaleRefund::query()
            ->where('created_at', '>=', $cashShift->opened_at)
            ->where('created_at', '<=', $until)
            ->whereHas('customerReturn', fn ($q) => $q->where('branch_id', $branchId))
            ->whereHas('retailSale', fn ($q) => $q->where('user_id', $cashShift->user_id))
            ->with(['customerReturn', 'retailSale', 'organizationBankAccount'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

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
            'until' => $until,
            'closingTable' => $closingTable,
            'kindBreakdown' => $kindBreakdown,
            'retailSales' => $retailSales,
            'cashMovements' => $cashMovements,
            'refunds' => $refunds,
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
     * @param  LengthAwarePaginator<\App\Models\Good>  $paginator
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
            . ' ' . (string) ($r['name'] ?? '')
            . ' ' . $barcode
            . ' ' . (string) ($r['category'] ?? '')
            . ' ' . $oem
            . ' ' . $factory
            . ' ' . (string) ($r['warehouse'] ?? '')
            . ' ' . (string) ($r['good_id'] ?? '')
            . ' ' . $articleCompact
            . ' ' . $barcodeCompact
            // Варианты без пробелов и дефисов — один и тот же ОЭМ в разных карточках часто пишут по-разному
            . ' ' . $oemCompact
            . ' ' . $factoryCompact
            . ' ' . $articleTokenCompact,
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
