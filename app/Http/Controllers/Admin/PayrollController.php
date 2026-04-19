<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesBranchStaffManagement;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RequiresOpenCashShift;
use App\Models\CashMovement;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePenalty;
use App\Models\Organization;
use App\Models\PayrollPayout;
use App\Services\CashLedgerService;
use App\Services\PayrollCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollController extends Controller
{
    use AuthorizesBranchStaffManagement;
    use RequiresOpenCashShift;

    public function index(Request $request, PayrollCalculationService $calculator): View
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $employeeCount = Employee::query()->where('branch_id', $branchId)->count();
        $advanceSum = (float) EmployeeAdvance::query()->where('branch_id', $branchId)->sum('amount');
        $penaltySum = (float) EmployeePenalty::query()->where('branch_id', $branchId)->sum('amount');

        $defaultFrom = Carbon::now()->startOfMonth()->toDateString();
        $defaultTo = Carbon::now()->endOfMonth()->toDateString();

        $periodFrom = $request->query('period_from', $defaultFrom);
        $periodTo = $request->query('period_to', $defaultTo);

        $periodFromCarbon = Carbon::parse($periodFrom)->startOfDay();
        $periodToCarbon = Carbon::parse($periodTo)->endOfDay();

        $netByEmployeeId = collect();
        $payoutByEmployeeId = collect();

        $employees = Employee::query()
            ->where('branch_id', $branchId)
            ->orderBy('full_name')
            ->get();

        if ($periodFromCarbon->lte($periodToCarbon)) {
            $lines = $calculator->linesForPeriod($branchId, $periodFromCarbon, $periodToCarbon);

            $netByEmployeeId = collect($lines)->mapWithKeys(
                fn (array $r): array => [$r['employee']->id => $r['net']]
            );

            $payoutByEmployeeId = PayrollPayout::query()
                ->where('branch_id', $branchId)
                ->whereDate('period_from', $periodFromCarbon->toDateString())
                ->whereDate('period_to', $periodToCarbon->toDateString())
                ->get()
                ->keyBy('employee_id');
        }

        return view('admin.payroll.index', [
            'pageTitle' => 'Зарплата',
            'employeeCount' => $employeeCount,
            'advanceSum' => $advanceSum,
            'penaltySum' => $penaltySum,
            'periodFrom' => $periodFromCarbon->toDateString(),
            'periodTo' => $periodToCarbon->toDateString(),
            'periodInvalid' => $periodFromCarbon->gt($periodToCarbon),
            'employees' => $employees,
            'netByEmployeeId' => $netByEmployeeId,
            'payoutByEmployeeId' => $payoutByEmployeeId,
        ]);
    }

    public function revokePayout(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $employee->branch_id !== $branchId) {
            abort(404);
        }

        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
        ]);

        $from = Carbon::parse($validated['period_from'])->startOfDay();
        $to = Carbon::parse($validated['period_to'])->endOfDay();

        $payout = PayrollPayout::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $employee->id)
            ->whereDate('period_from', $from->toDateString())
            ->whereDate('period_to', $to->toDateString())
            ->first();

        if ($payout === null) {
            return redirect()
                ->route('admin.payroll', [
                    'period_from' => $from->toDateString(),
                    'period_to' => $to->toDateString(),
                ])
                ->withErrors(['payroll' => 'За выбранный период выплата не найдена.']);
        }

        $movementId = (int) $payout->cash_movement_id;

        DB::transaction(function () use ($payout, $branchId, $movementId): void {
            $payout->delete();
            CashMovement::query()
                ->whereKey($movementId)
                ->where('branch_id', $branchId)
                ->delete();
        });

        return redirect()
            ->route('admin.payroll', [
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
            ])
            ->with('status', 'Отметка о выплате снята: статус сотрудника за период снова «не выплачено». Расход в кассе удалён.');
    }

    public function show(Request $request, Employee $employee, PayrollCalculationService $calculator, CashLedgerService $ledger): View
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $employee->branch_id !== $branchId) {
            abort(404);
        }

        $defaultFrom = Carbon::now()->startOfMonth()->toDateString();
        $defaultTo = Carbon::now()->endOfMonth()->toDateString();

        $periodFrom = $request->query('period_from', $defaultFrom);
        $periodTo = $request->query('period_to', $defaultTo);

        $periodFromCarbon = Carbon::parse($periodFrom)->startOfDay();
        $periodToCarbon = Carbon::parse($periodTo)->endOfDay();

        $periodInvalid = $periodFromCarbon->gt($periodToCarbon);

        $cardCalculationRow = null;
        $cardRetailSales = collect();
        $cardServiceLines = collect();
        $payoutRecord = null;

        if (! $periodInvalid) {
            $calculationLines = $calculator->linesForPeriod($branchId, $periodFromCarbon, $periodToCarbon);
            foreach ($calculationLines as $row) {
                if ($row['employee']->id === $employee->id) {
                    $cardCalculationRow = $row;
                    break;
                }
            }

            $payoutRecord = PayrollPayout::query()
                ->where('branch_id', $branchId)
                ->where('employee_id', $employee->id)
                ->whereDate('period_from', $periodFromCarbon->toDateString())
                ->whereDate('period_to', $periodToCarbon->toDateString())
                ->first();

            $uid = $employee->user_id !== null ? (int) $employee->user_id : null;
            $cardRetailSales = $calculator->retailSalesForEmployeePeriod(
                $branchId,
                $uid,
                $periodFromCarbon,
                $periodToCarbon
            );
            $cardServiceLines = $calculator->serviceLinesForEmployeePeriod(
                $branchId,
                $employee->id,
                $periodFromCarbon,
                $periodToCarbon
            );
        }

        $accounts = $ledger->accountsForBranch($branchId);
        $canIssuePayout = $cardCalculationRow !== null
            && (float) $cardCalculationRow['net'] > 0
            && $payoutRecord === null
            && ! $periodInvalid;

        $canPrintSlip = $cardCalculationRow !== null
            && ! $periodInvalid
            && ((float) $cardCalculationRow['net'] > 0 || $payoutRecord !== null);

        return view('admin.payroll.show', [
            'pageTitle' => 'Зарплата: '.$employee->full_name,
            'employee' => $employee,
            'periodFrom' => $periodFromCarbon->toDateString(),
            'periodTo' => $periodToCarbon->toDateString(),
            'periodInvalid' => $periodInvalid,
            'cardCalculationRow' => $cardCalculationRow,
            'cardRetailSales' => $cardRetailSales,
            'cardServiceLines' => $cardServiceLines,
            'payoutRecord' => $payoutRecord,
            'accounts' => $accounts,
            'canIssuePayout' => $canIssuePayout,
            'canPrintSlip' => $canPrintSlip,
        ]);
    }

    public function payoutForEmployee(Request $request, Employee $employee, PayrollCalculationService $calculator): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $employee->branch_id !== $branchId) {
            abort(404);
        }

        $userId = (int) auth()->id();

        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'our_account_id' => [
                'required',
                'integer',
                Rule::exists('organization_bank_accounts', 'id')->where(
                    fn ($q) => $q->whereIn(
                        'organization_id',
                        Organization::query()->where('branch_id', $branchId)->select('id')
                    )
                ),
            ],
        ]);

        $from = Carbon::parse($validated['period_from'])->startOfDay();
        $to = Carbon::parse($validated['period_to'])->endOfDay();

        $lines = $calculator->linesForPeriod($branchId, $from, $to);
        $row = collect($lines)->first(fn (array $r): bool => $r['employee']->id === $employee->id);

        if ($row === null) {
            return redirect()
                ->to(route('admin.payroll.show', $employee).'?'.http_build_query([
                    'period_from' => $from->toDateString(),
                    'period_to' => $to->toDateString(),
                ]))
                ->withErrors(['payroll' => 'Не удалось получить расчёт за период.']);
        }

        $net = (float) $row['net'];
        if ($net <= 0) {
            return redirect()
                ->to(route('admin.payroll.show', $employee).'?'.http_build_query([
                    'period_from' => $from->toDateString(),
                    'period_to' => $to->toDateString(),
                ]))
                ->withErrors(['payroll' => 'Сумма к выплате должна быть больше нуля.']);
        }

        if (PayrollPayout::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $employee->id)
            ->whereDate('period_from', $from->toDateString())
            ->whereDate('period_to', $to->toDateString())
            ->exists()) {
            return redirect()
                ->to(route('admin.payroll.show', $employee).'?'.http_build_query([
                    'period_from' => $from->toDateString(),
                    'period_to' => $to->toDateString(),
                ]))
                ->withErrors(['payroll' => 'За этот период выплата уже оформлена.']);
        }

        DB::transaction(function () use ($branchId, $employee, $from, $to, $net, $validated, $userId): void {
            $comment = sprintf(
                'Зарплата за %s — %s — %s',
                $from->format('d.m.Y').'–'.$to->format('d.m.Y'),
                $employee->full_name,
                number_format($net, 2, '.', ' ')
            );

            $movement = CashMovement::query()->create([
                'branch_id' => $branchId,
                'kind' => CashMovement::KIND_EXPENSE_OTHER,
                'occurred_on' => now()->toDateString(),
                'amount' => $net,
                'our_account_id' => $validated['our_account_id'],
                'expense_category' => 'Зарплата',
                'comment' => $comment,
                'user_id' => $userId,
            ]);

            PayrollPayout::query()->create([
                'branch_id' => $branchId,
                'employee_id' => $employee->id,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'amount' => $net,
                'cash_movement_id' => $movement->id,
            ]);
        });

        return redirect()
            ->to(route('admin.payroll.show', $employee).'?'.http_build_query([
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
            ]))
            ->with('status', 'Выплата оформлена. Можно распечатать расписку для подписи.');
    }

    public function paySlip(Request $request, Employee $employee, PayrollCalculationService $calculator): View
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $employee->branch_id !== $branchId) {
            abort(404);
        }

        $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
        ]);

        $from = Carbon::parse($request->query('period_from'))->startOfDay();
        $to = Carbon::parse($request->query('period_to'))->endOfDay();

        $lines = $calculator->linesForPeriod($branchId, $from, $to);
        $row = collect($lines)->first(fn (array $r): bool => $r['employee']->id === $employee->id);

        if ($row === null) {
            abort(404);
        }

        $payoutRecord = PayrollPayout::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $employee->id)
            ->whereDate('period_from', $from->toDateString())
            ->whereDate('period_to', $to->toDateString())
            ->first();

        $net = (float) $row['net'];
        $isPaid = $payoutRecord !== null;
        $amount = $isPaid ? (float) $payoutRecord->amount : $net;

        if ($amount <= 0) {
            abort(404);
        }

        $isPreview = ! $isPaid && $net > 0;

        $branchName = auth()->user()->branch?->name ?? 'Филиал';

        return view('admin.payroll.pay-slip', [
            'branchName' => $branchName,
            'employee' => $employee,
            'periodFrom' => $from,
            'periodTo' => $to,
            'cr' => $row,
            'amount' => $amount,
            'payoutRecord' => $payoutRecord,
            'isPreview' => $isPreview,
        ]);
    }

    public function payout(Request $request, PayrollCalculationService $calculator): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        if ($redirect = $this->redirectIfNoOpenCashShift()) {
            return $redirect;
        }

        $branchId = (int) auth()->user()->branch_id;
        $userId = (int) auth()->id();

        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'our_account_id' => [
                'required',
                'integer',
                Rule::exists('organization_bank_accounts', 'id')->where(
                    fn ($q) => $q->whereIn(
                        'organization_id',
                        Organization::query()->where('branch_id', $branchId)->select('id')
                    )
                ),
            ],
        ]);

        $from = Carbon::parse($validated['period_from'])->startOfDay();
        $to = Carbon::parse($validated['period_to'])->endOfDay();

        $lines = $calculator->linesForPeriod($branchId, $from, $to);

        $created = 0;
        $skipNonPositive = 0;
        $skipAlreadyPaid = 0;

        DB::transaction(function () use ($lines, $branchId, $from, $to, $validated, $userId, &$created, &$skipNonPositive, &$skipAlreadyPaid) {
            foreach ($lines as $row) {
                /** @var Employee $employee */
                $employee = $row['employee'];
                $net = (float) $row['net'];

                if ($net <= 0) {
                    $skipNonPositive++;

                    continue;
                }

                $exists = PayrollPayout::query()
                    ->where('branch_id', $branchId)
                    ->where('employee_id', $employee->id)
                    ->whereDate('period_from', $from->toDateString())
                    ->whereDate('period_to', $to->toDateString())
                    ->exists();

                if ($exists) {
                    $skipAlreadyPaid++;

                    continue;
                }

                $comment = sprintf(
                    'Зарплата за %s — %s — %s',
                    $from->format('d.m.Y').'–'.$to->format('d.m.Y'),
                    $employee->full_name,
                    number_format($net, 2, '.', ' ')
                );

                $movement = CashMovement::query()->create([
                    'branch_id' => $branchId,
                    'kind' => CashMovement::KIND_EXPENSE_OTHER,
                    'occurred_on' => now()->toDateString(),
                    'amount' => $net,
                    'our_account_id' => $validated['our_account_id'],
                    'expense_category' => 'Зарплата',
                    'comment' => $comment,
                    'user_id' => $userId,
                ]);

                PayrollPayout::query()->create([
                    'branch_id' => $branchId,
                    'employee_id' => $employee->id,
                    'period_from' => $from->toDateString(),
                    'period_to' => $to->toDateString(),
                    'amount' => $net,
                    'cash_movement_id' => $movement->id,
                ]);

                $created++;
            }
        });

        $msg = 'Создано операций выплаты: '.$created.'.';
        if ($skipNonPositive > 0) {
            $msg .= ' Без выплаты (нулевая или отрицательная сумма): '.$skipNonPositive.'.';
        }
        if ($skipAlreadyPaid > 0) {
            $msg .= ' Уже были выплаты за этот период: '.$skipAlreadyPaid.'.';
        }

        return redirect()
            ->route('admin.payroll', [
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
            ])
            ->with('status', $msg);
    }
}
