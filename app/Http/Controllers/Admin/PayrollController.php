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

    public function index(Request $request, CashLedgerService $ledger, PayrollCalculationService $calculator): View
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

        $calculationLines = [];
        $payoutByEmployee = collect();

        if ($periodFromCarbon->lte($periodToCarbon)) {
            $calculationLines = $calculator->linesForPeriod($branchId, $periodFromCarbon, $periodToCarbon);

            $payoutByEmployee = PayrollPayout::query()
                ->where('branch_id', $branchId)
                ->whereDate('period_from', $periodFromCarbon->toDateString())
                ->whereDate('period_to', $periodToCarbon->toDateString())
                ->get()
                ->keyBy('employee_id');
        }

        $accounts = $ledger->accountsForBranch($branchId);

        return view('admin.payroll.index', [
            'pageTitle' => 'Зарплата',
            'employeeCount' => $employeeCount,
            'advanceSum' => $advanceSum,
            'penaltySum' => $penaltySum,
            'periodFrom' => $periodFromCarbon->toDateString(),
            'periodTo' => $periodToCarbon->toDateString(),
            'periodInvalid' => $periodFromCarbon->gt($periodToCarbon),
            'calculationLines' => $calculationLines,
            'payoutByEmployee' => $payoutByEmployee,
            'accounts' => $accounts,
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
