<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Counterparty;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\LegalEntitySale;
use App\Models\LegalEntitySaleLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\PurchaseReturn;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    /** Список «Покупатели» в сверке (тип в справочнике). */
    public const MODE_BUYERS = 'buyers';

    /** Список «Поставщики» в сверке (тип в справочнике). */
    public const MODE_SELLERS = 'sellers';

    public function index(Request $request): View|RedirectResponse
    {
        $branchId = (int) auth()->user()->branch_id;

        $mode = $this->normalizeMode($request->query('mode'));

        $branchHasAnyCounterparty = Counterparty::query()->where('branch_id', $branchId)->exists();

        $counterparties = $this->counterpartiesForReconciliationMode($branchId, $mode);

        $counterpartyId = (int) $request->integer('counterparty_id') ?: 0;
        $counterparty = $counterpartyId > 0
            ? Counterparty::query()->where('branch_id', $branchId)->find($counterpartyId)
            : null;

        if ($counterparty !== null && ! $this->counterpartyMatchesMode($counterparty, $mode)) {
            $alt = $mode === self::MODE_BUYERS ? self::MODE_SELLERS : self::MODE_BUYERS;
            if ($this->counterpartyMatchesMode($counterparty, $alt)) {
                return redirect()->route('admin.reconciliation.index', array_merge(
                    $request->only(['date_from', 'date_to']),
                    ['mode' => $alt, 'counterparty_id' => $counterparty->id]
                ));
            }

            abort(404);
        }

        if ($counterpartyId === 0) {
            // Список: всегда полный долг за всю историю (период на этом экране не задаётся).
            $from = Carbon::create(2000, 1, 1)->startOfDay();
            $to = Carbon::now()->endOfDay();
        } else {
            $anyDateFilter = $request->filled('date_from') || $request->filled('date_to');
            // Карточка контрагента: без дат — типичный месяц для разбора по периоду.
            if (! $anyDateFilter) {
                $from = Carbon::now()->startOfMonth();
                $to = Carbon::now()->startOfDay();
            } else {
                $from = $request->date('date_from') ?: Carbon::now()->startOfMonth();
                $to = $request->date('date_to') ?: Carbon::now()->startOfDay();
            }
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        $buyerRows = collect();
        $supplierRows = collect();
        $buyerOpening = '0';
        $buyerClosing = '0';
        $supplierOpening = '0';
        $supplierClosing = '0';
        $summaryRows = collect();
        $paidIncomePeriod = '0';
        $paidExpensePeriod = '0';
        $buyerSalesPeriod = '0';
        $buyerReturnsPeriod = '0';
        $buyerPeriodPurchasesNet = '0';
        $supplierPurchasesPeriod = '0';
        $supplierReturnsPeriod = '0';

        if ($counterparty !== null) {
            $data = $this->reconciliationForCounterparty($branchId, $counterparty, $from, $to);
            $buyerRows = $data['buyerRows'];
            $supplierRows = $data['supplierRows'];
            $buyerOpening = $data['buyerOpening'];
            $buyerClosing = $data['buyerClosing'];
            $supplierOpening = $data['supplierOpening'];
            $supplierClosing = $data['supplierClosing'];
            $paidIncomePeriod = $data['paidIncomePeriod'];
            $paidExpensePeriod = $data['paidExpensePeriod'];
            $buyerSalesPeriod = $data['buyerSalesPeriod'];
            $buyerReturnsPeriod = $data['buyerReturnsPeriod'];
            $buyerPeriodPurchasesNet = $data['buyerPeriodPurchasesNet'];
            $supplierPurchasesPeriod = $data['supplierPurchasesPeriod'];
            $supplierReturnsPeriod = $data['supplierReturnsPeriod'];
        } else {
            foreach ($counterparties as $cp) {
                $data = $this->reconciliationForCounterparty($branchId, $cp, $from, $to);
                if ($mode === self::MODE_BUYERS) {
                    $summaryRows->push([
                        'counterparty' => $cp,
                        'period_purchases' => $data['buyerPeriodPurchasesNet'],
                        'paid' => $data['paidIncomePeriod'],
                        'debt' => $data['buyerClosing'],
                        'opening_debt_card' => $this->openingDebtString($cp->opening_debt_as_buyer),
                    ]);
                } else {
                    $summaryRows->push([
                        'counterparty' => $cp,
                        'period_purchases' => $data['supplierPurchasesPeriod'],
                        'paid' => $data['paidExpensePeriod'],
                        'debt' => $data['supplierClosing'],
                        'opening_debt_card' => $this->openingDebtString($cp->opening_debt_as_supplier),
                    ]);
                }
            }
        }

        return view('admin.reconciliation.index', [
            'counterparties' => $counterparties,
            'branchHasAnyCounterparty' => $branchHasAnyCounterparty,
            'counterpartyId' => $counterpartyId,
            'counterparty' => $counterparty,
            'mode' => $mode,
            'from' => $from,
            'to' => $to,
            'summaryRows' => $summaryRows,
            'buyerRows' => $buyerRows,
            'supplierRows' => $supplierRows,
            'buyerOpening' => $buyerOpening,
            'buyerClosing' => $buyerClosing,
            'supplierOpening' => $supplierOpening,
            'supplierClosing' => $supplierClosing,
            'paidIncomePeriod' => $paidIncomePeriod,
            'paidExpensePeriod' => $paidExpensePeriod,
            'buyerSalesPeriod' => $buyerSalesPeriod,
            'buyerReturnsPeriod' => $buyerReturnsPeriod,
            'buyerPeriodPurchasesNet' => $buyerPeriodPurchasesNet,
            'supplierPurchasesPeriod' => $supplierPurchasesPeriod,
            'supplierReturnsPeriod' => $supplierReturnsPeriod ?? '0',
        ]);
    }

    /**
     * @return array{
     *     buyerRows: Collection,
     *     supplierRows: Collection,
     *     buyerOpening: string,
     *     buyerClosing: string,
     *     supplierOpening: string,
     *     supplierClosing: string,
     *     paidIncomePeriod: string,
     *     paidExpensePeriod: string,
     * }
     */
    private function reconciliationForCounterparty(int $branchId, Counterparty $counterparty, Carbon $from, Carbon $to): array
    {
        $buyerAliases = $counterparty->legalSaleBuyerNameAliases();
        $supplierAliases = $counterparty->supplierNameAliases();

        $buyerOpening = $this->buyerOpeningBalance($branchId, $counterparty, $buyerAliases, $from);
        $supplierOpening = $this->supplierOpeningBalance($branchId, $counterparty, $supplierAliases, $from);

        $buyerRows = $this->buyerPeriodRows($branchId, $counterparty->id, $buyerAliases, $from, $to);
        $supplierRows = $this->supplierPeriodRows($branchId, $counterparty->id, $supplierAliases, $from, $to);

        $buyerClosing = bcadd($buyerOpening, $this->sumBuyerDelta($buyerRows), 2);
        $supplierClosing = bcadd($supplierOpening, $this->sumSupplierDelta($supplierRows), 2);

        $paidIncomePeriod = $this->sumIncomeClientInPeriod($branchId, $counterparty->id, $from, $to);
        $paidExpensePeriod = $this->sumExpenseSupplierInPeriod($branchId, $counterparty->id, $from, $to);

        $buyerSalesPeriod = $this->sumBuyerSalesInPeriod($branchId, $buyerAliases, $from, $to);
        $buyerReturnsPeriod = $this->sumBuyerReturnsInPeriod($branchId, $buyerAliases, $from, $to);

        $supplierPurchasesGross = $this->sumSupplierPurchasesInPeriod($branchId, $supplierAliases, $from, $to);
        $supplierReturnsPeriod = $this->sumSupplierReturnsInPeriod($branchId, $supplierAliases, $from, $to);

        return [
            'buyerRows' => $buyerRows,
            'supplierRows' => $supplierRows,
            'buyerOpening' => $buyerOpening,
            'buyerClosing' => $buyerClosing,
            'supplierOpening' => $supplierOpening,
            'supplierClosing' => $supplierClosing,
            'paidIncomePeriod' => $paidIncomePeriod,
            'paidExpensePeriod' => $paidExpensePeriod,
            'buyerPeriodPurchasesNet' => bcsub($buyerSalesPeriod, $buyerReturnsPeriod, 2),
            'buyerSalesPeriod' => $buyerSalesPeriod,
            'buyerReturnsPeriod' => $buyerReturnsPeriod,
            'supplierPurchasesPeriod' => bcsub($supplierPurchasesGross, $supplierReturnsPeriod, 2),
            'supplierReturnsPeriod' => $supplierReturnsPeriod,
        ];
    }

    private function normalizeMode(mixed $mode): string
    {
        $m = is_string($mode) ? $mode : '';
        if ($m === 'they_owe' || $m === self::MODE_BUYERS) {
            return self::MODE_BUYERS;
        }
        if ($m === 'we_owe' || $m === self::MODE_SELLERS) {
            return self::MODE_SELLERS;
        }

        return self::MODE_BUYERS;
    }

    /**
     * Список контрагентов для вкладки: по полю «Тип» в справочнике (покупатель / поставщик / прочее в обоих).
     *
     * @return Collection<int, Counterparty>
     */
    private function counterpartiesForReconciliationMode(int $branchId, string $mode): Collection
    {
        $q = Counterparty::query()
            ->where('branch_id', $branchId)
            ->orderBy('full_name')
            ->orderBy('name');

        if ($mode === self::MODE_BUYERS) {
            $q->whereIn('kind', [Counterparty::KIND_BUYER, Counterparty::KIND_OTHER]);
        } else {
            $q->whereIn('kind', [Counterparty::KIND_SUPPLIER, Counterparty::KIND_OTHER]);
        }

        return $q->get();
    }

    private function counterpartyMatchesMode(Counterparty $counterparty, string $mode): bool
    {
        if ($counterparty->kind === Counterparty::KIND_OTHER) {
            return true;
        }

        if ($mode === self::MODE_BUYERS) {
            return $counterparty->kind === Counterparty::KIND_BUYER;
        }

        return $counterparty->kind === Counterparty::KIND_SUPPLIER;
    }

    private function sumBuyerSalesInPeriod(int $branchId, array $buyerAliases, Carbon $from, Carbon $to): string
    {
        if ($buyerAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($sales as $sale) {
            $total = bcadd($total, $this->sumLines($sale->lines), 2);
        }

        return $total;
    }

    private function sumBuyerReturnsInPeriod(int $branchId, array $buyerAliases, Carbon $from, Carbon $to): string
    {
        if ($buyerAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $returns = CustomerReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($returns as $ret) {
            $total = bcadd($total, $this->sumLines($ret->lines), 2);
        }

        return $total;
    }

    private function sumSupplierPurchasesInPeriod(int $branchId, array $supplierAliases, Carbon $from, Carbon $to): string
    {
        if ($supplierAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $docs = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    private function sumSupplierReturnsInPeriod(int $branchId, array $supplierAliases, Carbon $from, Carbon $to): string
    {
        if ($supplierAliases === []) {
            return '0';
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $total = '0';
        $docs = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    private function sumIncomeClientInPeriod(int $branchId, int $counterpartyId, Carbon $from, Carbon $to): string
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    private function sumExpenseSupplierInPeriod(int $branchId, int $counterpartyId, Carbon $from, Carbon $to): string
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * Дебиторка: начальный долг из карточки + реализации − оплаты − возвраты до даты (сальдо «нам должны»).
     */
    private function buyerOpeningBalance(int $branchId, Counterparty $counterparty, array $buyerAliases, Carbon $from): string
    {
        $carry = $this->openingDebtString($counterparty->opening_debt_as_buyer);
        if ($buyerAliases === []) {
            return $carry;
        }

        $before = $from->toDateString();

        $sales = $this->sumLegalSalesBefore($branchId, $buyerAliases, $before);
        $payments = $this->sumIncomeClientBefore($branchId, $counterparty->id, $before);
        $returns = $this->sumCustomerReturnsBefore($branchId, $buyerAliases, $before);

        $fromDocs = bcsub(bcsub($sales, $payments, 2), $returns, 2);

        return bcadd($carry, $fromDocs, 2);
    }

    /**
     * Кредиторка: начальный долг из карточки + закупки − возвраты поставщику − оплаты до даты (сальдо «мы должны»).
     */
    private function supplierOpeningBalance(int $branchId, Counterparty $counterparty, array $supplierAliases, Carbon $from): string
    {
        $carry = $this->openingDebtString($counterparty->opening_debt_as_supplier);
        if ($supplierAliases === []) {
            return $carry;
        }

        $before = $from->toDateString();

        $purchases = $this->sumPurchasesBefore($branchId, $supplierAliases, $before);
        $returns = $this->sumPurchaseReturnsBefore($branchId, $supplierAliases, $before);
        $payments = $this->sumExpenseSupplierBefore($branchId, $counterparty->id, $before);

        $fromDocs = bcsub(bcsub($purchases, $returns, 2), $payments, 2);

        return bcadd($carry, $fromDocs, 2);
    }

    private function openingDebtString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function sumLegalSalesBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($sales as $sale) {
            $total = bcadd($total, $this->sumLines($sale->lines), 2);
        }

        return $total;
    }

    private function sumIncomeClientBefore(int $branchId, int $counterpartyId, string $beforeDate): string
    {
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->where('occurred_on', '<', $beforeDate)
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    private function sumCustomerReturnsBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $returns = CustomerReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($returns as $ret) {
            $total = bcadd($total, $this->sumLines($ret->lines), 2);
        }

        return $total;
    }

    private function sumPurchasesBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $docs = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    private function sumPurchaseReturnsBefore(int $branchId, array $aliases, string $beforeDate): string
    {
        $total = '0';
        $docs = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $aliases)
            ->where('document_date', '<', $beforeDate)
            ->with('lines')
            ->get();

        foreach ($docs as $doc) {
            $total = bcadd($total, $this->sumLines($doc->lines), 2);
        }

        return $total;
    }

    private function sumExpenseSupplierBefore(int $branchId, int $counterpartyId, string $beforeDate): string
    {
        $sum = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->where('occurred_on', '<', $beforeDate)
            ->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * @param  iterable<int, LegalEntitySaleLine|CustomerReturnLine|PurchaseReceiptLine|PurchaseReturnLine>  $lines
     */
    private function sumLines(iterable $lines): string
    {
        $t = '0';
        foreach ($lines as $line) {
            if ($line->line_sum !== null) {
                $t = bcadd($t, (string) $line->line_sum, 2);
            }
        }

        return $t;
    }

    private function buyerPeriodRows(int $branchId, int $counterpartyId, array $buyerAliases, Carbon $from, Carbon $to): Collection
    {
        if ($buyerAliases === []) {
            return collect();
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $rows = collect();

        $sales = LegalEntitySale::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            $amt = $this->sumLines($sale->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $sale->document_date->format('Y-m-d').'-1-'.$sale->id,
                'date' => $sale->document_date,
                'kind' => 'sale',
                'title' => 'Продажа',
                'detail' => 'Документ № '.$sale->id,
                'debit' => $amt,
                'credit' => null,
            ]);
        }

        $returns = CustomerReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('buyer_name', $buyerAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($returns as $ret) {
            $amt = $this->sumLines($ret->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $ret->document_date->format('Y-m-d').'-2-'.$ret->id,
                'date' => $ret->document_date,
                'kind' => 'return',
                'title' => 'Возврат',
                'detail' => 'Документ № '.$ret->id,
                'debit' => null,
                'credit' => $amt,
            ]);
        }

        $payments = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        foreach ($payments as $p) {
            $amt = number_format((float) $p->amount, 2, '.', '');
            $rows->push([
                'sort' => $p->occurred_on->format('Y-m-d').'-3-'.$p->id,
                'date' => $p->occurred_on,
                'kind' => 'payment',
                'title' => 'Перевод денег',
                'detail' => trim((string) $p->comment) !== '' ? $p->comment : '—',
                'debit' => null,
                'credit' => $amt,
            ]);
        }

        return $rows->sortBy('sort')->values();
    }

    private function supplierPeriodRows(int $branchId, int $counterpartyId, array $supplierAliases, Carbon $from, Carbon $to): Collection
    {
        if ($supplierAliases === []) {
            return collect();
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $rows = collect();

        $purchases = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($purchases as $doc) {
            $amt = $this->sumLines($doc->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $doc->document_date->format('Y-m-d').'-1-'.$doc->id,
                'date' => $doc->document_date,
                'kind' => 'purchase',
                'title' => 'Закуп у поставщика',
                'detail' => 'Документ № '.$doc->id,
                'debit' => null,
                'credit' => $amt,
            ]);
        }

        $purchaseReturns = PurchaseReturn::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_name', $supplierAliases)
            ->whereBetween('document_date', [$fromStr, $toStr])
            ->with('lines')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        foreach ($purchaseReturns as $ret) {
            $amt = $this->sumLines($ret->lines);
            if (bccomp($amt, '0', 2) === 0) {
                continue;
            }
            $rows->push([
                'sort' => $ret->document_date->format('Y-m-d').'-2-'.$ret->id,
                'date' => $ret->document_date,
                'kind' => 'purchase_return',
                'title' => 'Возврат поставщику',
                'detail' => 'Документ № '.$ret->id,
                'debit' => $amt,
                'credit' => null,
            ]);
        }

        $payments = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('counterparty_id', $counterpartyId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->whereBetween('occurred_on', [$fromStr, $toStr])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        foreach ($payments as $p) {
            $amt = number_format((float) $p->amount, 2, '.', '');
            $rows->push([
                'sort' => $p->occurred_on->format('Y-m-d').'-3-'.$p->id,
                'date' => $p->occurred_on,
                'kind' => 'payment',
                'title' => 'Перевод денег поставщику',
                'detail' => trim((string) $p->comment) !== '' ? $p->comment : '—',
                'debit' => $amt,
                'credit' => null,
            ]);
        }

        return $rows->sortBy('sort')->values();
    }

    private function sumBuyerDelta(Collection $rows): string
    {
        $t = '0';
        foreach ($rows as $r) {
            if ($r['debit'] !== null) {
                $t = bcadd($t, $r['debit'], 2);
            }
            if ($r['credit'] !== null) {
                $t = bcsub($t, $r['credit'], 2);
            }
        }

        return $t;
    }

    private function sumSupplierDelta(Collection $rows): string
    {
        $t = '0';
        foreach ($rows as $r) {
            if ($r['credit'] !== null) {
                $t = bcadd($t, $r['credit'], 2);
            }
            if ($r['debit'] !== null) {
                $t = bcsub($t, $r['debit'], 2);
            }
        }

        return $t;
    }
}
