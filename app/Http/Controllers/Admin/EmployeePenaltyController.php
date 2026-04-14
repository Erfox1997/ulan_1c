<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesBranchStaffManagement;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePenalty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeePenaltyController extends Controller
{
    use AuthorizesBranchStaffManagement;

    public function index(): View
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $penalties = EmployeePenalty::query()
            ->where('branch_id', $branchId)
            ->with('employee')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $employees = $this->employeesForBranch();

        return view('admin.payroll.penalties.index', [
            'pageTitle' => 'Штрафы',
            'penalties' => $penalties,
            'employees' => $employees,
        ]);
    }

    public function create(): View
    {
        $this->ensureCanManageBranchStaff();

        $employees = $this->employeesForBranch();

        return view('admin.payroll.penalties.create', [
            'pageTitle' => 'Новый штраф',
            'employees' => $employees,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('branch_id', $branchId)],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        EmployeePenalty::query()->create([
            'branch_id' => $branchId,
            'employee_id' => $validated['employee_id'],
            'entry_date' => $validated['entry_date'],
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('admin.payroll.penalties.index')
            ->with('status', 'Штраф записан.');
    }

    public function edit(EmployeePenalty $penalty): View
    {
        $this->ensureCanManageBranchStaff();

        $employees = $this->employeesForBranch();

        return view('admin.payroll.penalties.edit', [
            'pageTitle' => 'Штраф',
            'penalty' => $penalty->load('employee'),
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, EmployeePenalty $penalty): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('branch_id', $branchId)],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $penalty->update([
            'employee_id' => $validated['employee_id'],
            'entry_date' => $validated['entry_date'],
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('admin.payroll.penalties.edit', $penalty)
            ->with('status', 'Данные сохранены.');
    }

    public function destroy(EmployeePenalty $penalty): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $penalty->delete();

        return redirect()
            ->route('admin.payroll.penalties.index')
            ->with('status', 'Запись удалена.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    private function employeesForBranch()
    {
        return Employee::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('full_name')
            ->get();
    }
}
