<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesBranchStaffManagement;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeAdvanceController extends Controller
{
    use AuthorizesBranchStaffManagement;

    public function index(): View
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $advances = EmployeeAdvance::query()
            ->where('branch_id', $branchId)
            ->with('employee')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $employees = $this->employeesForBranch();

        return view('admin.payroll.advances.index', [
            'pageTitle' => 'Авансы',
            'advances' => $advances,
            'employees' => $employees,
        ]);
    }

    public function create(): View
    {
        $this->ensureCanManageBranchStaff();

        $employees = $this->employeesForBranch();

        return view('admin.payroll.advances.create', [
            'pageTitle' => 'Новый аванс',
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

        EmployeeAdvance::query()->create([
            'branch_id' => $branchId,
            'employee_id' => $validated['employee_id'],
            'entry_date' => $validated['entry_date'],
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('admin.payroll.advances.index')
            ->with('status', 'Аванс записан.');
    }

    public function edit(EmployeeAdvance $advance): View
    {
        $this->ensureCanManageBranchStaff();

        $employees = $this->employeesForBranch();

        return view('admin.payroll.advances.edit', [
            'pageTitle' => 'Аванс',
            'advance' => $advance->load('employee'),
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, EmployeeAdvance $advance): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('branch_id', $branchId)],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $advance->update([
            'employee_id' => $validated['employee_id'],
            'entry_date' => $validated['entry_date'],
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('admin.payroll.advances.edit', $advance)
            ->with('status', 'Данные сохранены.');
    }

    public function destroy(EmployeeAdvance $advance): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $advance->delete();

        return redirect()
            ->route('admin.payroll.advances.index')
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
