<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodsCharacteristicsRequest;
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

    private const GOODS_CHARACTERISTICS_PER_PAGE = 100;

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
        $oemMinLowOnly = $request->boolean('oem_min_low');

        $allRows = $this->reports->goodsStock($branchId, $warehouseId);
        $allRows = $this->reports->enrichGoodsStockWithOemGroupLow($allRows);
        if ($searchQuery !== '') {
            $needle = mb_strtolower($searchQuery, 'UTF-8');
            $allRows = $allRows->filter(function (array $r) use ($needle): bool {
                $hay = mb_strtolower(
                    ($r['article'] ?? '')
                    . ' ' . ($r['name'] ?? '')
                    . ' ' . ($r['barcode'] ?? '')
                    . ' ' . ($r['category'] ?? '')
                    . ' ' . ($r['oem'] ?? '')
                    . ' ' . ($r['factory_number'] ?? '')
                    . ' ' . ($r['warehouse'] ?? ''),
                    'UTF-8'
                );

                return str_contains($hay, $needle);
            })->values();
        }
        if ($oemMinLowOnly) {
            $allRows = $allRows->filter(function (array $r): bool {
                return (bool) ($r['oem_group_low'] ?? false);
            })->values();
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
            return [
                'balance_id' => (int) ($r['opening_stock_balance_id'] ?? 0),
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
            'oemMinLowOnly' => $oemMinLowOnly,
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
            $needle = mb_strtolower($searchQuery, 'UTF-8');
            $allRows = $allRows->filter(function (array $r) use ($needle): bool {
                $hay = mb_strtolower(
                    ($r['article'] ?? '')
                    . ' ' . ($r['name'] ?? '')
                    . ' ' . ($r['barcode'] ?? '')
                    . ' ' . ($r['category'] ?? '')
                    . ' ' . ($r['oem'] ?? '')
                    . ' ' . ($r['factory_number'] ?? '')
                    . ' ' . ($r['warehouse'] ?? ''),
                    'UTF-8'
                );

                return str_contains($hay, $needle);
            })->values();
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
            'pageTitle' => 'Продажи по товарам',
            'rows' => $data['rows'],
            'categoryRows' => $data['categoryRows'],
            'totalRevenue' => $data['totalRevenue'],
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
                    'quantity' => (string) ($line['quantity'] ?? ''),
                    'unit_cost' => isset($line['unit_cost']) ? (string) $line['unit_cost'] : '',
                    'wholesale_price' => isset($line['wholesale_price']) ? (string) $line['wholesale_price'] : '',
                    'sale_price' => isset($line['sale_price']) ? (string) $line['sale_price'] : '',
                    'min_sale_price' => isset($line['min_sale_price']) ? (string) $line['min_sale_price'] : '',
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
                'quantity' => $bal && $bal->quantity !== null ? (string) $bal->quantity : '',
                'unit_cost' => $bal && $bal->unit_cost !== null ? (string) $bal->unit_cost : '',
                'wholesale_price' => $good->wholesale_price !== null ? (string) $good->wholesale_price : '',
                'sale_price' => $good->sale_price !== null ? (string) $good->sale_price : '',
                'min_sale_price' => $good->min_sale_price !== null ? (string) $good->min_sale_price : '',
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
