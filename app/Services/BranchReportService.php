<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Counterparty;
use App\Models\CustomerReturnLine;
use App\Models\Good;
use App\Models\LegalEntitySale;
use App\Models\LegalEntitySaleLine;
use App\Models\OpeningStockBalance;
use App\Models\OrganizationBankAccount;
use App\Models\PurchaseReceiptLine;
use App\Models\PurchaseReturnLine;
use App\Models\RetailSale;
use App\Models\RetailSaleLine;
use App\Models\StockSurplusLine;
use App\Models\StockTransferLine;
use App\Models\StockWriteoffLine;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchReportService
{
    public function __construct(
        private readonly CashLedgerService $cashLedger
    ) {}

    /**
     * Остатки товаров: по складу или сводно по филиалу.
     *
     * @return Collection<int, array{
     *     row_key: string,
     *     has_balance_record: bool,
     *     opening_stock_balance_id: int,
     *     good_id: int,
     *     warehouse_id: int,
     *     article: string,
     *     name: string,
     *     unit: string,
     *     barcode: string,
     *     category: string,
     *     sale_price: ?float,
     *     min_sale_price: ?float,
     *     oem: string,
     *     factory_number: string,
     *     min_stock: ?float,
     *     warehouse: string,
     *     quantity: float,
     *     unit_cost: ?float,
     *     amount: ?float
     * }>
     */
    public function goodsStock(int $branchId, int $warehouseId): Collection
    {
        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        if ($warehouses->isEmpty()) {
            return collect();
        }

        $warehouseIds = $warehouseId > 0
            ? ($warehouses->has($warehouseId) ? collect([(int) $warehouseId]) : collect())
            : $warehouses->keys()->map(fn ($id) => (int) $id);

        if ($warehouseIds->isEmpty()) {
            return collect();
        }

        $goods = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $balancesByKey = OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->whereIn('warehouse_id', $warehouseIds->all())
            ->get()
            ->keyBy(fn (OpeningStockBalance $b) => $b->good_id.'_'.$b->warehouse_id);

        $rows = collect();
        foreach ($warehouseIds as $wid) {
            foreach ($goods as $g) {
                $k = $g->id.'_'.$wid;
                $b = $balancesByKey->get($k);
                $qty = $b !== null ? (float) $b->quantity : 0.0;
                $cost = $b !== null && $b->unit_cost !== null ? (float) $b->unit_cost : null;
                $amount = $cost !== null ? round($qty * $cost, 2) : null;

                $rows->push([
                    'row_key' => $b !== null ? 'b'.(int) $b->id : 'g'.(int) $g->id.'w'.(int) $wid,
                    'has_balance_record' => $b !== null,
                    'opening_stock_balance_id' => $b !== null ? (int) $b->id : 0,
                    'good_id' => (int) $g->id,
                    'warehouse_id' => (int) $wid,
                    'article' => (string) ($g->article_code ?? ''),
                    'name' => (string) ($g->name ?? ''),
                    'unit' => (string) ($g->unit ?? 'шт.'),
                    'barcode' => (string) ($g->barcode ?? ''),
                    'category' => (string) ($g->category ?? ''),
                    'sale_price' => $g->sale_price !== null ? (float) $g->sale_price : null,
                    'min_sale_price' => $g->min_sale_price !== null ? (float) $g->min_sale_price : null,
                    'oem' => (string) ($g->oem ?? ''),
                    'factory_number' => (string) ($g->factory_number ?? ''),
                    'min_stock' => $g->min_stock !== null ? (float) $g->min_stock : null,
                    'warehouse' => $warehouses->get($wid)?->name ?? '—',
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'amount' => $amount,
                ]);
            }
        }

        return $rows->sort(function (array $a, array $b): int {
            if ($a['warehouse_id'] !== $b['warehouse_id']) {
                return $a['warehouse_id'] <=> $b['warehouse_id'];
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        })->values();
    }

    /**
     * Помечает строки отчёта «Остатки»: для группы с одинаковым непустым ОЭМ суммируется количество по всем строкам;
     * без ОЭМ каждая строка — отдельная «группа». oem_group_low = true, если сумма ≤ мин. остатка (минимум порога по группе).
     * line_below_min = true, если по самой строке количество ≤ min_stock (без группировки по ОЭМ).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function enrichGoodsStockWithOemGroupLow(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $sumByKey = [];
        $minByKey = [];

        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $key = $this->goodsStockOemGroupKey($r);
            $qty = (float) ($r['quantity'] ?? 0);
            $sumByKey[$key] = ($sumByKey[$key] ?? 0.0) + $qty;
            $ms = $r['min_stock'] ?? null;
            if ($ms !== null) {
                $fv = (float) $ms;
                if (! isset($minByKey[$key])) {
                    $minByKey[$key] = $fv;
                } else {
                    $minByKey[$key] = min($minByKey[$key], $fv);
                }
            }
        }

        return $rows->map(function (array $r) use ($sumByKey, $minByKey): array {
            $key = $this->goodsStockOemGroupKey($r);
            $sum = (float) ($sumByKey[$key] ?? 0);
            $min = $minByKey[$key] ?? null;
            $r['oem_group_sum'] = $sum;
            $r['oem_group_min'] = $min;
            $r['oem_group_low'] = $min !== null && $sum <= $min;

            $qty = (float) ($r['quantity'] ?? 0);
            $rowMin = $r['min_stock'] ?? null;
            $r['line_below_min'] = $rowMin !== null && $qty <= (float) $rowMin;

            return $r;
        })->values();
    }

    /**
     * Ключ группы для суммирования остатков по ОЭМ.
     */
    private function goodsStockOemGroupKey(array $r): string
    {
        $oem = trim((string) ($r['oem'] ?? ''));
        if ($oem !== '') {
            return 'oem:'.$oem;
        }

        $article = (string) ($r['article'] ?? '');
        $wh = (string) ($r['warehouse'] ?? '');

        return 'empty:'.$article."\x1f".$wh;
    }

    /**
     * Движение товаров за период: свод по каждой номенклатуре (товары, не услуги).
     * Учитываются те же документы и фильтр по складу, что и раньше в ленте движений.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function goodsMovement(int $branchId, int $warehouseId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $goods = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->orderBy('name')
            ->get();

        $buckets = [];
        foreach ($goods as $g) {
            $buckets[(int) $g->id] = [
                'good_id' => (int) $g->id,
                'article' => (string) ($g->article_code ?? ''),
                'name' => (string) $g->name,
                'unit' => (string) ($g->unit ?? 'шт.'),
                'purchase' => 0.0,
                'purchase_return' => 0.0,
                'transfer_out' => 0.0,
                'transfer_in' => 0.0,
                'surplus' => 0.0,
                'customer_return' => 0.0,
                'retail_sale' => 0.0,
                'legal_sale' => 0.0,
                'writeoff' => 0.0,
            ];
        }

        $add = function (string $key, Collection $sums) use (&$buckets): void {
            foreach ($sums as $goodId => $qty) {
                $gid = (int) $goodId;
                if (! isset($buckets[$gid])) {
                    continue;
                }
                $buckets[$gid][$key] += (float) $qty;
            }
        };

        $whDoc = function ($q, string $col = 'warehouse_id') use ($warehouseId) {
            if ($warehouseId > 0) {
                $q->where($col, $warehouseId);
            }
        };

        $purchaseSums = PurchaseReceiptLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('purchaseReceipt', function ($q) use ($branchId, $fromStr, $toStr, $whDoc) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                $whDoc($q);
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('purchase', $purchaseSums);

        $purchaseReturnSums = PurchaseReturnLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('purchaseReturn', function ($q) use ($branchId, $fromStr, $toStr, $whDoc) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                $whDoc($q);
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('purchase_return', $purchaseReturnSums);

        $retailSums = RetailSaleLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('retailSale', function ($q) use ($branchId, $fromStr, $toStr, $whDoc) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                $whDoc($q);
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('retail_sale', $retailSums);

        $legalSums = LegalEntitySaleLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('legalEntitySale', function ($q) use ($branchId, $fromStr, $toStr, $whDoc) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                $whDoc($q);
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('legal_sale', $legalSums);

        $customerReturnSums = CustomerReturnLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('customerReturn', function ($q) use ($branchId, $fromStr, $toStr, $whDoc) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                $whDoc($q);
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('customer_return', $customerReturnSums);

        $transferOutQ = StockTransferLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('stockTransfer', function ($q) use ($branchId, $fromStr, $toStr, $warehouseId) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                if ($warehouseId > 0) {
                    $q->where('from_warehouse_id', $warehouseId);
                }
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('transfer_out', $transferOutQ);

        $transferInQ = StockTransferLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('stockTransfer', function ($q) use ($branchId, $fromStr, $toStr, $warehouseId) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                if ($warehouseId > 0) {
                    $q->where('to_warehouse_id', $warehouseId);
                }
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('transfer_in', $transferInQ);

        $writeoffSums = StockWriteoffLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('stockWriteoff', function ($q) use ($branchId, $fromStr, $toStr, $whDoc) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                $whDoc($q);
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('writeoff', $writeoffSums);

        $surplusSums = StockSurplusLine::query()
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->whereHas('stockSurplus', function ($q) use ($branchId, $fromStr, $toStr, $whDoc) {
                $q->where('branch_id', $branchId)
                    ->whereDate('document_date', '>=', $fromStr)
                    ->whereDate('document_date', '<=', $toStr);
                $whDoc($q);
            })
            ->whereNotNull('good_id')
            ->selectRaw('good_id, SUM(quantity) as qty')
            ->groupBy('good_id')
            ->get()
            ->keyBy('good_id')
            ->map(fn ($r) => (float) $r->qty);
        $add('surplus', $surplusSums);

        $numericKeys = [
            'purchase', 'purchase_return', 'transfer_out', 'transfer_in', 'surplus',
            'customer_return', 'retail_sale', 'legal_sale', 'writeoff',
        ];

        $rows = collect($buckets)->map(function (array $row) use ($numericKeys) {
            foreach ($numericKeys as $k) {
                $row[$k] = round($row[$k], 2);
            }
            $row['sold_total'] = round($row['retail_sale'] + $row['legal_sale'], 2);
            $row['transfer_net'] = round($row['transfer_in'] - $row['transfer_out'], 2);

            return $row;
        })->values();

        $totals = [
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

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * Суммарное изменение остатка по движениям после даты (строго: document_date > on).
     * Остаток на конец дня on = текущий остаток из opening_stock_balances − delta.
     *
     * Ключ: "good_id|warehouse_id" => изменение (+ приход на склад, − расход).
     *
     * @return array<string, float>
     */
    public function netGoodsStockDeltaAfterDate(int $branchId, int $warehouseId, CarbonInterface $on): array
    {
        $onStr = $on->format('Y-m-d');
        $map = [];

        $add = function (int $goodId, int $whId, float $delta) use (&$map, $warehouseId): void {
            if ($warehouseId > 0 && $whId !== $warehouseId) {
                return;
            }
            $k = $goodId.'|'.$whId;
            $map[$k] = ($map[$k] ?? 0.0) + $delta;
        };

        $purchaseRows = PurchaseReceiptLine::query()
            ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $purchaseRows->where('pr.warehouse_id', $warehouseId);
        }
        foreach ($purchaseRows->get(['purchase_receipt_lines.good_id', 'pr.warehouse_id', 'purchase_receipt_lines.quantity']) as $r) {
            $add((int) $r->good_id, (int) $r->warehouse_id, (float) $r->quantity);
        }

        $purchaseReturnRows = PurchaseReturnLine::query()
            ->join('purchase_returns as pr', 'pr.id', '=', 'purchase_return_lines.purchase_return_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $purchaseReturnRows->where('pr.warehouse_id', $warehouseId);
        }
        foreach ($purchaseReturnRows->get(['purchase_return_lines.good_id', 'pr.warehouse_id', 'purchase_return_lines.quantity']) as $r) {
            $add((int) $r->good_id, (int) $r->warehouse_id, -(float) $r->quantity);
        }

        $retailRows = RetailSaleLine::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_lines.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('rs.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $retailRows->where('rs.warehouse_id', $warehouseId);
        }
        foreach ($retailRows->get(['retail_sale_lines.good_id', 'rs.warehouse_id', 'retail_sale_lines.quantity']) as $r) {
            $add((int) $r->good_id, (int) $r->warehouse_id, -(float) $r->quantity);
        }

        $legalRows = LegalEntitySaleLine::query()
            ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
            ->where('ls.branch_id', $branchId)
            ->whereDate('ls.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $legalRows->where('ls.warehouse_id', $warehouseId);
        }
        foreach ($legalRows->get(['legal_entity_sale_lines.good_id', 'ls.warehouse_id', 'legal_entity_sale_lines.quantity']) as $r) {
            $add((int) $r->good_id, (int) $r->warehouse_id, -(float) $r->quantity);
        }

        $customerReturnRows = CustomerReturnLine::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'customer_return_lines.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->whereDate('cr.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $customerReturnRows->where('cr.warehouse_id', $warehouseId);
        }
        foreach ($customerReturnRows->get(['customer_return_lines.good_id', 'cr.warehouse_id', 'customer_return_lines.quantity']) as $r) {
            $add((int) $r->good_id, (int) $r->warehouse_id, (float) $r->quantity);
        }

        $writeoffRows = StockWriteoffLine::query()
            ->join('stock_writeoffs as sw', 'sw.id', '=', 'stock_writeoff_lines.stock_writeoff_id')
            ->where('sw.branch_id', $branchId)
            ->whereDate('sw.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $writeoffRows->where('sw.warehouse_id', $warehouseId);
        }
        foreach ($writeoffRows->get(['stock_writeoff_lines.good_id', 'sw.warehouse_id', 'stock_writeoff_lines.quantity']) as $r) {
            $add((int) $r->good_id, (int) $r->warehouse_id, -(float) $r->quantity);
        }

        $surplusRows = StockSurplusLine::query()
            ->join('stock_surpluses as ss', 'ss.id', '=', 'stock_surplus_lines.stock_surplus_id')
            ->where('ss.branch_id', $branchId)
            ->whereDate('ss.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $surplusRows->where('ss.warehouse_id', $warehouseId);
        }
        foreach ($surplusRows->get(['stock_surplus_lines.good_id', 'ss.warehouse_id', 'stock_surplus_lines.quantity']) as $r) {
            $add((int) $r->good_id, (int) $r->warehouse_id, (float) $r->quantity);
        }

        $transferLines = StockTransferLine::query()
            ->join('stock_transfers as st', 'st.id', '=', 'stock_transfer_lines.stock_transfer_id')
            ->where('st.branch_id', $branchId)
            ->whereDate('st.document_date', '>', $onStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false));
        if ($warehouseId > 0) {
            $transferLines->where(function ($q) use ($warehouseId) {
                $q->where('st.from_warehouse_id', $warehouseId)
                    ->orWhere('st.to_warehouse_id', $warehouseId);
            });
        }
        foreach ($transferLines->get([
            'stock_transfer_lines.good_id',
            'stock_transfer_lines.quantity',
            'st.from_warehouse_id',
            'st.to_warehouse_id',
        ]) as $r) {
            $qty = (float) $r->quantity;
            $add((int) $r->good_id, (int) $r->from_warehouse_id, -$qty);
            $add((int) $r->good_id, (int) $r->to_warehouse_id, $qty);
        }

        return $map;
    }

    /**
     * Остатки по денежным счетам на дату.
     *
     * @return Collection<int, array{id: int, label: string, currency: string, balance: float}>
     */
    public function cashBalancesOn(int $branchId, CarbonInterface $on): Collection
    {
        $at = Carbon::parse($on->format('Y-m-d'))->addDay();

        return OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->with('organization')
            ->orderBy('organization_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (OrganizationBankAccount $acc) use ($branchId, $at) {
                $bal = $this->cashLedger->balanceAtDateExclusive($branchId, $acc->id, $at);

                return [
                    'id' => $acc->id,
                    'label' => $acc->movementReportLabel(),
                    'currency' => $acc->currency,
                    'balance' => $bal,
                ];
            })->values();
    }

    /**
     * Продажи по товарам и услугам (количество, выручка, доля в % и сводка по категориям для каждого блока).
     *
     * @return array{
     *     rows: Collection<int, array{good_id: int, article: string, name: string, unit: string, category: string, quantity: float, revenue: float, revenue_share_pct: float}>,
     *     categoryRows: Collection<int, array{category: string, revenue: float, revenue_share_pct: float}>,
     *     totalRevenue: float,
     *     serviceRows: Collection<int, array{good_id: int, article: string, name: string, unit: string, category: string, quantity: float, revenue: float, revenue_share_pct: float}>,
     *     serviceCategoryRows: Collection<int, array{category: string, revenue: float, revenue_share_pct: float}>,
     *     totalServiceRevenue: float
     * }
     */
    public function salesByGoods(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $goods = $this->salesByNomenclatureKind($branchId, $fromStr, $toStr, false);
        $services = $this->salesByNomenclatureKind($branchId, $fromStr, $toStr, true);

        return [
            'rows' => $goods['rows'],
            'categoryRows' => $goods['categoryRows'],
            'totalRevenue' => $goods['totalRevenue'],
            'serviceRows' => $services['rows'],
            'serviceCategoryRows' => $services['categoryRows'],
            'totalServiceRevenue' => $services['totalRevenue'],
        ];
    }

    /**
     * @return array{
     *     rows: Collection<int, array{good_id: int, article: string, name: string, unit: string, category: string, quantity: float, revenue: float, revenue_share_pct: float}>,
     *     categoryRows: Collection<int, array{category: string, revenue: float, revenue_share_pct: float}>,
     *     totalRevenue: float
     * }
     */
    private function salesByNomenclatureKind(int $branchId, string $fromStr, string $toStr, bool $isService): array
    {
        $retail = RetailSaleLine::query()
            ->select([
                'good_id',
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('SUM(line_sum) as revenue'),
            ])
            ->whereHas('retailSale', fn ($q) => $q->where('branch_id', $branchId)
                ->whereDate('document_date', '>=', $fromStr)
                ->whereDate('document_date', '<=', $toStr))
            ->whereHas('good', fn ($g) => $g->where('is_service', $isService))
            ->groupBy('good_id');

        $legal = LegalEntitySaleLine::query()
            ->select([
                'good_id',
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('SUM(line_sum) as revenue'),
            ])
            ->whereHas('legalEntitySale', fn ($q) => $q->where('branch_id', $branchId)
                ->whereDate('document_date', '>=', $fromStr)
                ->whereDate('document_date', '<=', $toStr))
            ->whereHas('good', fn ($g) => $g->where('is_service', $isService))
            ->groupBy('good_id');

        /** @var array<int, array{good_id: int, quantity: float, revenue: float}> $merged */
        $merged = [];

        foreach ($retail->get() as $row) {
            $gid = (int) $row->good_id;
            $merged[$gid] = [
                'good_id' => $gid,
                'quantity' => (float) $row->quantity,
                'revenue' => (float) $row->revenue,
            ];
        }

        foreach ($legal->get() as $row) {
            $gid = (int) $row->good_id;
            if (! isset($merged[$gid])) {
                $merged[$gid] = [
                    'good_id' => $gid,
                    'quantity' => 0.0,
                    'revenue' => 0.0,
                ];
            }
            $merged[$gid]['quantity'] += (float) $row->quantity;
            $merged[$gid]['revenue'] += (float) $row->revenue;
        }

        $mergedCollection = collect($merged);

        $goods = Good::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', array_keys($merged))
            ->get()
            ->keyBy('id');

        $rows = $mergedCollection->map(function (array $item) use ($goods) {
            $g = $goods->get($item['good_id']);
            $cat = trim((string) ($g?->category ?? ''));
            if ($cat === '') {
                $cat = 'Без категории';
            }

            return [
                'good_id' => $item['good_id'],
                'article' => (string) ($g?->article_code ?? ''),
                'name' => (string) ($g?->name ?? ''),
                'unit' => (string) ($g?->unit ?? 'шт.'),
                'category' => $cat,
                'quantity' => $item['quantity'],
                'revenue' => round($item['revenue'], 2),
            ];
        })->sortByDesc('revenue')->values();

        $totalRevenue = round((float) $rows->sum('revenue'), 2);

        $rows = $rows->map(function (array $row) use ($totalRevenue): array {
            $pct = $totalRevenue > 0.0
                ? round($row['revenue'] / $totalRevenue * 100, 2)
                : 0.0;

            return $row + ['revenue_share_pct' => $pct];
        });

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
     * Продажи по клиентам: юрлица по контрагенту/названию, розница одной строкой.
     * Себестоимость и валовая прибыль — по товарам (без услуг), как в отчёте «Валовая прибыль»; колонка выручки — полная по документам.
     *
     * @return array{rows: Collection<int, array{key: string, label: string, revenue: float, cost: float, profit: float}>, totals: array{revenue: float, cost: float, profit: float}}
     */
    public function salesByClients(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $costByGoodWarehouse = OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->get()
            ->groupBy(fn (OpeningStockBalance $b) => $b->good_id.'_'.$b->warehouse_id);

        $resolveCost = function (int $goodId, int $warehouseId) use ($costByGoodWarehouse, $branchId): float {
            $b = $costByGoodWarehouse->get($goodId.'_'.$warehouseId)?->first();
            if ($b !== null && $b->unit_cost !== null) {
                return (float) $b->unit_cost;
            }

            return (float) (OpeningStockBalance::query()
                ->where('branch_id', $branchId)
                ->where('good_id', $goodId)
                ->whereNotNull('unit_cost')
                ->orderByDesc('quantity')
                ->value('unit_cost') ?? 0);
        };

        $retailTotal = (float) RetailSale::query()
            ->where('branch_id', $branchId)
            ->whereDate('document_date', '>=', $fromStr)
            ->whereDate('document_date', '<=', $toStr)
            ->sum('total_amount');

        $retailGoodsRevenue = 0.0;
        $retailCost = 0.0;

        $retailLines = RetailSaleLine::query()
            ->with(['retailSale', 'good'])
            ->whereHas('retailSale', fn ($q) => $q->where('branch_id', $branchId)
                ->whereDate('document_date', '>=', $fromStr)
                ->whereDate('document_date', '<=', $toStr))
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->get();

        foreach ($retailLines as $line) {
            $sale = $line->retailSale;
            $whId = (int) $sale->warehouse_id;
            $gid = (int) $line->good_id;
            $qty = (float) $line->quantity;
            $rev = (float) $line->line_sum;
            $unitCost = $resolveCost($gid, $whId);
            $retailGoodsRevenue += $rev;
            $retailCost += round($qty * $unitCost, 2);
        }
        $retailGoodsRevenue = round($retailGoodsRevenue, 2);
        $retailCost = round($retailCost, 2);
        $retailProfit = round($retailGoodsRevenue - $retailCost, 2);

        $legalRows = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereDate('document_date', '>=', $fromStr)
            ->whereDate('document_date', '<=', $toStr)
            ->with(['lines.good'])
            ->get();

        $byClient = [];
        foreach ($legalRows as $sale) {
            $sum = (float) $sale->lines->sum('line_sum');
            $label = null;
            if ($sale->counterparty_id) {
                $sale->loadMissing('counterparty');
                $cp = $sale->counterparty;
                $label = $cp ? trim((string) ($cp->full_name ?: Counterparty::buildFullName($cp->legal_form, $cp->name))) : null;
            }
            if ($label === null || $label === '') {
                $label = trim((string) $sale->buyer_name) ?: 'Покупатель (юрлицо)';
            }
            $key = 'cp:'.($sale->counterparty_id ?? 'name:'.mb_strtolower($label));
            if (! isset($byClient[$key])) {
                $byClient[$key] = ['label' => $label, 'revenue' => 0.0, 'cost' => 0.0, 'goods_revenue' => 0.0];
            }
            $byClient[$key]['revenue'] += $sum;

            foreach ($sale->lines as $line) {
                $g = $line->good;
                if (! $g || $g->is_service) {
                    continue;
                }
                $whId = (int) $sale->warehouse_id;
                $gid = (int) $line->good_id;
                $qty = (float) $line->quantity;
                $rev = (float) $line->line_sum;
                $unitCost = $resolveCost($gid, $whId);
                $cost = round($qty * $unitCost, 2);
                $byClient[$key]['goods_revenue'] += $rev;
                $byClient[$key]['cost'] += $cost;
            }
        }

        $out = collect();
        if ($retailTotal > 0) {
            $out->push([
                'key' => 'retail',
                'label' => 'Розница (физлица)',
                'revenue' => round($retailTotal, 2),
                'cost' => $retailCost,
                'profit' => $retailProfit,
            ]);
        }

        foreach ($byClient as $row) {
            $cost = round($row['cost'], 2);
            $goodsRev = round($row['goods_revenue'], 2);
            $profit = round($goodsRev - $cost, 2);
            $out->push([
                'key' => $row['label'],
                'label' => $row['label'],
                'revenue' => round($row['revenue'], 2),
                'cost' => $cost,
                'profit' => $profit,
            ]);
        }

        $out = $out->sortByDesc('revenue')->values();

        $totals = [
            'revenue' => round((float) $out->sum('revenue'), 2),
            'cost' => round((float) $out->sum('cost'), 2),
            'profit' => round((float) $out->sum('profit'), 2),
        ];

        return [
            'rows' => $out,
            'totals' => $totals,
        ];
    }

    /**
     * Валовая прибыль по проданным товарам: выручка минус себестоимость по учётной цене остатков.
     *
     * @return array{revenue: float, cost: float, profit: float, lines: Collection<int, array{good_id: int, article: string, name: string, quantity: float, revenue: float, cost: float, profit: float}>}
     */
    public function grossProfit(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $costByGoodWarehouse = OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->get()
            ->groupBy(fn (OpeningStockBalance $b) => $b->good_id.'_'.$b->warehouse_id);

        $resolveCost = function (int $goodId, int $warehouseId) use ($costByGoodWarehouse, $branchId): float {
            $b = $costByGoodWarehouse->get($goodId.'_'.$warehouseId)?->first();
            if ($b !== null && $b->unit_cost !== null) {
                return (float) $b->unit_cost;
            }

            return (float) (OpeningStockBalance::query()
                ->where('branch_id', $branchId)
                ->where('good_id', $goodId)
                ->whereNotNull('unit_cost')
                ->orderByDesc('quantity')
                ->value('unit_cost') ?? 0);
        };

        $lines = collect();

        $retailLines = RetailSaleLine::query()
            ->with(['retailSale', 'good'])
            ->whereHas('retailSale', fn ($q) => $q->where('branch_id', $branchId)
                ->whereDate('document_date', '>=', $fromStr)
                ->whereDate('document_date', '<=', $toStr))
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->get();

        foreach ($retailLines as $line) {
            $sale = $line->retailSale;
            $whId = (int) $sale->warehouse_id;
            $gid = (int) $line->good_id;
            $qty = (float) $line->quantity;
            $rev = (float) $line->line_sum;
            $unitCost = $resolveCost($gid, $whId);
            $cost = round($qty * $unitCost, 2);
            $profit = round($rev - $cost, 2);
            $lines->push([
                'good_id' => $gid,
                'article' => (string) ($line->article_code ?? ''),
                'name' => (string) ($line->name ?? ''),
                'quantity' => $qty,
                'revenue' => $rev,
                'cost' => $cost,
                'profit' => $profit,
            ]);
        }

        $legalLines = LegalEntitySaleLine::query()
            ->with(['legalEntitySale', 'good'])
            ->whereHas('legalEntitySale', fn ($q) => $q->where('branch_id', $branchId)
                ->whereDate('document_date', '>=', $fromStr)
                ->whereDate('document_date', '<=', $toStr))
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->get();

        foreach ($legalLines as $line) {
            $sale = $line->legalEntitySale;
            $whId = (int) $sale->warehouse_id;
            $gid = (int) $line->good_id;
            $qty = (float) $line->quantity;
            $rev = (float) $line->line_sum;
            $unitCost = $resolveCost($gid, $whId);
            $cost = round($qty * $unitCost, 2);
            $profit = round($rev - $cost, 2);
            $lines->push([
                'good_id' => $gid,
                'article' => (string) ($line->article_code ?? ''),
                'name' => (string) ($line->name ?? ''),
                'quantity' => $qty,
                'revenue' => $rev,
                'cost' => $cost,
                'profit' => $profit,
            ]);
        }

        $aggregated = [];
        foreach ($lines as $row) {
            $id = $row['good_id'];
            if (! isset($aggregated[$id])) {
                $aggregated[$id] = [
                    'good_id' => $id,
                    'article' => $row['article'],
                    'name' => $row['name'],
                    'quantity' => 0.0,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                    'profit' => 0.0,
                ];
            }
            $aggregated[$id]['quantity'] += $row['quantity'];
            $aggregated[$id]['revenue'] += $row['revenue'];
            $aggregated[$id]['cost'] += $row['cost'];
            $aggregated[$id]['profit'] += $row['profit'];
        }

        $aggCollection = collect($aggregated)->map(function (array $r) {
            $r['revenue'] = round($r['revenue'], 2);
            $r['cost'] = round($r['cost'], 2);
            $r['profit'] = round($r['profit'], 2);

            return $r;
        })->sortByDesc('profit')->values();

        $revenue = (float) $aggCollection->sum('revenue');
        $cost = (float) $aggCollection->sum('cost');

        return [
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'profit' => round($revenue - $cost, 2),
            'lines' => $aggCollection,
        ];
    }

    /**
     * Расходы по категориям (прочие расходы из кассы/банка).
     *
     * @return Collection<int, array{category: string, amount: float}>
     */
    public function expensesByCategory(int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $catExpr = "COALESCE(NULLIF(TRIM(expense_category), ''), 'Без категории')";

        return CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->selectRaw($catExpr.' as category')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy(DB::raw($catExpr))
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => [
                'category' => (string) $r->category,
                'amount' => round((float) $r->amount, 2),
            ]);
    }

    /**
     * Упрощённая оборотно-сальдовая ведомость по денежным счетам организаций филиала.
     *
     * @return Collection<int, array{id: int, label: string, currency: string, opening: float, turnover_in: float, turnover_out: float, closing: float}>
     */
    public function cashTrialBalance(int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $accounts = $this->cashLedger->accountsForBranch($branchId);
        $toExclusive = Carbon::parse($to->format('Y-m-d'))->addDay();

        $history = $this->cashLedger->historyRows($branchId, $from, $to);

        return $accounts->map(function (OrganizationBankAccount $acc) use ($branchId, $from, $toExclusive, $history) {
            $id = $acc->id;
            $opening = $this->cashLedger->balanceAtDateExclusive($branchId, $id, $from);
            $closing = $this->cashLedger->balanceAtDateExclusive($branchId, $id, $toExclusive);

            $in = 0.0;
            $out = 0.0;
            foreach ($history as $row) {
                $deltas = $row['delta_by_account'] ?? [];
                if (isset($deltas[$id])) {
                    $d = (float) $deltas[$id];
                    if ($d >= 0) {
                        $in += $d;
                    } else {
                        $out += -$d;
                    }
                }
            }

            return [
                'id' => $id,
                'label' => $acc->movementReportLabel(),
                'currency' => $acc->currency,
                'opening' => $opening,
                'turnover_in' => round($in, 2),
                'turnover_out' => round($out, 2),
                'closing' => $closing,
            ];
        })->values();
    }
}
