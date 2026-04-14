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
use App\Models\StockTransferLine;
use App\Models\StockWriteoffLine;
use App\Models\StockSurplusLine;
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
     * @return Collection<int, array{article: string, name: string, unit: string, warehouse: string, quantity: float, amount: ?float}>
     */
    public function goodsStock(int $branchId, int $warehouseId): Collection
    {
        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $q = OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->with('good')
            ->whereHas('good', fn ($g) => $g->where('is_service', false));

        if ($warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }

        return $q->orderBy('warehouse_id')->orderBy('id')->get()->map(function (OpeningStockBalance $b) use ($warehouses) {
            $qty = (float) $b->quantity;
            $cost = $b->unit_cost !== null ? (float) $b->unit_cost : null;
            $amount = $cost !== null ? round($qty * $cost, 2) : null;

            return [
                'article' => (string) ($b->good?->article_code ?? ''),
                'name' => (string) ($b->good?->name ?? ''),
                'unit' => (string) ($b->good?->unit ?? 'шт.'),
                'warehouse' => $warehouses->get($b->warehouse_id)?->name ?? '—',
                'quantity' => $qty,
                'amount' => $amount,
            ];
        })->values();
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

        $add = function (string $key, \Illuminate\Support\Collection $sums) use (&$buckets): void {
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
     * Продажи по товарам (количество и выручка).
     *
     * @return Collection<int, array{good_id: int, article: string, name: string, unit: string, quantity: float, revenue: float}>
     */
    public function salesByGoods(int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $retail = RetailSaleLine::query()
            ->select([
                'good_id',
                DB::raw('SUM(quantity) as quantity'),
                DB::raw('SUM(line_sum) as revenue'),
            ])
            ->whereHas('retailSale', fn ($q) => $q->where('branch_id', $branchId)
                ->whereDate('document_date', '>=', $fromStr)
                ->whereDate('document_date', '<=', $toStr))
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
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
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
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

        return $mergedCollection->map(function (array $item) use ($goods) {
            $g = $goods->get($item['good_id']);

            return [
                'good_id' => $item['good_id'],
                'article' => (string) ($g?->article_code ?? ''),
                'name' => (string) ($g?->name ?? ''),
                'unit' => (string) ($g?->unit ?? 'шт.'),
                'quantity' => $item['quantity'],
                'revenue' => round($item['revenue'], 2),
            ];
        })->sortByDesc('revenue')->values();
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
