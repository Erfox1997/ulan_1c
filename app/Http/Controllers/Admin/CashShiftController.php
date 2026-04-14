<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashShiftRequest;
use App\Http\Requests\StoreCashShiftRequest;
use App\Models\CashShift;
use App\Models\OrganizationBankAccount;
use App\Services\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;

class CashShiftController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    public function store(StoreCashShiftRequest $request): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;

        if (CashShift::query()->where('branch_id', $branchId)->where('user_id', $request->user()->id)->open()->exists()) {
            return redirect()
                ->route('admin.dashboard')
                ->withErrors(['opening_by_account' => 'У вас уже есть открытая смена. Сначала закройте её.']);
        }

        $allowedIds = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($allowedIds === []) {
            return redirect()
                ->route('admin.dashboard')
                ->withErrors(['opening_by_account' => 'Нет счетов организаций филиала — заведите счета в «Данные организации», затем откройте смену.']);
        }

        $rawByAccount = $request->validated('opening_by_account', []);
        $stored = [];
        $sum = '0';

        foreach ($allowedIds as $accountId) {
            $key = (string) $accountId;
            $raw = $rawByAccount[$key] ?? $rawByAccount[$accountId] ?? null;
            if ($raw === null || trim((string) $raw) === '') {
                return redirect()
                    ->route('admin.dashboard')
                    ->withInput()
                    ->withErrors(["opening_by_account.{$key}" => 'Укажите сумму на начало смены по этому счёту.']);
            }
            $parsed = $this->openingBalanceService->parseOptionalMoney($raw);
            if ($parsed === null || (float) $parsed < 0) {
                return redirect()
                    ->route('admin.dashboard')
                    ->withInput()
                    ->withErrors(["opening_by_account.{$key}" => 'Введите неотрицательное число (сом).']);
            }
            $stored[$key] = (string) $parsed;
            $sum = bcadd($sum, (string) $parsed, 2);
        }

        CashShift::query()->create([
            'branch_id' => $branchId,
            'user_id' => (int) $request->user()->id,
            'business_date' => now()->toDateString(),
            'opened_at' => now(),
            'opening_cash' => $sum,
            'opening_by_account' => $stored,
            'open_note' => $request->validated('open_note') ?: null,
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Смена открыта. Суммы по счетам на начало смены сохранены.');
    }

    public function close(CloseCashShiftRequest $request, CashShift $cashShift): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;

        if ((int) $cashShift->branch_id !== $branchId || $cashShift->closed_at !== null) {
            abort(403);
        }

        if ((int) $cashShift->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $allowedIds = OrganizationBankAccount::query()
            ->whereHas('organization', fn ($q) => $q->where('branch_id', $branchId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($allowedIds === []) {
            return redirect()
                ->route('admin.dashboard')
                ->withErrors(['closing_by_account' => 'Нет счетов организаций филиала — нельзя зафиксировать закрытие по счетам.']);
        }

        $rawByAccount = $request->validated('closing_by_account', []);
        $stored = [];
        $sum = '0';

        foreach ($allowedIds as $accountId) {
            $key = (string) $accountId;
            $raw = $rawByAccount[$key] ?? $rawByAccount[$accountId] ?? null;
            if ($raw === null || trim((string) $raw) === '') {
                return redirect()
                    ->route('admin.dashboard')
                    ->withInput()
                    ->withErrors(["closing_by_account.{$key}" => 'Укажите сумму при закрытии по этому счёту.']);
            }
            $parsed = $this->openingBalanceService->parseOptionalMoney($raw);
            if ($parsed === null || (float) $parsed < 0) {
                return redirect()
                    ->route('admin.dashboard')
                    ->withInput()
                    ->withErrors(["closing_by_account.{$key}" => 'Введите неотрицательное число (сом).']);
            }
            $stored[$key] = (string) $parsed;
            $sum = bcadd($sum, (string) $parsed, 2);
        }

        $cashShift->update([
            'closed_at' => now(),
            'closing_cash' => $sum,
            'closing_by_account' => $stored,
            'close_note' => $request->validated('close_note') ?: null,
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Смена закрыта. Суммы по счетам при закрытии сохранены.');
    }
}
