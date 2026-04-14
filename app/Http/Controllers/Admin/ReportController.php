<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\BranchReportService;
use App\Services\CashLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private readonly BranchReportService $reports,
        private readonly CashLedgerService $cashLedger
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

        $rows = $this->reports->goodsStock($branchId, $warehouseId);

        return view('admin.reports.goods-stock', [
            'pageTitle' => 'Остатки товаров',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'rows' => $rows,
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

        return view('admin.reports.goods-movement', [
            'pageTitle' => 'Движение товаров',
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $warehouseId,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
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
        $rows = $this->reports->salesByGoods($branchId, $from, $to);

        return view('admin.reports.sales-by-goods', [
            'pageTitle' => 'Продажи по товарам',
            'rows' => $rows,
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

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
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
