<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Counterparty;
use App\Models\CustomerReturnLine;
use App\Models\EmployeeAdvance;
use App\Models\Good;
use App\Models\LegalEntitySale;
use App\Models\LegalEntitySaleLine;
use App\Models\OpeningStockBalance;
use App\Models\OrganizationBankAccount;
use App\Models\PurchaseReceiptLine;
use App\Models\PurchaseReturnLine;
use App\Models\RetailSale;
use App\Models\RetailSaleLine;
use App\Models\RetailSalePayment;
use App\Models\RetailSaleRefund;
use App\Models\StockSurplusLine;
use App\Models\StockTransferLine;
use App\Models\StockWriteoffLine;
use App\Models\Warehouse;
use App\Support\InvoiceNakladnayaFormatter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

        $stockTotalsByGoodId = [];
        foreach ($this->goodsStock($branchId, $warehouseId) as $stockRow) {
            $gid = (int) $stockRow['good_id'];
            $stockTotalsByGoodId[$gid] = round(
                ($stockTotalsByGoodId[$gid] ?? 0.0) + (float) ($stockRow['quantity'] ?? 0),
                2
            );
        }

        $buckets = [];
        foreach ($goods as $g) {
            $gid = (int) $g->id;
            $buckets[$gid] = [
                'good_id' => $gid,
                'article' => (string) ($g->article_code ?? ''),
                'name' => (string) $g->name,
                'category' => trim((string) ($g->category ?? '')),
                'stock_qty' => (float) ($stockTotalsByGoodId[$gid] ?? 0.0),
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
     * Движения по одному товару (все склады филиала), по убыванию даты документа.
     *
     * @param  ?int  $limit  null — без ограничения (перед фильтрацией по периоду)
     * @return Collection<int, array{
     *     date: string,
     *     date_human: string,
     *     label: string,
     *     warehouse: string,
     *     quantity: float,
     *     direction: 'in'|'out'|'neutral',
     *     sort_key: string
     * }>
     */
    public function goodMovementLedgerForGood(int $branchId, int $goodId, ?int $limit = 200): Collection
    {
        $bucket = collect();

        $addRow = function (
            string $dateYmd,
            string $label,
            string $warehouse,
            float $quantity,
            string $direction,
            string $sortPrefix,
            int $sortTail
        ) use ($bucket): void {
            $dt = Carbon::parse($dateYmd);
            $bucket->push([
                'date' => $dateYmd,
                'date_human' => $dt->format('d.m.Y'),
                'label' => $label,
                'warehouse' => $warehouse,
                'quantity' => round($quantity, 4),
                'direction' => $direction,
                'sort_key' => $dateYmd.'|'.$sortPrefix.'|'.str_pad((string) $sortTail, 12, '0', STR_PAD_LEFT),
            ]);
        };

        foreach (
            OpeningStockBalance::query()
                ->where('branch_id', $branchId)
                ->where('good_id', $goodId)
                ->with('warehouse')
                ->orderBy('id')
                ->get() as $ob
        ) {
            $d = $ob->created_at !== null ? $ob->created_at->format('Y-m-d') : now()->format('Y-m-d');
            $addRow(
                $d,
                'Ввод начального остатка',
                $ob->warehouse?->name ?? '—',
                abs((float) $ob->quantity),
                'in',
                'ob',
                (int) $ob->id
            );
        }

        PurchaseReceiptLine::query()
            ->where('good_id', $goodId)
            ->whereHas('purchaseReceipt', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['purchaseReceipt.warehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (PurchaseReceiptLine $line) use ($addRow): void {
                $doc = $line->purchaseReceipt;
                if ($doc === null) {
                    return;
                }
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Поступление от поставщика',
                    $doc->warehouse?->name ?? '—',
                    (float) $line->quantity,
                    'in',
                    'prl',
                    (int) $line->id
                );
            });

        PurchaseReturnLine::query()
            ->where('good_id', $goodId)
            ->whereHas('purchaseReturn', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['purchaseReturn.warehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (PurchaseReturnLine $line) use ($addRow): void {
                $doc = $line->purchaseReturn;
                if ($doc === null) {
                    return;
                }
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Возврат поставщику',
                    $doc->warehouse?->name ?? '—',
                    (float) $line->quantity,
                    'out',
                    'prtl',
                    (int) $line->id
                );
            });

        RetailSaleLine::query()
            ->where('good_id', $goodId)
            ->whereHas('retailSale', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['retailSale.warehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (RetailSaleLine $line) use ($addRow): void {
                $doc = $line->retailSale;
                if ($doc === null) {
                    return;
                }
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Розничная продажа',
                    $doc->warehouse?->name ?? '—',
                    (float) $line->quantity,
                    'out',
                    'rsl',
                    (int) $line->id
                );
            });

        LegalEntitySaleLine::query()
            ->where('good_id', $goodId)
            ->whereHas('legalEntitySale', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['legalEntitySale.warehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (LegalEntitySaleLine $line) use ($addRow): void {
                $doc = $line->legalEntitySale;
                if ($doc === null) {
                    return;
                }
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Продажа юрлицу',
                    $doc->warehouse?->name ?? '—',
                    (float) $line->quantity,
                    'out',
                    'lel',
                    (int) $line->id
                );
            });

        CustomerReturnLine::query()
            ->where('good_id', $goodId)
            ->whereHas('customerReturn', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['customerReturn.warehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (CustomerReturnLine $line) use ($addRow): void {
                $doc = $line->customerReturn;
                if ($doc === null) {
                    return;
                }
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Возврат от покупателя',
                    $doc->warehouse?->name ?? '—',
                    (float) $line->quantity,
                    'in',
                    'crl',
                    (int) $line->id
                );
            });

        StockWriteoffLine::query()
            ->where('good_id', $goodId)
            ->whereHas('stockWriteoff', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['stockWriteoff.warehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (StockWriteoffLine $line) use ($addRow): void {
                $doc = $line->stockWriteoff;
                if ($doc === null) {
                    return;
                }
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Списание',
                    $doc->warehouse?->name ?? '—',
                    (float) $line->quantity,
                    'out',
                    'swl',
                    (int) $line->id
                );
            });

        StockSurplusLine::query()
            ->where('good_id', $goodId)
            ->whereHas('stockSurplus', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['stockSurplus.warehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (StockSurplusLine $line) use ($addRow): void {
                $doc = $line->stockSurplus;
                if ($doc === null) {
                    return;
                }
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Оприходование излишков',
                    $doc->warehouse?->name ?? '—',
                    (float) $line->quantity,
                    'in',
                    'ssl',
                    (int) $line->id
                );
            });

        StockTransferLine::query()
            ->where('good_id', $goodId)
            ->whereHas('stockTransfer', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['stockTransfer.fromWarehouse', 'stockTransfer.toWarehouse'])
            ->orderBy('id')
            ->get()
            ->each(function (StockTransferLine $line) use ($addRow): void {
                $doc = $line->stockTransfer;
                if ($doc === null) {
                    return;
                }
                $from = $doc->fromWarehouse?->name ?? '—';
                $to = $doc->toWarehouse?->name ?? '—';
                $addRow(
                    $doc->document_date->format('Y-m-d'),
                    'Перемещение: '.$from.' → '.$to,
                    $from.' → '.$to,
                    (float) $line->quantity,
                    'neutral',
                    'stl',
                    (int) $line->id
                );
            });

        $sorted = $bucket->sortByDesc('sort_key')->values();
        if ($limit !== null) {
            $sorted = $sorted->take($limit);
        }

        return $sorted->map(function (array $r): array {
            unset($r['sort_key']);

            return $r;
        })->values();
    }

    /**
     * Строки журнала движений по товару за период, с фильтром по складу как в отчёте («все склады» или один).
     * Сортировка: новее сверху (как goodMovementLedgerForGood).
     *
     * @return Collection<int, array{
     *     date: string,
     *     date_human: string,
     *     label: string,
     *     warehouse: string,
     *     quantity: float,
     *     direction: 'in'|'out'|'neutral'
     * }>
     */
    public function goodMovementLedgerForGoodInPeriod(
        int $branchId,
        int $goodId,
        CarbonInterface $from,
        CarbonInterface $to,
        int $warehouseId,
        int $maxRows = 400
    ): Collection {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $whName = '';
        if ($warehouseId > 0) {
            $whName = trim((string) Warehouse::query()
                ->where('branch_id', $branchId)
                ->whereKey($warehouseId)
                ->value('name'));
        }

        return $this->goodMovementLedgerForGood($branchId, $goodId, null)
            ->filter(function (array $r) use ($fromStr, $toStr, $whName): bool {
                $d = (string) ($r['date'] ?? '');
                if ($d === '' || $d < $fromStr || $d > $toStr) {
                    return false;
                }
                if ($whName === '') {
                    return true;
                }
                $w = (string) ($r['warehouse'] ?? '');

                return $w === $whName || str_contains($w, $whName);
            })
            ->take($maxRows)
            ->values();
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
     * Сводный отчёт «чистая прибыль» за период: денежные поступления минус выплаты по выбранным статьям.
     *
     * — Приход от покупателя / прочие приходы / расход поставщику / прочие расходы — из журнала «Банк и касса» (дата операции).
     * — Прочие расходы без выплат зарплаты (категория «Зарплата» учитывается отдельной строкой).
     * — Розница: оплаты по чекам за период и возвраты покупателю (денежная выплата по возврату) по дате документа возврата — отдельные строки в отчёте, в итоге считаются как оплаты минус возвраты.
     * — Авансы: суммы записей модуля «Авансы» по дате записи (отдельный учёт от проводок кассы).
     * Переводы между счетами не включаются.
     *
     * @return array{
     *     income_client: float,
     *     income_other: float,
     *     expense_supplier: float,
     *     expense_other: float,
     *     retail_payments: float,
     *     retail_refunds: float,
     *     retail_net: float,
     *     salary_payouts: float,
     *     employee_advances: float,
     *     payroll_advances_total: float,
     *     net_profit: float
     * }
     */
    public function netProfitCashSummary(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $incomeClient = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $incomeOther = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $expenseSupplier = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $salaryPayouts = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->whereRaw("TRIM(COALESCE(expense_category, '')) = ?", ['Зарплата'])
            ->sum('amount');

        $expenseOther = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->whereRaw("TRIM(COALESCE(expense_category, '')) != ?", ['Зарплата'])
            ->sum('amount');

        $retailPayments = (float) RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('rs.document_date', '>=', $fromStr)
            ->whereDate('rs.document_date', '<=', $toStr)
            ->sum('retail_sale_payments.amount');

        $retailRefunds = (float) RetailSaleRefund::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'retail_sale_refunds.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->whereDate('cr.document_date', '>=', $fromStr)
            ->whereDate('cr.document_date', '<=', $toStr)
            ->sum('retail_sale_refunds.amount');

        $retailNet = round($retailPayments - $retailRefunds, 2);

        $employeeAdvances = (float) EmployeeAdvance::query()
            ->where('branch_id', $branchId)
            ->whereDate('entry_date', '>=', $fromStr)
            ->whereDate('entry_date', '<=', $toStr)
            ->sum('amount');

        $payrollAdvancesTotal = round($salaryPayouts + $employeeAdvances, 2);

        $netProfit = round(
            $incomeClient + $incomeOther + $retailNet - $expenseSupplier - $expenseOther - $salaryPayouts - $employeeAdvances,
            2
        );

        return [
            'income_client' => round($incomeClient, 2),
            'income_other' => round($incomeOther, 2),
            'expense_supplier' => round($expenseSupplier, 2),
            'expense_other' => round($expenseOther, 2),
            'retail_payments' => round($retailPayments, 2),
            'retail_refunds' => round($retailRefunds, 2),
            'retail_net' => $retailNet,
            'salary_payouts' => round($salaryPayouts, 2),
            'employee_advances' => round($employeeAdvances, 2),
            'payroll_advances_total' => $payrollAdvancesTotal,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Детальные строки для модального окна отчёта «Чистая прибыль».
     *
     * @return array{
     *     title: string,
     *     period_label: string,
     *     columns: list<array{key: string, label: string}>,
     *     rows: list<array<string, string>>,
     *     total: float,
     *     total_formatted: string,
     *     empty_message: string
     * }
     */
    public function netProfitCashDetail(int $branchId, CarbonInterface $from, CarbonInterface $to, string $kind): array
    {
        $allowed = [
            'income_client',
            'income_other',
            'expense_supplier',
            'expense_other',
            'retail_payments',
            'retail_refunds',
            'salary_payouts',
            'employee_advances',
            'payroll_advances',
            'net_profit',
        ];
        if (! in_array($kind, $allowed, true)) {
            throw new InvalidArgumentException('Неизвестная статья отчёта.');
        }

        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $periodLabel = $from->format('d.m.Y').' — '.$to->format('d.m.Y');

        return match ($kind) {
            'income_client' => $this->netProfitDetailIncomeClient($branchId, $fromStr, $toStr, $periodLabel),
            'income_other' => $this->netProfitDetailIncomeOther($branchId, $fromStr, $toStr, $periodLabel),
            'expense_supplier' => $this->netProfitDetailExpenseSupplier($branchId, $fromStr, $toStr, $periodLabel),
            'expense_other' => $this->netProfitDetailExpenseOther($branchId, $fromStr, $toStr, $periodLabel),
            'retail_payments' => $this->netProfitDetailRetailPayments($branchId, $fromStr, $toStr, $periodLabel),
            'retail_refunds' => $this->netProfitDetailRetailRefunds($branchId, $fromStr, $toStr, $periodLabel),
            'salary_payouts' => $this->netProfitDetailSalary($branchId, $fromStr, $toStr, $periodLabel),
            'employee_advances' => $this->netProfitDetailEmployeeAdvances($branchId, $fromStr, $toStr, $periodLabel),
            'payroll_advances' => $this->netProfitDetailPayrollCombined($branchId, $fromStr, $toStr, $periodLabel),
            'net_profit' => $this->netProfitDetailNetProfitComposition($branchId, $from, $to, $periodLabel),
        };
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailIncomeClient(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $movements = $this->netProfitFetchCashMovements($branchId, $fromStr, $toStr, CashMovement::KIND_INCOME_CLIENT);

        return $this->netProfitDetailPackCash(
            'Приход от покупателя',
            $periodLabel,
            $movements,
            (float) $movements->sum('amount'),
            '+'
        );
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailIncomeOther(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $movements = $this->netProfitFetchCashMovements($branchId, $fromStr, $toStr, CashMovement::KIND_INCOME_OTHER);

        return $this->netProfitDetailPackCash(
            'Приход прочие',
            $periodLabel,
            $movements,
            (float) $movements->sum('amount'),
            '+'
        );
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailExpenseSupplier(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $movements = $this->netProfitFetchCashMovements($branchId, $fromStr, $toStr, CashMovement::KIND_EXPENSE_SUPPLIER);

        return $this->netProfitDetailPackCash(
            'Расход поставщику',
            $periodLabel,
            $movements,
            (float) $movements->sum('amount'),
            '−'
        );
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailExpenseOther(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->whereRaw("TRIM(COALESCE(expense_category, '')) != ?", ['Зарплата'])
            ->with(['counterparty', 'ourAccount'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        return $this->netProfitDetailPackCash(
            'Прочие расходы (без зарплаты)',
            $periodLabel,
            $movements,
            (float) $movements->sum('amount'),
            '−'
        );
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailSalary(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->whereRaw("TRIM(COALESCE(expense_category, '')) = ?", ['Зарплата'])
            ->with(['counterparty', 'ourAccount'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        return $this->netProfitDetailPackCash(
            'Выплаты зарплаты',
            $periodLabel,
            $movements,
            (float) $movements->sum('amount'),
            '−'
        );
    }

    /**
     * @return EloquentCollection<int, CashMovement>
     */
    private function netProfitFetchCashMovements(int $branchId, string $fromStr, string $toStr, string $movementKind): EloquentCollection
    {
        return CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', $movementKind)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->with(['counterparty', 'ourAccount'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, CashMovement>  $movements
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailPackCash(string $title, string $periodLabel, EloquentCollection $movements, float $total, string $signPrefix): array
    {
        $rows = [];
        foreach ($movements as $m) {
            $cat = trim((string) ($m->expense_category ?? ''));
            $comment = trim((string) ($m->comment ?? ''));
            $detail = '—';
            if ($cat !== '' && $comment !== '') {
                $detail = $cat.' · '.$comment;
            } elseif ($cat !== '') {
                $detail = $cat;
            } elseif ($comment !== '') {
                $detail = $comment;
            }

            $rows[] = [
                'date' => $m->occurred_on->format('d.m.Y'),
                'amount' => InvoiceNakladnayaFormatter::formatMoney((float) $m->amount),
                'account' => $m->ourAccount?->movementReportLabel() ?? '—',
                'counterparty' => trim((string) ($m->counterparty?->name ?? '')) !== ''
                    ? trim((string) $m->counterparty->name)
                    : '—',
                'detail' => $detail,
            ];
        }

        return [
            'title' => $title,
            'period_label' => $periodLabel,
            'columns' => [
                ['key' => 'date', 'label' => 'Дата'],
                ['key' => 'amount', 'label' => 'Сумма'],
                ['key' => 'account', 'label' => 'Счёт'],
                ['key' => 'counterparty', 'label' => 'Контрагент'],
                ['key' => 'detail', 'label' => 'Комментарий'],
            ],
            'rows' => $rows,
            'total' => round($total, 2),
            'total_formatted' => trim($signPrefix.' '.InvoiceNakladnayaFormatter::formatMoney($total)),
            'empty_message' => 'Нет операций за выбранный период.',
        ];
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailRetailPayments(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $payments = RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('rs.document_date', '>=', $fromStr)
            ->whereDate('rs.document_date', '<=', $toStr)
            ->with(['organizationBankAccount'])
            ->orderByDesc('rs.document_date')
            ->orderByDesc('rs.id')
            ->orderByDesc('retail_sale_payments.id')
            ->select('retail_sale_payments.*', 'rs.document_date as sale_document_date', 'rs.id as sale_id')
            ->get();

        $total = (float) $payments->sum('amount');
        $rows = [];
        foreach ($payments as $p) {
            $acc = $p->organizationBankAccount;
            $saleDate = $p->getAttribute('sale_document_date');
            if ($saleDate !== null && ! $saleDate instanceof CarbonInterface) {
                $saleDate = Carbon::parse((string) $saleDate);
            }
            $saleDate = $saleDate instanceof CarbonInterface ? $saleDate : ($p->retailSale?->document_date);

            $saleId = (int) ($p->getAttribute('sale_id') ?? $p->retail_sale_id);
            $rows[] = [
                'date' => $saleDate instanceof CarbonInterface ? $saleDate->format('d.m.Y') : '—',
                'amount' => InvoiceNakladnayaFormatter::formatMoney((float) $p->amount),
                'sale_id' => (string) $saleId,
                'account' => $acc?->movementReportLabel() ?? '—',
            ];
        }

        return [
            'title' => 'Оплаты по розничным чекам',
            'period_label' => $periodLabel,
            'columns' => [
                ['key' => 'date', 'label' => 'Дата чека'],
                ['key' => 'amount', 'label' => 'Сумма'],
                ['key' => 'sale_id', 'label' => 'Чек №'],
                ['key' => 'account', 'label' => 'Счёт'],
            ],
            'rows' => $rows,
            'total' => round($total, 2),
            'total_formatted' => '+ '.InvoiceNakladnayaFormatter::formatMoney($total),
            'empty_message' => 'Нет оплат за выбранный период.',
        ];
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailRetailRefunds(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $refunds = RetailSaleRefund::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'retail_sale_refunds.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->whereDate('cr.document_date', '>=', $fromStr)
            ->whereDate('cr.document_date', '<=', $toStr)
            ->with(['organizationBankAccount'])
            ->orderByDesc('cr.document_date')
            ->orderByDesc('cr.id')
            ->orderByDesc('retail_sale_refunds.id')
            ->select(
                'retail_sale_refunds.*',
                'cr.document_date as return_document_date',
                'cr.id as customer_return_id'
            )
            ->get();

        $total = (float) $refunds->sum('amount');
        $rows = [];
        foreach ($refunds as $r) {
            $acc = $r->organizationBankAccount;
            $d = $r->getAttribute('return_document_date');
            if ($d !== null && ! $d instanceof CarbonInterface) {
                $d = Carbon::parse((string) $d);
            }
            $d = $d instanceof CarbonInterface ? $d : ($r->customerReturn?->document_date);
            $rows[] = [
                'date' => $d instanceof CarbonInterface ? $d->format('d.m.Y') : '—',
                'amount' => InvoiceNakladnayaFormatter::formatMoney((float) $r->amount),
                'return_id' => (string) (int) ($r->getAttribute('customer_return_id') ?? $r->customer_return_id),
                'sale_id' => (string) (int) $r->retail_sale_id,
                'account' => $acc?->movementReportLabel() ?? '—',
            ];
        }

        return [
            'title' => 'Возвраты покупателю (розница)',
            'period_label' => $periodLabel,
            'columns' => [
                ['key' => 'date', 'label' => 'Дата возврата'],
                ['key' => 'amount', 'label' => 'Сумма'],
                ['key' => 'return_id', 'label' => 'Возврат №'],
                ['key' => 'sale_id', 'label' => 'Чек №'],
                ['key' => 'account', 'label' => 'Счёт'],
            ],
            'rows' => $rows,
            'total' => round($total, 2),
            'total_formatted' => '− '.InvoiceNakladnayaFormatter::formatMoney($total),
            'empty_message' => 'Нет возвратов за выбранный период.',
        ];
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailEmployeeAdvances(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $advances = EmployeeAdvance::query()
            ->where('branch_id', $branchId)
            ->whereDate('entry_date', '>=', $fromStr)
            ->whereDate('entry_date', '<=', $toStr)
            ->with('employee')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $total = (float) $advances->sum('amount');
        $rows = [];
        foreach ($advances as $a) {
            $note = trim((string) ($a->note ?? ''));
            $rows[] = [
                'date' => $a->entry_date->format('d.m.Y'),
                'amount' => InvoiceNakladnayaFormatter::formatMoney((float) $a->amount),
                'employee' => trim((string) ($a->employee?->full_name ?? '')) !== ''
                    ? trim((string) $a->employee->full_name)
                    : '—',
                'detail' => $note !== '' ? mb_substr($note, 0, 500) : '—',
            ];
        }

        return [
            'title' => 'Авансы сотрудникам',
            'period_label' => $periodLabel,
            'columns' => [
                ['key' => 'date', 'label' => 'Дата'],
                ['key' => 'amount', 'label' => 'Сумма'],
                ['key' => 'employee', 'label' => 'Сотрудник'],
                ['key' => 'detail', 'label' => 'Примечание'],
            ],
            'rows' => $rows,
            'total' => round($total, 2),
            'total_formatted' => '− '.InvoiceNakladnayaFormatter::formatMoney($total),
            'empty_message' => 'Нет записей за выбранный период.',
        ];
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailPayrollCombined(int $branchId, string $fromStr, string $toStr, string $periodLabel): array
    {
        $salaryMovements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->whereRaw("TRIM(COALESCE(expense_category, '')) = ?", ['Зарплата'])
            ->with(['ourAccount'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $advances = EmployeeAdvance::query()
            ->where('branch_id', $branchId)
            ->whereDate('entry_date', '>=', $fromStr)
            ->whereDate('entry_date', '<=', $toStr)
            ->with('employee')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $merged = [];
        foreach ($salaryMovements as $m) {
            $comment = trim((string) ($m->comment ?? ''));
            $merged[] = [
                '_sort' => $m->occurred_on->copy()->startOfDay()->timestamp * 1000 + $m->id,
                'date' => $m->occurred_on->format('d.m.Y'),
                'kind_label' => 'Зарплата',
                'amount' => InvoiceNakladnayaFormatter::formatMoney((float) $m->amount),
                'account' => $m->ourAccount?->movementReportLabel() ?? '—',
                'detail' => $comment !== '' ? $comment : '—',
            ];
        }
        foreach ($advances as $a) {
            $note = trim((string) ($a->note ?? ''));
            $emp = trim((string) ($a->employee?->full_name ?? ''));
            $merged[] = [
                '_sort' => $a->entry_date->copy()->startOfDay()->timestamp * 1000 + 500000000 + $a->id,
                'date' => $a->entry_date->format('d.m.Y'),
                'kind_label' => 'Аванс',
                'amount' => InvoiceNakladnayaFormatter::formatMoney((float) $a->amount),
                'account' => '—',
                'detail' => $emp !== '' ? ($note !== '' ? $emp.' · '.$note : $emp) : ($note !== '' ? $note : '—'),
            ];
        }

        usort($merged, fn (array $x, array $y): int => ($y['_sort'] ?? 0) <=> ($x['_sort'] ?? 0));
        $rows = array_map(function (array $r): array {
            unset($r['_sort']);

            return $r;
        }, $merged);

        $totalSalary = (float) $salaryMovements->sum('amount');
        $totalAdv = (float) $advances->sum('amount');
        $total = round($totalSalary + $totalAdv, 2);

        return [
            'title' => 'Зарплата и авансы',
            'period_label' => $periodLabel,
            'columns' => [
                ['key' => 'date', 'label' => 'Дата'],
                ['key' => 'kind_label', 'label' => 'Вид'],
                ['key' => 'amount', 'label' => 'Сумма'],
                ['key' => 'account', 'label' => 'Счёт'],
                ['key' => 'detail', 'label' => 'Детали'],
            ],
            'rows' => $rows,
            'total' => $total,
            'total_formatted' => '− '.InvoiceNakladnayaFormatter::formatMoney($total),
            'empty_message' => 'Нет выплат и авансов за выбранный период.',
        ];
    }

    /**
     * @return array{title: string, period_label: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>, total: float, total_formatted: string, empty_message: string}
     */
    private function netProfitDetailNetProfitComposition(int $branchId, CarbonInterface $from, CarbonInterface $to, string $periodLabel): array
    {
        $s = $this->netProfitCashSummary($branchId, $from, $to);
        $fmt = fn (float $v): string => InvoiceNakladnayaFormatter::formatMoney($v);

        $rows = [
            ['article' => 'Приход от покупателя', 'flow' => '+ '.$fmt($s['income_client'])],
            ['article' => 'Приход прочие', 'flow' => '+ '.$fmt($s['income_other'])],
            ['article' => 'Расход поставщику', 'flow' => '− '.$fmt($s['expense_supplier'])],
            ['article' => 'Прочие расходы (без зарплаты)', 'flow' => '− '.$fmt($s['expense_other'])],
            ['article' => 'Оплаты по розничным чекам', 'flow' => '+ '.$fmt($s['retail_payments'])],
            ['article' => 'Возвраты покупателю (розница)', 'flow' => '− '.$fmt($s['retail_refunds'])],
            ['article' => 'Зарплата и авансы', 'flow' => '− '.$fmt($s['payroll_advances_total'])],
            ['article' => 'Чистая прибыль', 'flow' => ($s['net_profit'] >= 0 ? '+ ' : '− ').$fmt(abs($s['net_profit']))],
        ];

        return [
            'title' => 'Состав чистой прибыли',
            'period_label' => $periodLabel,
            'columns' => [
                ['key' => 'article', 'label' => 'Статья'],
                ['key' => 'flow', 'label' => 'В расчёте'],
            ],
            'rows' => $rows,
            'total' => round((float) $s['net_profit'], 2),
            'total_formatted' => InvoiceNakladnayaFormatter::formatMoney((float) $s['net_profit']),
            'empty_message' => 'Нет данных.',
        ];
    }

    /**
     * Полная управленческая ОСВ (стиль бухгалтерского отчёта): счета по блокам —
     * деньги, товары, расчёты, доходы и расходы; сальдо начала/конца и обороты там, где данные есть.
     *
     * @return array{
     *   sections: list<array<string, mixed>>,
     *   currency_codes: list<string>,
     *   dashboard: array{closing: array<string, float>, turnover: array{debit: float, credit: float}}
     * }
     */
    public function fullTurnoverOsv(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $cashBlock = $this->cashTurnoverOsv($branchId, $from, $to);
        $resolveCost = $this->buildCostResolverForBranch($branchId);
        $gp = $this->grossProfit($branchId, $from, $to);
        $cogs = round((float) $gp['cost'], 2);

        $onBeforePeriod = $from->copy()->subDay()->startOfDay();
        $onEndPeriod = $to->copy()->startOfDay();

        $invOpen = $this->inventoryValuationAtBoundary($branchId, $onBeforePeriod);
        $invClose = $this->inventoryValuationAtBoundary($branchId, $onEndPeriod);

        $purchaseIn = (float) (PurchaseReceiptLine::query()
            ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>=', $fromStr)
            ->whereDate('pr.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('purchase_receipt_lines.line_sum'));

        $purchaseRetOut = (float) (PurchaseReturnLine::query()
            ->join('purchase_returns as pr', 'pr.id', '=', 'purchase_return_lines.purchase_return_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>=', $fromStr)
            ->whereDate('pr.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('purchase_return_lines.line_sum'));

        $customerReturnInCost = $this->sumCustomerReturnStockCostForPeriod($branchId, $fromStr, $toStr, $resolveCost);

        $writeoffCost = $this->sumStockWriteoffCostForPeriod($branchId, $fromStr, $toStr, $resolveCost);

        $surplusDebit = $this->sumStockSurplusCostForPeriod($branchId, $fromStr, $toStr, $resolveCost);

        $costTransferOutIn = $this->sumStockTransferCostsForPeriod($branchId, $fromStr, $toStr, $resolveCost);

        $invDebitTurnover = round(
            $purchaseIn + $customerReturnInCost + $surplusDebit + ($costTransferOutIn['in'] ?? 0.0),
            2
        );
        $invCreditTurnover = round(
            $cogs + $purchaseRetOut + $writeoffCost + ($costTransferOutIn['out'] ?? 0.0),
            2
        );

        $openAp = round((float) Counterparty::query()->where('branch_id', $branchId)->sum('opening_debt_as_supplier'), 2);
        $openArRef = round((float) Counterparty::query()->where('branch_id', $branchId)->sum('opening_debt_as_buyer'), 2);

        $paymentsToSuppliers = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $paymentsFromClientsTracked = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereNotNull('counterparty_id')
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $legalTurnoverDebit = round((float) LegalEntitySaleLine::query()
            ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
            ->where('ls.branch_id', $branchId)
            ->whereDate('ls.document_date', '>=', $fromStr)
            ->whereDate('ls.document_date', '<=', $toStr)
            ->sum('legal_entity_sale_lines.line_sum'), 2);

        $closingApNet = round($openAp + $purchaseIn - $paymentsToSuppliers, 2);

        $closingArRef = round($openArRef + $legalTurnoverDebit - $paymentsFromClientsTracked, 2);

        $fromStart = $from->copy()->startOfDay();
        $retailDebtOpen = $this->retailDebtOutstandingAtMoment($branchId, $fromStart, false);
        $retailDebtClose = $this->retailDebtOutstandingAtMoment($branchId, $to->copy()->startOfDay(), true);
        $retailDebtDebitTurn = $this->retailDebtIssuedInPeriod($branchId, $fromStr, $toStr);
        $retailDebtCreditTurn = $this->retailDebtRepaidAfterCheckoutInPeriod($branchId, $fromStr, $toStr);

        $rdOpenSides = $this->assetPositiveDebitSaldoSides($retailDebtOpen);
        $rdCloseSides = $this->assetPositiveDebitSaldoSides($retailDebtClose);

        $retailGoodsRev = (float) RetailSaleLine::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_lines.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('rs.document_date', '>=', $fromStr)
            ->whereDate('rs.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('retail_sale_lines.line_sum');

        $retailServicesRev = (float) RetailSaleLine::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_lines.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('rs.document_date', '>=', $fromStr)
            ->whereDate('rs.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', true))
            ->sum('retail_sale_lines.line_sum');

        $legalGoodsRev = (float) LegalEntitySaleLine::query()
            ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
            ->where('ls.branch_id', $branchId)
            ->whereDate('ls.document_date', '>=', $fromStr)
            ->whereDate('ls.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('legal_entity_sale_lines.line_sum');

        $legalServicesRev = (float) LegalEntitySaleLine::query()
            ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
            ->where('ls.branch_id', $branchId)
            ->whereDate('ls.document_date', '>=', $fromStr)
            ->whereDate('ls.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', true))
            ->sum('legal_entity_sale_lines.line_sum');

        $expenseOther = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $incomeOther = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $snippet = fn (float $snD, float $snC, float $toD, float $toC, float $skD, float $skC): array => [
            'sn_debit' => round($snD, 2),
            'sn_credit' => round($snC, 2),
            'to_debit' => round($toD, 2),
            'to_credit' => round($toC, 2),
            'sk_debit' => round($skD, 2),
            'sk_credit' => round($skC, 2),
        ];

        $apSn = $this->liabilityPositiveCreditToSides($openAp);
        $apSk = $this->liabilityPositiveCreditToSides($closingApNet);

        $arRefSn = $this->assetPositiveDebitSaldoSides($openArRef);
        $arRefSk = $this->assetPositiveDebitSaldoSides($closingArRef);

        $syntheticSections = [];

        $syntheticSections[] = [
            'key' => 'inventory',
            'title' => '2. Товары и запасы',
            'mode' => 'flat',
            'accounts' => [
                array_merge($snippet($invOpen, 0.0, $invDebitTurnover, $invCreditTurnover, $invClose, 0.0), [
                    'id' => -1310,
                    'register_code' => '1310',
                    'account_label' => 'Материальные запасы (товары на складах)',
                    'kind' => 'stock',
                    'currency' => '',
                ]),
            ],
        ];

        $syntheticSections[] = [
            'key' => 'debts',
            'title' => '3. Расчёты с контрагентами',
            'mode' => 'flat',
            'accounts' => [
                array_merge($snippet($apSn['debit'], $apSn['credit'], $paymentsToSuppliers, $purchaseIn, $apSk['debit'], $apSk['credit']), [
                    'id' => -3310,
                    'register_code' => '3310',
                    'account_label' => 'Задолженность поставщикам (+ закупки за период − оплаты из «Банк и касса»)',
                    'kind' => 'payable',
                    'currency' => '',
                    'balance_kind' => 'passive',
                ]),
                array_merge($snippet($arRefSn['debit'], $arRefSn['credit'], $legalTurnoverDebit, $paymentsFromClientsTracked, $arRefSk['debit'], $arRefSk['credit']), [
                    'id' => -4110,
                    'register_code' => '4110',
                    'account_label' => 'Дебиторская задолженность покупателей (контрагенты из справочника + счета юрлиц за период)',
                    'kind' => 'receivable_ref',
                    'currency' => '',
                ]),
                array_merge($snippet($rdOpenSides['debit'], $rdOpenSides['credit'], $retailDebtDebitTurn, $retailDebtCreditTurn, $rdCloseSides['debit'], $rdCloseSides['credit']), [
                    'id' => -4210,
                    'register_code' => '4210',
                    'account_label' => 'Розничная задолженность (отсрочка в чеках: выдача в дебет оборота, оплату долга после чека — в кредит)',
                    'kind' => 'receivable_retail',
                    'currency' => '',
                ]),
            ],
        ];

        $syntheticSections[] = [
            'key' => 'income',
            'title' => '4. Доходы за период',
            'mode' => 'flat',
            'accounts' => [
                $this->pnlCreditRow(-6010, '6010', 'Выручка розницы — товары', round($retailGoodsRev, 2)),
                $this->pnlCreditRow(-6011, '6011', 'Выручка розницы — услуги', round($retailServicesRev, 2)),
                $this->pnlCreditRow(-6110, '6110', 'Выручка от оптовых продаж (юрлица) — товары', round($legalGoodsRev, 2)),
                $this->pnlCreditRow(-6120, '6120', 'Выручка от оптовых продаж (юрлица) — услуги', round($legalServicesRev, 2)),
                $this->pnlCreditRow(-6030, '6030', 'Прочие поступления («Банк и касса» — приход прочее / займы)', round($incomeOther, 2)),
            ],
        ];

        $syntheticSections[] = [
            'key' => 'expense',
            'title' => '5. Расходы за период',
            'mode' => 'flat',
            'accounts' => [
                $this->pnlDebitRow(-7110, '7110', 'Себестоимость проданных товаров (по учётной цене)', $cogs),
                $this->pnlDebitRow(-7510, '7510', 'Прочие расходы («Банк и касса» — прочий расход)', round($expenseOther, 2)),
            ],
        ];

        $totalIncomeForProfit = round(
            (float) $retailGoodsRev + (float) $retailServicesRev + (float) $legalGoodsRev + (float) $legalServicesRev + (float) $incomeOther,
            2
        );
        $totalExpenseForProfit = round((float) $cogs + (float) $expenseOther, 2);
        $profitNet = round($totalIncomeForProfit - $totalExpenseForProfit, 2);

        $syntheticSections[] = [
            'key' => 'period_result',
            'title' => '6. Финансовый результат (управленчески)',
            'mode' => 'flat',
            'accounts' => [
                array_merge(
                    $snippet(
                        0.0,
                        0.0,
                        $profitNet < 0 ? abs($profitNet) : 0.0,
                        $profitNet > 0 ? $profitNet : 0.0,
                        0.0,
                        0.0
                    ),
                    [
                        'id' => -9910,
                        'register_code' => '9910',
                        'account_label' => 'Доходы разд. 4 − расходы разд. 5 (без налогов и полного БУ)',
                        'kind' => 'period_profit',
                        'currency' => '',
                    ]
                ),
            ],
        ];

        $sections = array_merge(
            [[
                'key' => 'money',
                'title' => '1. Денежные средства',
                'mode' => 'money',
                'groups' => $cashBlock['groups'],
                'grand' => $cashBlock['grand'],
            ]],
            $syntheticSections
        );

        $dashboard = $this->buildTurnoverOsvDashboard(
            $sections,
            round($invClose, 2),
            round($closingArRef + $retailDebtClose, 2),
            round($closingApNet, 2)
        );

        return [
            'sections' => $sections,
            'currency_codes' => $cashBlock['currency_codes'],
            'dashboard' => $dashboard,
        ];
    }

    /**
     * Сводка для шапки ОСВ: сальдо на конец по ключевым показателям, обороты за период.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function buildTurnoverOsvDashboard(
        array $sections,
        float $inventoryClosing,
        float $receivablesNet,
        float $payablesNet
    ): array {
        $cashClosingNet = 0.0;
        $bankClosingNet = 0.0;
        $moneySec = $sections[0] ?? [];
        if (($moneySec['mode'] ?? '') === 'money') {
            foreach ($moneySec['groups'] ?? [] as $g) {
                foreach ($g['accounts'] ?? [] as $r) {
                    $net = (float) $r['sk_debit'] - (float) $r['sk_credit'];
                    if (($r['kind'] ?? '') === 'cash') {
                        $cashClosingNet += $net;
                    } elseif (($r['kind'] ?? '') === 'bank') {
                        $bankClosingNet += $net;
                    }
                }
            }
        }
        $cashClosingNet = round($cashClosingNet, 2);
        $bankClosingNet = round($bankClosingNet, 2);

        $rows = $this->flattenOsvAccountRows($sections);
        $sums = $this->sumOsvNumericColumns($rows);

        return [
            'closing' => [
                'cash' => $cashClosingNet,
                'bank' => $bankClosingNet,
                'money_total' => round($cashClosingNet + $bankClosingNet, 2),
                'receivables' => $receivablesNet,
                'payables' => $payablesNet,
                'inventory' => $inventoryClosing,
            ],
            'turnover' => [
                'debit' => (float) $sums['to_debit'],
                'credit' => (float) $sums['to_credit'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function flattenOsvAccountRows(array $sections): array
    {
        $out = [];
        foreach ($sections as $sec) {
            if (($sec['mode'] ?? '') === 'money') {
                foreach ($sec['groups'] ?? [] as $g) {
                    foreach ($g['accounts'] ?? [] as $row) {
                        $out[] = $row;
                    }
                }
            } else {
                foreach ($sec['accounts'] ?? [] as $row) {
                    $out[] = $row;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{sn_debit: float, sn_credit: float, to_debit: float, to_credit: float, sk_debit: float, sk_credit: float}
     */
    private function sumOsvNumericColumns(array $rows): array
    {
        $acc = [
            'sn_debit' => 0.0,
            'sn_credit' => 0.0,
            'to_debit' => 0.0,
            'to_credit' => 0.0,
            'sk_debit' => 0.0,
            'sk_credit' => 0.0,
        ];
        foreach ($rows as $r) {
            foreach (array_keys($acc) as $k) {
                $acc[$k] += (float) ($r[$k] ?? 0);
            }
        }
        foreach ($acc as $k => $v) {
            $acc[$k] = round($v, 2);
        }

        return $acc;
    }

    /**
     * Розничная дебиторка по чекам: сколько покупатель всё ещё должен (total − оплаты), на дату.
     *
     * @param  bool  $inclusiveEnd  false — платежи строго до начала $paymentsRecordedUpTo; true — включая конец календарного дня $paymentsRecordedUpTo
     */
    private function retailDebtOutstandingAtMoment(int $branchId, CarbonInterface $paymentsRecordedUpTo, bool $inclusiveEnd): float
    {
        $cutoff = $inclusiveEnd
            ? $paymentsRecordedUpTo->copy()->endOfDay()
            : $paymentsRecordedUpTo->copy()->startOfDay();
        $op = $inclusiveEnd ? '<=' : '<';

        $sales = RetailSale::query()
            ->where('branch_id', $branchId)
            ->withSum([
                'payments' => function ($q) use ($op, $cutoff) {
                    $q->where('created_at', $op, $cutoff);
                },
            ], 'amount')
            ->get(['id', 'total_amount']);

        return round($sales->sum(function (RetailSale $s): float {
            $paid = (float) ($s->payments_sum_amount ?? 0);
            $tot = (float) $s->total_amount;

            return max(0.0, round($tot - $paid, 2));
        }), 2);
    }

    /**
     * Сумма первоначальной отсрочки по чекам с датой продажи в интервале (в дебет оборота 4210).
     */
    private function retailDebtIssuedInPeriod(int $branchId, string $fromStr, string $toStr): float
    {
        $sales = RetailSale::query()
            ->where('branch_id', $branchId)
            ->whereDate('document_date', '>=', $fromStr)
            ->whereDate('document_date', '<=', $toStr)
            ->get(['id', 'debt_amount']);

        if ($sales->isEmpty()) {
            return 0.0;
        }

        $ids = $sales->pluck('id')->all();
        $extras = RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->whereIn('retail_sale_payments.retail_sale_id', $ids)
            ->whereColumn('retail_sale_payments.created_at', '>', 'rs.created_at')
            ->groupBy('retail_sale_payments.retail_sale_id')
            ->selectRaw('retail_sale_payments.retail_sale_id as sid, SUM(retail_sale_payments.amount) as extra')
            ->pluck('extra', 'sid');

        return round($sales->sum(function (RetailSale $s) use ($extras): float {
            $extra = (float) ($extras[$s->id] ?? 0.0);

            return max(0.0, round((float) $s->debt_amount + $extra, 2));
        }), 2);
    }

    /**
     * Оплаты долга после оформления чека (не счётная оплата при пробитии), в кредит оборота 4210.
     */
    private function retailDebtRepaidAfterCheckoutInPeriod(int $branchId, string $fromStr, string $toStr): float
    {
        return round((float) RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('retail_sale_payments.created_at', '>=', $fromStr)
            ->whereDate('retail_sale_payments.created_at', '<=', $toStr)
            ->whereColumn('retail_sale_payments.created_at', '>', 'rs.created_at')
            ->sum('retail_sale_payments.amount'), 2);
    }

    /**
     * @return array{debit: float, credit: float}
     */
    private function liabilityPositiveCreditToSides(float $netPayablePositive): array
    {
        if ($netPayablePositive >= 0) {
            return ['debit' => 0.0, 'credit' => round($netPayablePositive, 2)];
        }

        return ['debit' => round(-$netPayablePositive, 2), 'credit' => 0.0];
    }

    /**
     * @return array{debit: float, credit: float}
     */
    private function assetPositiveDebitSaldoSides(float $netReceivableDebitPositive): array
    {
        if ($netReceivableDebitPositive >= 0) {
            return ['debit' => round($netReceivableDebitPositive, 2), 'credit' => 0.0];
        }

        return ['debit' => 0.0, 'credit' => round(-$netReceivableDebitPositive, 2)];
    }

    /**
     * @return array<string, mixed>
     */
    private function pnlCreditRow(int $id, string $code, string $label, float $creditTurn): array
    {
        return [
            'id' => $id,
            'register_code' => $code,
            'account_label' => $label,
            'kind' => 'income',
            'currency' => '',
            'sn_debit' => 0.0,
            'sn_credit' => 0.0,
            'to_debit' => 0.0,
            'to_credit' => round($creditTurn, 2),
            'sk_debit' => 0.0,
            'sk_credit' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pnlDebitRow(int $id, string $code, string $label, float $debitTurn): array
    {
        return [
            'id' => $id,
            'register_code' => $code,
            'account_label' => $label,
            'kind' => 'expense',
            'currency' => '',
            'sn_debit' => 0.0,
            'sn_credit' => 0.0,
            'to_debit' => round($debitTurn, 2),
            'to_credit' => 0.0,
            'sk_debit' => 0.0,
            'sk_credit' => 0.0,
        ];
    }

    private function inventoryValuationAtBoundary(int $branchId, CarbonInterface $onExclusiveEndBoundary): float
    {
        $deltaMap = $this->netGoodsStockDeltaAfterDate($branchId, 0, $onExclusiveEndBoundary);

        return round((float) OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->get()
            ->reduce(function (float $carry, OpeningStockBalance $b) use ($deltaMap): float {
                $k = $b->good_id.'|'.$b->warehouse_id;
                $qtyNow = (float) $b->quantity;
                $dq = (float) ($deltaMap[$k] ?? 0);
                $qtyAt = round($qtyNow - $dq, 4);
                $uc = $b->unit_cost;

                return $carry + ($qtyAt > 0 && $uc !== null
                    ? round($qtyAt * (float) $uc, 2)
                    : 0.0);
            }, 0.0), 2);
    }

    /**
     * @return callable(int,int):float
     */
    private function buildCostResolverForBranch(int $branchId): callable
    {
        $costByGoodWarehouse = OpeningStockBalance::query()
            ->where('branch_id', $branchId)
            ->get()
            ->groupBy(fn (OpeningStockBalance $b) => $b->good_id.'_'.$b->warehouse_id);

        return function (int $goodId, int $warehouseId) use ($costByGoodWarehouse, $branchId): float {
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
    }

    /**
     * @param  callable(int,int):float  $resolveCost
     */
    private function sumCustomerReturnStockCostForPeriod(int $branchId, string $fromStr, string $toStr, callable $resolveCost): float
    {
        $sum = 0.0;
        CustomerReturnLine::query()
            ->join('customer_returns as cr', 'cr.id', '=', 'customer_return_lines.customer_return_id')
            ->where('cr.branch_id', $branchId)
            ->whereDate('cr.document_date', '>=', $fromStr)
            ->whereDate('cr.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->select(['customer_return_lines.good_id', 'customer_return_lines.quantity', 'cr.warehouse_id'])
            ->get()
            ->each(function ($r) use (&$sum, $resolveCost): void {
                $sum += round((float) $r->quantity * $resolveCost((int) $r->good_id, (int) $r->warehouse_id), 2);
            });

        return round($sum, 2);
    }

    /**
     * @param  callable(int,int):float  $resolveCost
     */
    private function sumStockWriteoffCostForPeriod(int $branchId, string $fromStr, string $toStr, callable $resolveCost): float
    {
        $sum = 0.0;
        StockWriteoffLine::query()
            ->join('stock_writeoffs as sw', 'sw.id', '=', 'stock_writeoff_lines.stock_writeoff_id')
            ->where('sw.branch_id', $branchId)
            ->whereDate('sw.document_date', '>=', $fromStr)
            ->whereDate('sw.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->select(['stock_writeoff_lines.good_id', 'stock_writeoff_lines.quantity', 'sw.warehouse_id'])
            ->get()
            ->each(function ($r) use (&$sum, $resolveCost): void {
                $sum += round((float) $r->quantity * $resolveCost((int) $r->good_id, (int) $r->warehouse_id), 2);
            });

        return round($sum, 2);
    }

    /**
     * @param  callable(int,int):float  $resolveCost
     */
    private function sumStockSurplusCostForPeriod(int $branchId, string $fromStr, string $toStr, callable $resolveCost): float
    {
        $sum = 0.0;
        StockSurplusLine::query()
            ->join('stock_surpluses as ss', 'ss.id', '=', 'stock_surplus_lines.stock_surplus_id')
            ->where('ss.branch_id', $branchId)
            ->whereDate('ss.document_date', '>=', $fromStr)
            ->whereDate('ss.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->with('good:id,is_service')
            ->select(['stock_surplus_lines.good_id', 'stock_surplus_lines.quantity', 'stock_surplus_lines.unit_cost', 'ss.warehouse_id'])
            ->get()
            ->each(function ($r) use (&$sum, $resolveCost): void {
                $uc = $r->unit_cost !== null ? (float) $r->unit_cost : $resolveCost((int) $r->good_id, (int) $r->warehouse_id);
                $sum += round((float) $r->quantity * $uc, 2);
            });

        return round($sum, 2);
    }

    /**
     * @param  callable(int,int):float  $resolveCost
     * @return array{out: float, in: float}
     */
    private function sumStockTransferCostsForPeriod(int $branchId, string $fromStr, string $toStr, callable $resolveCost): array
    {
        $outSum = 0.0;
        $inSum = 0.0;
        StockTransferLine::query()
            ->join('stock_transfers as st', 'st.id', '=', 'stock_transfer_lines.stock_transfer_id')
            ->where('st.branch_id', $branchId)
            ->whereDate('st.document_date', '>=', $fromStr)
            ->whereDate('st.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->select([
                'stock_transfer_lines.good_id',
                'stock_transfer_lines.quantity',
                'st.from_warehouse_id',
                'st.to_warehouse_id',
            ])
            ->get()
            ->each(function ($r) use (&$outSum, &$inSum, $resolveCost): void {
                $gid = (int) $r->good_id;
                $qty = (float) $r->quantity;
                $fromWh = (int) $r->from_warehouse_id;
                $toWh = (int) $r->to_warehouse_id;
                $outSum += round($qty * $resolveCost($gid, $fromWh), 2);
                $inSum += round($qty * $resolveCost($gid, $toWh), 2);
            });

        return ['out' => round($outSum, 2), 'in' => round($inSum, 2)];
    }

    /**
     * Оборотно-сальдовая ведомость по денежным счетам: группировка по организациям, колонки дебет/кредит
     * (оформление, близкое к привычной «ОСВ» в 1С). Считает те же сальдо и обороты, что и ранее, но
     * представляет сальдо и итог в виде пары «дебет | кредит».
     *
     * @return array{
     *   groups: list<array{
     *     organization_id: int,
     *     organization_name: string,
     *     accounts: list<array{
     *       id: int,
     *       register_code: string,
     *       account_label: string,
     *       kind: string,
     *       currency: string,
     *       sn_debit: float, sn_credit: float,
     *       to_debit: float, to_credit: float,
     *       sk_debit: float, sk_credit: float
     *     }>,
     *     subtotal: array{sn_debit: float, sn_credit: float, to_debit: float, to_credit: float, sk_debit: float, sk_credit: float}
     *   }>,
     *   grand: array{sn_debit: float, sn_credit: float, to_debit: float, to_credit: float, sk_debit: float, sk_credit: float},
     *   currency_codes: list<string>
     * }
     */
    public function cashTurnoverOsv(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        $accounts = $this->cashLedger->accountsForBranch($branchId);
        $toExclusive = Carbon::parse($to->format('Y-m-d'))->addDay();
        $history = $this->cashLedger->historyRows($branchId, $from, $to);

        $zero = static fn (): array => [
            'sn_debit' => 0.0,
            'sn_credit' => 0.0,
            'to_debit' => 0.0,
            'to_credit' => 0.0,
            'sk_debit' => 0.0,
            'sk_credit' => 0.0,
        ];

        $rowSix = function (array $a) use ($zero): array {
            $z = $zero();
            foreach (array_keys($z) as $k) {
                $z[$k] = round((float) ($a[$k] ?? 0), 2);
            }

            return $z;
        };

        $addSix = function (array $sum, array $b) use ($zero): array {
            $o = $zero();
            foreach (array_keys($o) as $k) {
                $o[$k] = (float) ($sum[$k] ?? 0) + (float) ($b[$k] ?? 0);
            }

            return $o;
        };

        $accountRows = [];
        foreach ($accounts as $acc) {
            $id = (int) $acc->id;
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

            $in = round($in, 2);
            $out = round($out, 2);
            $sn = $this->osvBalanceToDebitCredit($opening);
            $sk = $this->osvBalanceToDebitCredit($closing);

            $org = $acc->organization;
            $registerCode = str_pad((string) ($id % 10000), 4, '0', STR_PAD_LEFT);
            if ($registerCode === '0000') {
                $registerCode = '9999';
            }
            $accountRows[] = [
                'organization_id' => (int) $acc->organization_id,
                'organization_name' => (string) ($org?->name ?? '—'),
                'id' => $id,
                'register_code' => $registerCode,
                'account_label' => $acc->summaryLabel(),
                'kind' => $acc->isCash() ? 'cash' : 'bank',
                'currency' => (string) $acc->currency,
                'sn_debit' => round($sn['debit'], 2),
                'sn_credit' => round($sn['credit'], 2),
                'to_debit' => $in,
                'to_credit' => $out,
                'sk_debit' => round($sk['debit'], 2),
                'sk_credit' => round($sk['credit'], 2),
            ];
        }

        $orderedOrgIds = $accounts->pluck('organization_id')->unique()->values();
        $byOrg = collect($accountRows)->groupBy('organization_id');
        $groups = [];
        $grand = $zero();

        foreach ($orderedOrgIds as $orgId) {
            $orgId = (int) $orgId;
            $orgRows = $byOrg->get($orgId, collect());
            if ($orgRows->isEmpty()) {
                continue;
            }
            $name = (string) $orgRows->first()['organization_name'];
            $sub = $zero();
            $list = [];
            foreach ($orgRows as $r) {
                $list[] = $r;
                $sub = $addSix($sub, $r);
            }
            $sub = $rowSix($sub);
            $grand = $addSix($grand, $sub);
            $groups[] = [
                'organization_id' => $orgId,
                'organization_name' => $name,
                'accounts' => $list,
                'subtotal' => $sub,
            ];
        }

        $currencies = collect($accountRows)->pluck('currency')->unique()->sort()->values()->all();

        return [
            'groups' => $groups,
            'grand' => $rowSix($grand),
            'currency_codes' => $currencies,
        ];
    }

    /**
     * Расшифровка ячейки синтетического счёта ОСВ (id строки в таблице — отрицательный).
     *
     * @return array<string, mixed>
     */
    public function turnoverOsvSyntheticDetail(int $branchId, int $synthId, string $kind, CarbonInterface $from, CarbonInterface $to): array
    {
        $allowed = ['opening', 'turnover_debit', 'turnover_credit', 'closing'];
        if (! in_array($kind, $allowed, true)) {
            throw new \InvalidArgumentException('Неизвестный тип ячейки.');
        }

        $kindLabels = [
            'opening' => 'Сальдо на начало периода',
            'turnover_debit' => 'Оборот за период — дебет',
            'turnover_credit' => 'Оборот за период — кредит',
            'closing' => 'Сальдо на конец периода',
        ];
        $kindLabel = $kindLabels[$kind];

        return match ($synthId) {
            -1310 => $this->turnoverOsvSynth1310($branchId, $kind, $kindLabel, $from, $to),
            -3310 => $this->turnoverOsvSynth3310($branchId, $kind, $kindLabel, $from, $to),
            -4110 => $this->turnoverOsvSynth4110($branchId, $kind, $kindLabel, $from, $to),
            -4210 => $this->turnoverOsvSynth4210($branchId, $kind, $kindLabel, $from, $to),
            -6010, -6011, -6110, -6120, -6030 => $this->turnoverOsvSynthIncome($branchId, $synthId, $kind, $kindLabel, $from, $to),
            -7110 => $this->turnoverOsvSynth7110($branchId, $kind, $kindLabel, $from, $to),
            -7510 => $this->turnoverOsvSynth7510($branchId, $kind, $kindLabel, $from, $to),
            -9910 => $this->turnoverOsvSynth9910($branchId, $kind, $kindLabel, $from, $to),
            default => throw new \InvalidArgumentException('Для этой строки расшифровка не настроена.'),
        };
    }

    /**
     * @param  list<array{date: ?string, title: string, detail: string, amount: float, amount_fmt: string}>  $lines
     * @return array<string, mixed>
     */
    private function osvSynthJson(
        string $code,
        string $accountLabel,
        string $kind,
        string $kindLabel,
        array $lines,
        string $footerNote,
        ?float $total = null
    ): array {
        $out = [
            'account_code' => $code,
            'account_label' => $accountLabel,
            'kind' => $kind,
            'kind_label' => $kindLabel,
            'is_synthetic' => true,
            'lines' => $lines,
            'footer_note' => $footerNote,
        ];
        if ($total !== null) {
            $out['total'] = round($total, 2);
            $out['total_fmt'] = InvoiceNakladnayaFormatter::formatMoney(abs($total));
        }

        return $out;
    }

    /**
     * @return array{date: ?string, title: string, detail: string, amount: float, amount_fmt: string}
     */
    private function osvSynthLine(?string $date, string $title, string $detail, float $amount): array
    {
        $r = round($amount, 2);

        return [
            'date' => $date,
            'title' => $title,
            'detail' => $detail,
            'amount' => $r,
            'amount_fmt' => InvoiceNakladnayaFormatter::formatMoney($r),
        ];
    }

    private function turnoverOsvSynthPnlNoSaldo(string $code, string $label, string $kind, string $kindLabel): array
    {
        return $this->osvSynthJson(
            $code,
            $label,
            $kind,
            $kindLabel,
            [],
            'Для счетов доходов и расходов в этой ведомости заполняются только обороты за период; сальдо на начало и конец не строится.',
        );
    }

    private function turnoverOsvSynth1310(int $branchId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $onBeforePeriod = $from->copy()->subDay()->startOfDay();
        $onEndPeriod = $to->copy()->startOfDay();
        $resolveCost = $this->buildCostResolverForBranch($branchId);
        $gp = $this->grossProfit($branchId, $from, $to);
        $cogs = round((float) $gp['cost'], 2);

        $invOpen = round($this->inventoryValuationAtBoundary($branchId, $onBeforePeriod), 2);
        $invClose = round($this->inventoryValuationAtBoundary($branchId, $onEndPeriod), 2);

        $purchaseIn = (float) (PurchaseReceiptLine::query()
            ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>=', $fromStr)
            ->whereDate('pr.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('purchase_receipt_lines.line_sum'));

        $purchaseRetOut = (float) (PurchaseReturnLine::query()
            ->join('purchase_returns as pr', 'pr.id', '=', 'purchase_return_lines.purchase_return_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>=', $fromStr)
            ->whereDate('pr.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('purchase_return_lines.line_sum'));

        $customerReturnInCost = $this->sumCustomerReturnStockCostForPeriod($branchId, $fromStr, $toStr, $resolveCost);
        $writeoffCost = $this->sumStockWriteoffCostForPeriod($branchId, $fromStr, $toStr, $resolveCost);
        $surplusDebit = $this->sumStockSurplusCostForPeriod($branchId, $fromStr, $toStr, $resolveCost);
        $costTransferOutIn = $this->sumStockTransferCostsForPeriod($branchId, $fromStr, $toStr, $resolveCost);

        $debitTurn = round(
            $purchaseIn + $customerReturnInCost + $surplusDebit + ($costTransferOutIn['in'] ?? 0.0),
            2
        );
        $creditTurn = round(
            $cogs + $purchaseRetOut + $writeoffCost + ($costTransferOutIn['out'] ?? 0.0),
            2
        );

        $code = '1310';
        $label = 'Материальные запасы (товары на складах)';

        if ($kind === 'opening') {
            $lines = [
                $this->osvSynthLine(null, 'Оценка запасов на начало', 'Учётные цены из карточек остатков и движения до '.$from->format('d.m.Y'), $invOpen),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Сальдо — сумма количества × учётная цена по каждой позиции входящих остатков (без услуг).',
                $invOpen);
        }
        if ($kind === 'closing') {
            $lines = [
                $this->osvSynthLine(null, 'Оценка запасов на конец', 'На '.$to->format('d.m.Y'), $invClose),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'То же правило, что на начало: остатки по складам × учётная себестоимость.',
                $invClose);
        }
        if ($kind === 'turnover_debit') {
            $lines = [
                $this->osvSynthLine(null, 'Поступления (закупки у поставщиков)', 'Суммы строк приходных накладных за период', $purchaseIn),
                $this->osvSynthLine(null, 'Возвраты от покупателей на склад', 'По учётной цене отгрузки', $customerReturnInCost),
                $this->osvSynthLine(null, 'Оприходование излишков', 'По учётной цене', $surplusDebit),
                $this->osvSynthLine(null, 'Перемещения — приход на склад', 'Себестоимость вх. остатка', (float) ($costTransferOutIn['in'] ?? 0.0)),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Дебет оборота — всё, что увеличило оценку запасов за период.', $debitTurn);
        }

        $lines = [
            $this->osvSynthLine(null, 'Себестоимость проданных товаров', 'По строкам розницы и опта (не услуги), кол-во × учётная цена', $cogs),
            $this->osvSynthLine(null, 'Возвраты поставщику', 'Суммы строк за период', $purchaseRetOut),
            $this->osvSynthLine(null, 'Списания со склада', 'По учётной цене', $writeoffCost),
            $this->osvSynthLine(null, 'Перемещения — расход со склада', 'Себестоимость', (float) ($costTransferOutIn['out'] ?? 0.0)),
        ];

        return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
            'Кредит оборота — всё, что уменьшило оценку запасов за период.', $creditTurn);
    }

    private function turnoverOsvSynth3310(int $branchId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $openAp = round((float) Counterparty::query()->where('branch_id', $branchId)->sum('opening_debt_as_supplier'), 2);

        $purchaseIn = (float) (PurchaseReceiptLine::query()
            ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>=', $fromStr)
            ->whereDate('pr.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('purchase_receipt_lines.line_sum'));

        $paymentsToSuppliers = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $closingApNet = round($openAp + $purchaseIn - $paymentsToSuppliers, 2);
        $code = '3310';
        $label = 'Задолженность поставщикам';

        if ($kind === 'opening') {
            $lines = [
                $this->osvSynthLine(null, 'Входящие сальдо контрагентов', 'Поле «долг перед поставщиком» в справочнике (сумма по филиалу)', $openAp),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Упрощённая модель: старт — только из справочника контрагентов.', $openAp);
        }
        if ($kind === 'closing') {
            $lines = [
                $this->osvSynthLine(null, 'Расчёт на конец', 'Входящие долги + закупки за период − оплаты поставщикам из «Банк и касса»', $closingApNet),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Это не полная бухгалтерская кредиторка, а связка справочника и документов закупки/оплат.', $closingApNet);
        }
        if ($kind === 'turnover_debit') {
            $lines = [];
            $movements = CashMovement::query()
                ->where('branch_id', $branchId)
                ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
                ->whereDate('occurred_on', '>=', $fromStr)
                ->whereDate('occurred_on', '<=', $toStr)
                ->with('counterparty')
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit(200)
                ->get();
            foreach ($movements as $m) {
                $cp = $m->counterparty?->name ?? '';
                $lines[] = $this->osvSynthLine(
                    $m->occurred_on->format('d.m.Y'),
                    'Оплата поставщику',
                    trim($cp.' '.(string) $m->comment),
                    (float) $m->amount
                );
            }
            if (empty($lines)) {
                $lines[] = $this->osvSynthLine(null, '—', 'Нет оплат поставщикам за период', 0.0);
            }

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Дебет оборота — оплаты из раздела «Банк и касса» (расход поставщику), уменьшают долг.',
                $paymentsToSuppliers);
        }

        $lines = [];
        $receipts = PurchaseReceiptLine::query()
            ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
            ->where('pr.branch_id', $branchId)
            ->whereDate('pr.document_date', '>=', $fromStr)
            ->whereDate('pr.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->selectRaw('pr.id, pr.document_date, SUM(purchase_receipt_lines.line_sum) as s')
            ->groupBy('pr.id', 'pr.document_date')
            ->orderByDesc('s')
            ->limit(200)
            ->get();
        foreach ($receipts as $r) {
            $lines[] = $this->osvSynthLine(
                $r->document_date instanceof CarbonInterface ? $r->document_date->format('d.m.Y') : null,
                'Поступление от поставщика',
                'Документ № '.$r->id,
                (float) $r->s
            );
        }
        if (empty($lines)) {
            $lines[] = $this->osvSynthLine(null, '—', 'Нет закупок товара за период', 0.0);
        }

        return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
            'Кредит оборота — суммы строк приходов (накладные), увеличивают долг перед поставщиками.',
            $purchaseIn);
    }

    private function turnoverOsvSynth4110(int $branchId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $openArRef = round((float) Counterparty::query()->where('branch_id', $branchId)->sum('opening_debt_as_buyer'), 2);

        $paymentsFromClientsTracked = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereNotNull('counterparty_id')
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $legalTurnoverDebit = round((float) LegalEntitySaleLine::query()
            ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
            ->where('ls.branch_id', $branchId)
            ->whereDate('ls.document_date', '>=', $fromStr)
            ->whereDate('ls.document_date', '<=', $toStr)
            ->sum('legal_entity_sale_lines.line_sum'), 2);

        $closingArRef = round($openArRef + $legalTurnoverDebit - $paymentsFromClientsTracked, 2);
        $code = '4110';
        $label = 'Дебиторская задолженность покупателей (юрлица, справочник)';

        if ($kind === 'opening') {
            $lines = [
                $this->osvSynthLine(null, 'Входящие сальдо', 'Сумма полей «долг покупателя» у контрагентов филиала', $openArRef),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Старт модели — только справочник контрагентов.', $openArRef);
        }
        if ($kind === 'closing') {
            $lines = [
                $this->osvSynthLine(null, 'Расчёт на конец', 'Входящие + суммы продаж юрлицам − оплаты от клиентов с привязкой контрагента', $closingArRef),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Розница и оплаты без контрагента сюда по этой строке не входят.', $closingArRef);
        }
        if ($kind === 'turnover_debit') {
            $lines = [];
            $buckets = LegalEntitySaleLine::query()
                ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
                ->where('ls.branch_id', $branchId)
                ->whereDate('ls.document_date', '>=', $fromStr)
                ->whereDate('ls.document_date', '<=', $toStr)
                ->selectRaw('ls.id, ls.document_date, SUM(legal_entity_sale_lines.line_sum) as s')
                ->groupBy('ls.id', 'ls.document_date')
                ->orderByDesc('s')
                ->limit(200)
                ->get();
            foreach ($buckets as $b) {
                $lines[] = $this->osvSynthLine(
                    $b->document_date instanceof CarbonInterface ? $b->document_date->format('d.m.Y') : null,
                    'Реализация юрлицу',
                    'Документ № '.$b->id,
                    (float) $b->s
                );
            }

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Дебет оборота — суммы строк счетов/отгрузок юрлицам за период.', $legalTurnoverDebit);
        }

        $lines = [];
        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereNotNull('counterparty_id')
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->with('counterparty')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
        foreach ($movements as $m) {
            $cp = $m->counterparty?->name ?? '';
            $lines[] = $this->osvSynthLine(
                $m->occurred_on->format('d.m.Y'),
                'Оплата от клиента',
                trim($cp.' '.(string) $m->comment),
                (float) $m->amount
            );
        }

        return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
            'Кредит оборота — приходы из «Банк и касса» с указанием контрагента-покупателя.', $paymentsFromClientsTracked);
    }

    private function turnoverOsvSynth4210(int $branchId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $fromStart = $from->copy()->startOfDay();

        $retailDebtOpen = $this->retailDebtOutstandingAtMoment($branchId, $fromStart, false);
        $retailDebtClose = $this->retailDebtOutstandingAtMoment($branchId, $to->copy()->startOfDay(), true);
        $retailDebtDebitTurn = $this->retailDebtIssuedInPeriod($branchId, $fromStr, $toStr);
        $retailDebtCreditTurn = $this->retailDebtRepaidAfterCheckoutInPeriod($branchId, $fromStr, $toStr);

        $code = '4210';
        $label = 'Розничная задолженность (долг в чеках)';

        if ($kind === 'opening') {
            $lines = [
                $this->osvSynthLine(null, 'Долг по чекам до начала периода', 'По каждому чеку: сумма чека − все оплаты, учтённые до '.$from->format('d.m.Y'), $retailDebtOpen),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Учёт по времени создания платежа (retail_sale_payments.created_at).',
                $retailDebtOpen);
        }
        if ($kind === 'closing') {
            $lines = [
                $this->osvSynthLine(null, 'Долг по чекам на конец периода', 'Оплаты до конца '.$to->format('d.m.Y').' включительно', $retailDebtClose),
            ];

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Незакрытый остаток долга по полю остатка (total − оплаты на дату).',
                $retailDebtClose);
        }
        if ($kind === 'turnover_debit') {
            $lines = [];
            $sales = RetailSale::query()
                ->where('branch_id', $branchId)
                ->whereDate('document_date', '>=', $fromStr)
                ->whereDate('document_date', '<=', $toStr)
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'document_date', 'debt_amount', 'created_at']);
            $ids = $sales->pluck('id')->all();
            $extras = [];
            if ($ids !== []) {
                $extras = RetailSalePayment::query()
                    ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
                    ->whereIn('retail_sale_payments.retail_sale_id', $ids)
                    ->whereColumn('retail_sale_payments.created_at', '>', 'rs.created_at')
                    ->groupBy('retail_sale_payments.retail_sale_id')
                    ->selectRaw('retail_sale_payments.retail_sale_id as sid, SUM(retail_sale_payments.amount) as extra')
                    ->pluck('extra', 'sid');
            }
            foreach ($sales as $s) {
                $extra = (float) ($extras[$s->id] ?? 0.0);
                $issued = max(0.0, round((float) $s->debt_amount + $extra, 2));
                if ($issued < 0.005) {
                    continue;
                }
                $lines[] = $this->osvSynthLine(
                    $s->document_date->format('d.m.Y'),
                    'Отсрочка по чеку № '.$s->id,
                    'Первоначальный долг с чеком (остаток + оплаты после оформления чека)',
                    $issued
                );
            }
            if (empty($lines)) {
                $lines[] = $this->osvSynthLine(null, '—', 'Нет новой отсрочки по датам чеков в периоде', 0.0);
            }

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Только чеки с ненулевой выданной отсрочкой.', $retailDebtDebitTurn);
        }

        $lines = [];
        $payments = RetailSalePayment::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_payments.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('retail_sale_payments.created_at', '>=', $fromStr)
            ->whereDate('retail_sale_payments.created_at', '<=', $toStr)
            ->whereColumn('retail_sale_payments.created_at', '>', 'rs.created_at')
            ->orderByDesc('retail_sale_payments.created_at')
            ->orderByDesc('retail_sale_payments.id')
            ->limit(200)
            ->get(['retail_sale_payments.amount', 'retail_sale_payments.created_at', 'retail_sale_payments.retail_sale_id']);
        foreach ($payments as $p) {
            $lines[] = $this->osvSynthLine(
                Carbon::parse($p->created_at)->format('d.m.Y'),
                'Погашение долга по чеку',
                'Чек № '.(int) $p->retail_sale_id,
                (float) $p->amount
            );
        }
        if (empty($lines)) {
            $lines[] = $this->osvSynthLine(null, '—', 'Нет доплат по долгу после оформления чека в периоде', 0.0);
        }

        return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
            'Учитываются только платежи, у которых время позже создания записи чека (доплата в долг).',
            $retailDebtCreditTurn);
    }

    private function turnoverOsvSynthIncome(int $branchId, int $synthId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        [$code, $label, $isService, $retail, $isIncomeOther] = match ($synthId) {
            -6010 => ['6010', 'Выручка розницы — товары', false, true, false],
            -6011 => ['6011', 'Выручка розницы — услуги', true, true, false],
            -6110 => ['6110', 'Выручка опт — товары', false, false, false],
            -6120 => ['6120', 'Выручка опт — услуги', true, false, false],
            -6030 => ['6030', 'Прочие поступления', false, false, true],
            default => throw new \InvalidArgumentException('Неизвестный счёт дохода.'),
        };

        if ($kind !== 'turnover_credit') {
            return $this->turnoverOsvSynthPnlNoSaldo($code, $label, $kind, $kindLabel);
        }

        if ($isIncomeOther) {
            $lines = [];
            $movements = CashMovement::query()
                ->where('branch_id', $branchId)
                ->where('kind', CashMovement::KIND_INCOME_OTHER)
                ->whereDate('occurred_on', '>=', $fromStr)
                ->whereDate('occurred_on', '<=', $toStr)
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit(250)
                ->get();
            foreach ($movements as $m) {
                $lines[] = $this->osvSynthLine(
                    $m->occurred_on->format('d.m.Y'),
                    'Приход прочее',
                    trim(trim((string) $m->expense_category).' '.(string) $m->comment),
                    (float) $m->amount
                );
            }
            if (empty($lines)) {
                $lines[] = $this->osvSynthLine(null, '—', 'Нет операций «Приход прочее» за период', 0.0);
            }
            $total = (float) $movements->sum('amount');

            return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
                'Документы «Банк и касса» — приход прочее (в т.ч. займы с кредитором «прочее»).',
                round($total, 2));
        }

        $lines = [];
        if ($retail) {
            $buckets = RetailSaleLine::query()
                ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_lines.retail_sale_id')
                ->where('rs.branch_id', $branchId)
                ->whereDate('rs.document_date', '>=', $fromStr)
                ->whereDate('rs.document_date', '<=', $toStr)
                ->whereHas('good', fn ($g) => $g->where('is_service', $isService))
                ->selectRaw('rs.id, rs.document_date, SUM(retail_sale_lines.line_sum) as s')
                ->groupBy('rs.id', 'rs.document_date')
                ->orderByDesc('s')
                ->limit(200)
                ->get();
            foreach ($buckets as $b) {
                $lines[] = $this->osvSynthLine(
                    $b->document_date instanceof CarbonInterface ? $b->document_date->format('d.m.Y') : null,
                    'Розничный чек',
                    '№ '.$b->id,
                    (float) $b->s
                );
            }
            $total = (float) RetailSaleLine::query()
                ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_lines.retail_sale_id')
                ->where('rs.branch_id', $branchId)
                ->whereDate('rs.document_date', '>=', $fromStr)
                ->whereDate('rs.document_date', '<=', $toStr)
                ->whereHas('good', fn ($g) => $g->where('is_service', $isService))
                ->sum('retail_sale_lines.line_sum');
        } else {
            $buckets = LegalEntitySaleLine::query()
                ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
                ->where('ls.branch_id', $branchId)
                ->whereDate('ls.document_date', '>=', $fromStr)
                ->whereDate('ls.document_date', '<=', $toStr)
                ->whereHas('good', fn ($g) => $g->where('is_service', $isService))
                ->selectRaw('ls.id, ls.document_date, SUM(legal_entity_sale_lines.line_sum) as s')
                ->groupBy('ls.id', 'ls.document_date')
                ->orderByDesc('s')
                ->limit(200)
                ->get();
            foreach ($buckets as $b) {
                $lines[] = $this->osvSynthLine(
                    $b->document_date instanceof CarbonInterface ? $b->document_date->format('d.m.Y') : null,
                    'Продажа юрлицу',
                    'Документ № '.$b->id,
                    (float) $b->s
                );
            }
            $total = (float) LegalEntitySaleLine::query()
                ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
                ->where('ls.branch_id', $branchId)
                ->whereDate('ls.document_date', '>=', $fromStr)
                ->whereDate('ls.document_date', '<=', $toStr)
                ->whereHas('good', fn ($g) => $g->where('is_service', $isService))
                ->sum('legal_entity_sale_lines.line_sum');
        }

        if (empty($lines)) {
            $lines[] = $this->osvSynthLine(null, '—', 'Нет строк за период', 0.0);
        }

        return $this->osvSynthJson($code, $label, $kind, $kindLabel, $lines,
            'Суммы строк продаж за период (как в отчёте), до 200 крупнейших документов в списке.',
            round($total, 2));
    }

    private function turnoverOsvSynth7110(int $branchId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($kind !== 'turnover_debit') {
            return $this->turnoverOsvSynthPnlNoSaldo('7110', 'Себестоимость проданных товаров', $kind, $kindLabel);
        }
        $gp = $this->grossProfit($branchId, $from, $to);
        $lines = [];
        foreach ($gp['lines']->sortByDesc('cost')->take(150) as $row) {
            $lines[] = $this->osvSynthLine(
                null,
                (string) $row['name'],
                'Арт. '.(string) $row['article'].' · кол-во '.(string) $row['quantity'],
                (float) $row['cost']
            );
        }
        $total = round((float) $gp['cost'], 2);

        return $this->osvSynthJson(
            '7110',
            'Себестоимость проданных товаров',
            $kind,
            $kindLabel,
            $lines,
            'Совпадает с блоком «Себестоимость» отчёта валовой прибыли: количество продаж × учётная цена запаса.',
            $total
        );
    }

    private function turnoverOsvSynth7510(int $branchId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($kind !== 'turnover_debit') {
            return $this->turnoverOsvSynthPnlNoSaldo('7510', 'Прочие расходы', $kind, $kindLabel);
        }
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');

        $lines = [];
        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        foreach ($movements as $m) {
            $cat = trim((string) $m->expense_category);
            $lines[] = $this->osvSynthLine(
                $m->occurred_on->format('d.m.Y'),
                $cat !== '' ? $cat : 'Прочий расход',
                (string) $m->comment,
                (float) $m->amount
            );
        }
        if ($lines === []) {
            $lines[] = [
                'date' => '—',
                'summary' => 'За период нет записей «Прочий расход».',
                'comment' => '',
                'debit' => null,
                'credit' => null,
            ];
        }
        $total = round((float) $movements->sum('amount'), 2);

        return $this->osvSynthJson(
            '7510',
            'Прочие расходы («Банк и касса»)',
            $kind,
            $kindLabel,
            $lines,
            'В том числе выплаты зарплаты, если они оформлены как прочий расход с категорией «Зарплата».',
            $total
        );
    }

    private function turnoverOsvSynth9910(int $branchId, string $kind, string $kindLabel, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! in_array($kind, ['turnover_debit', 'turnover_credit'], true)) {
            return $this->turnoverOsvSynthPnlNoSaldo('9910', 'Финансовый результат', $kind, $kindLabel);
        }

        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        $gp = $this->grossProfit($branchId, $from, $to);
        $cogs = round((float) $gp['cost'], 2);

        $retailGoodsRev = (float) RetailSaleLine::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_lines.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('rs.document_date', '>=', $fromStr)
            ->whereDate('rs.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('retail_sale_lines.line_sum');

        $retailServicesRev = (float) RetailSaleLine::query()
            ->join('retail_sales as rs', 'rs.id', '=', 'retail_sale_lines.retail_sale_id')
            ->where('rs.branch_id', $branchId)
            ->whereDate('rs.document_date', '>=', $fromStr)
            ->whereDate('rs.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', true))
            ->sum('retail_sale_lines.line_sum');

        $legalGoodsRev = (float) LegalEntitySaleLine::query()
            ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
            ->where('ls.branch_id', $branchId)
            ->whereDate('ls.document_date', '>=', $fromStr)
            ->whereDate('ls.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', false))
            ->sum('legal_entity_sale_lines.line_sum');

        $legalServicesRev = (float) LegalEntitySaleLine::query()
            ->join('legal_entity_sales as ls', 'ls.id', '=', 'legal_entity_sale_lines.legal_entity_sale_id')
            ->where('ls.branch_id', $branchId)
            ->whereDate('ls.document_date', '>=', $fromStr)
            ->whereDate('ls.document_date', '<=', $toStr)
            ->whereHas('good', fn ($g) => $g->where('is_service', true))
            ->sum('legal_entity_sale_lines.line_sum');

        $expenseOther = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $incomeOther = (float) CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_OTHER)
            ->whereDate('occurred_on', '>=', $fromStr)
            ->whereDate('occurred_on', '<=', $toStr)
            ->sum('amount');

        $totalIncome = round(
            $retailGoodsRev + $retailServicesRev + $legalGoodsRev + $legalServicesRev + $incomeOther,
            2
        );
        $totalExpense = round($cogs + $expenseOther, 2);
        $profit = round($totalIncome - $totalExpense, 2);

        $lines = [
            $this->osvSynthLine(null, 'Доходы п.4 (6010–6120 + 6030)', 'Сумма строк раздела доходов', $totalIncome),
            $this->osvSynthLine(null, 'Расходы п.5 (7110 + 7510)', 'Себестоимость + прочие расходы из кассы/банка', $totalExpense),
            $this->osvSynthLine(null, 'Разница', $profit >= 0 ? 'Прибыль' : 'Убыток', abs($profit)),
        ];

        $footer = 'Управленческий итог без налогов, начислений ФОТ до выплаты и полного плана счетов.';

        if ($kind === 'turnover_credit' && $profit > 0) {
            return $this->osvSynthJson('9910', 'Финансовый результат', $kind, $kindLabel, $lines, $footer, $profit);
        }
        if ($kind === 'turnover_debit' && $profit < 0) {
            return $this->osvSynthJson('9910', 'Финансовый результат', $kind, $kindLabel, $lines, $footer, abs($profit));
        }

        return $this->osvSynthJson(
            '9910',
            'Финансовый результат',
            $kind,
            $kindLabel,
            [],
            'За период нет прибыли в выбранной колонке: прибыль/убыток отражается только в одной из колонок оборота.'
        );
    }

    /**
     * Чистый остаток на счёте: положительный — в дебет, отрицательный (перерасход) — в кредит.
     *
     * @return array{debit: float, credit: float}
     */
    private function osvBalanceToDebitCredit(float $balance): array
    {
        if ($balance >= 0) {
            return ['debit' => $balance, 'credit' => 0.0];
        }

        return ['debit' => 0.0, 'credit' => -$balance];
    }
}
