<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RequiresOpenCashShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankCashExpenseOtherRequest;
use App\Http\Requests\StoreBankCashExpenseSupplierRequest;
use App\Http\Requests\StoreBankCashIncomeClientRequest;
use App\Http\Requests\StoreBankCashIncomeOtherRequest;
use App\Http\Requests\StoreBankCashTransferRequest;
use App\Models\CashMovement;
use App\Models\Counterparty;
use App\Services\CashLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BankCashController extends Controller
{
    use RequiresOpenCashShift;

    public function incomeClientIndex(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->with(['ourAccount.organization', 'counterparty', 'user'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return view('admin.bank.income-client.index', [
            'movements' => $movements,
        ]);
    }

    public function incomeClientForm(CashLedgerService $ledger): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        return view('admin.bank.income-client.create', [
            'movement' => null,
            'accounts' => $ledger->accountsForBranch($branchId),
            'cpField' => $this->counterpartyAutocompleteConfig(
                $branchId,
                'buyer',
                route('admin.counterparties.search', ['for' => 'sale']),
                null
            ),
        ]);
    }

    public function editIncomeClient(CashLedgerService $ledger, int $cashMovement): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->findOrFail($cashMovement);

        return view('admin.bank.income-client.create', [
            'movement' => $movement,
            'accounts' => $ledger->accountsForBranch($branchId),
            'cpField' => $this->counterpartyAutocompleteConfig(
                $branchId,
                'buyer',
                route('admin.counterparties.search', ['for' => 'sale']),
                (int) $movement->counterparty_id
            ),
        ]);
    }

    public function storeIncomeClient(StoreBankCashIncomeClientRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        CashMovement::query()->create([
            'branch_id' => $branchId,
            'kind' => CashMovement::KIND_INCOME_CLIENT,
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'counterparty_id' => $request->validated('counterparty_id'),
            'comment' => $request->validated('comment'),
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.bank.income-client')
            ->with('status', 'Оплата от покупателя записана.');
    }

    public function updateIncomeClient(StoreBankCashIncomeClientRequest $request, int $cashMovement): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_CLIENT)
            ->findOrFail($cashMovement);

        $movement->update([
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'counterparty_id' => $request->validated('counterparty_id'),
            'comment' => $request->validated('comment'),
        ]);

        return redirect()
            ->route('admin.bank.income-client')
            ->with('status', 'Операция №'.$movement->id.' обновлена.');
    }

    public function incomeOtherIndex(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_OTHER)
            ->with(['ourAccount.organization', 'counterparty', 'user'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return view('admin.bank.income-other.index', [
            'movements' => $movements,
        ]);
    }

    public function incomeOtherForm(CashLedgerService $ledger): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        return view('admin.bank.income-other.create', [
            'movement' => null,
            'accounts' => $ledger->accountsForBranch($branchId),
            'cpField' => array_merge(
                $this->counterpartyAutocompleteConfig(
                    $branchId,
                    'other',
                    route('admin.counterparties.search', ['for' => 'other']),
                    null
                ),
                [
                    'requireSelection' => false,
                    'quickTitle' => 'Контрагент «прочее»',
                    'quickBtnAdd' => '+ Создать',
                ]
            ),
        ]);
    }

    public function editIncomeOther(CashLedgerService $ledger, int $cashMovement): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_OTHER)
            ->findOrFail($cashMovement);

        return view('admin.bank.income-other.create', [
            'movement' => $movement,
            'accounts' => $ledger->accountsForBranch($branchId),
            'cpField' => array_merge(
                $this->counterpartyAutocompleteConfig(
                    $branchId,
                    'other',
                    route('admin.counterparties.search', ['for' => 'other']),
                    $movement->counterparty_id !== null ? (int) $movement->counterparty_id : null
                ),
                [
                    'requireSelection' => false,
                    'quickTitle' => 'Контрагент «прочее»',
                    'quickBtnAdd' => '+ Создать',
                ]
            ),
        ]);
    }

    public function storeIncomeOther(StoreBankCashIncomeOtherRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        $isLoan = $request->validated('income_kind') === 'loan';
        CashMovement::query()->create([
            'branch_id' => $branchId,
            'kind' => CashMovement::KIND_INCOME_OTHER,
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'counterparty_id' => $isLoan ? $request->validated('counterparty_id') : null,
            'expense_category' => $request->validated('expense_category'),
            'comment' => $request->validated('comment'),
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.bank.income-other')
            ->with('status', $isLoan ? 'Поступление займа записано.' : 'Прочий приход записан.');
    }

    public function updateIncomeOther(StoreBankCashIncomeOtherRequest $request, int $cashMovement): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_INCOME_OTHER)
            ->findOrFail($cashMovement);

        $isLoan = $request->validated('income_kind') === 'loan';
        $movement->update([
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'counterparty_id' => $isLoan ? $request->validated('counterparty_id') : null,
            'expense_category' => $request->validated('expense_category'),
            'comment' => $request->validated('comment'),
        ]);

        return redirect()
            ->route('admin.bank.income-other')
            ->with('status', 'Операция №'.$movement->id.' обновлена.');
    }

    public function expenseSupplierIndex(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->with(['ourAccount.organization', 'counterparty', 'user'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return view('admin.bank.expense-supplier.index', [
            'movements' => $movements,
        ]);
    }

    public function expenseSupplierForm(CashLedgerService $ledger): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        return view('admin.bank.expense-supplier.create', [
            'movement' => null,
            'accounts' => $ledger->accountsForBranch($branchId),
            'cpField' => $this->counterpartyAutocompleteConfig(
                $branchId,
                'supplier',
                route('admin.counterparties.search', ['for' => 'purchase']),
                null
            ),
        ]);
    }

    public function editExpenseSupplier(CashLedgerService $ledger, int $cashMovement): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->findOrFail($cashMovement);

        return view('admin.bank.expense-supplier.create', [
            'movement' => $movement,
            'accounts' => $ledger->accountsForBranch($branchId),
            'cpField' => $this->counterpartyAutocompleteConfig(
                $branchId,
                'supplier',
                route('admin.counterparties.search', ['for' => 'purchase']),
                (int) $movement->counterparty_id
            ),
        ]);
    }

    /**
     * @return array{searchUrl: string, quickUrl: string, quickKind: string, initialId: int, initialLabel: string}
     */
    private function counterpartyAutocompleteConfig(int $branchId, string $quickKind, string $searchUrl, ?int $defaultCounterpartyId = null): array
    {
        $id = (int) old('counterparty_id', $defaultCounterpartyId ?? 0);
        $label = '';
        if ($id > 0) {
            $cp = Counterparty::query()->where('branch_id', $branchId)->find($id);
            if ($cp !== null) {
                $label = trim((string) $cp->full_name);
                if ($label === '') {
                    $label = Counterparty::buildFullName($cp->legal_form, $cp->name);
                }
            }
        }

        return [
            'searchUrl' => $searchUrl,
            'quickUrl' => route('admin.counterparties.quick-store'),
            'quickKind' => $quickKind,
            'initialId' => $id,
            'initialLabel' => $label,
        ];
    }

    public function storeExpenseSupplier(StoreBankCashExpenseSupplierRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        CashMovement::query()->create([
            'branch_id' => $branchId,
            'kind' => CashMovement::KIND_EXPENSE_SUPPLIER,
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'counterparty_id' => $request->validated('counterparty_id'),
            'comment' => $request->validated('comment'),
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.bank.expense-supplier')
            ->with('status', 'Оплата поставщику записана.');
    }

    public function updateExpenseSupplier(StoreBankCashExpenseSupplierRequest $request, int $cashMovement): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_SUPPLIER)
            ->findOrFail($cashMovement);

        $movement->update([
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'counterparty_id' => $request->validated('counterparty_id'),
            'comment' => $request->validated('comment'),
        ]);

        return redirect()
            ->route('admin.bank.expense-supplier')
            ->with('status', 'Операция №'.$movement->id.' обновлена.');
    }

    public function expenseOtherIndex(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->with(['ourAccount.organization', 'user'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return view('admin.bank.expense-other.index', [
            'movements' => $movements,
        ]);
    }

    public function expenseOtherForm(CashLedgerService $ledger): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        return view('admin.bank.expense-other.create', [
            'movement' => null,
            'accounts' => $ledger->accountsForBranch($branchId),
        ]);
    }

    public function editExpenseOther(CashLedgerService $ledger, int $cashMovement): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->findOrFail($cashMovement);

        return view('admin.bank.expense-other.create', [
            'movement' => $movement,
            'accounts' => $ledger->accountsForBranch($branchId),
        ]);
    }

    public function storeExpenseOther(StoreBankCashExpenseOtherRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        CashMovement::query()->create([
            'branch_id' => $branchId,
            'kind' => CashMovement::KIND_EXPENSE_OTHER,
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'expense_category' => $request->validated('expense_category'),
            'comment' => $request->validated('comment'),
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.bank.expense-other')
            ->with('status', 'Прочий расход записан.');
    }

    public function updateExpenseOther(StoreBankCashExpenseOtherRequest $request, int $cashMovement): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_EXPENSE_OTHER)
            ->findOrFail($cashMovement);

        $movement->update([
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'our_account_id' => $request->validated('our_account_id'),
            'expense_category' => $request->validated('expense_category'),
            'comment' => $request->validated('comment'),
        ]);

        return redirect()
            ->route('admin.bank.expense-other')
            ->with('status', 'Операция №'.$movement->id.' обновлена.');
    }

    public function transfersIndex(): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $movements = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_TRANSFER)
            ->with(['fromAccount.organization', 'toAccount.organization', 'user'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return view('admin.bank.transfers.index', [
            'movements' => $movements,
        ]);
    }

    public function transfersForm(CashLedgerService $ledger): View
    {
        $branchId = (int) auth()->user()->branch_id;

        return view('admin.bank.transfers.create', [
            'movement' => null,
            'accounts' => $ledger->accountsForBranch($branchId),
        ]);
    }

    public function editTransfer(CashLedgerService $ledger, int $cashMovement): View
    {
        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_TRANSFER)
            ->findOrFail($cashMovement);

        return view('admin.bank.transfers.create', [
            'movement' => $movement,
            'accounts' => $ledger->accountsForBranch($branchId),
        ]);
    }

    public function storeTransfer(StoreBankCashTransferRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        CashMovement::query()->create([
            'branch_id' => $branchId,
            'kind' => CashMovement::KIND_TRANSFER,
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'from_account_id' => $request->validated('from_account_id'),
            'to_account_id' => $request->validated('to_account_id'),
            'comment' => $request->validated('comment'),
            'user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.bank.transfers')
            ->with('status', 'Перевод между счетами записан.');
    }

    public function updateTransfer(StoreBankCashTransferRequest $request, int $cashMovement): RedirectResponse
    {
        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;

        $movement = CashMovement::query()
            ->where('branch_id', $branchId)
            ->where('kind', CashMovement::KIND_TRANSFER)
            ->findOrFail($cashMovement);

        $movement->update([
            'occurred_on' => $request->validated('occurred_on'),
            'amount' => $request->validated('amount'),
            'from_account_id' => $request->validated('from_account_id'),
            'to_account_id' => $request->validated('to_account_id'),
            'comment' => $request->validated('comment'),
        ]);

        return redirect()
            ->route('admin.bank.transfers')
            ->with('status', 'Операция №'.$movement->id.' обновлена.');
    }

    public function reportMovement(CashLedgerService $ledger, Request $request): View
    {
        $branchId = (int) auth()->user()->branch_id;
        [$defaultFrom, $defaultTo] = $ledger->defaultMovementPeriod();

        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : $defaultFrom;
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->startOfDay()
            : $defaultTo;
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $daily = $ledger->movementDailyByKind($branchId, $from, $to);

        return view('admin.bank.report-movement', [
            'summary' => $ledger->periodAccountSummary($branchId, $from, $to),
            'dailyRows' => $daily['rows'],
            'dailyTotals' => $daily['totals'],
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
        ]);
    }
}
