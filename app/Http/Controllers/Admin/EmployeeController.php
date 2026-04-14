<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesBranchStaffManagement;
use App\Http\Controllers\Controller;
use App\Models\BranchRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    use AuthorizesBranchStaffManagement;

    public function index(): View
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $employees = Employee::query()
            ->where('branch_id', $branchId)
            ->with(['user.branchRole'])
            ->orderBy('full_name')
            ->get();

        return view('admin.settings.employees.index', [
            'pageTitle' => 'Сотрудники',
            'employees' => $employees,
        ]);
    }

    public function create(): View
    {
        $this->ensureCanManageBranchStaff();

        $roles = BranchRole::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('name')
            ->get();

        return view('admin.settings.employees.create', [
            'pageTitle' => 'Новый сотрудник',
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'branch_role_id' => ['nullable', 'integer', Rule::exists('branch_roles', 'id')->where('branch_id', $branchId)],
            'salary_fixed' => ['nullable', 'numeric', 'min:0'],
            'salary_percent_goods' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'salary_percent_services' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($validated, $branchId) {
            $user = User::query()->create([
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'branch_id' => $branchId,
                'branch_role_id' => $validated['branch_role_id'] ?? null,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            Employee::query()->create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'full_name' => $validated['full_name'],
                'position' => $validated['position'] ?? null,
                'salary_fixed' => $validated['salary_fixed'] ?? null,
                'salary_percent_goods' => $validated['salary_percent_goods'] ?? null,
                'salary_percent_services' => $validated['salary_percent_services'] ?? null,
            ]);
        });

        return redirect()
            ->route('admin.settings.employees')
            ->with('status', 'Сотрудник создан. Вход по логину (e-mail) и паролю на странице входа в систему.');
    }

    public function edit(Employee $employee): View
    {
        $this->ensureCanManageBranchStaff();

        $roles = BranchRole::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->orderBy('name')
            ->get();

        return view('admin.settings.employees.edit', [
            'pageTitle' => 'Сотрудник: '.$employee->full_name,
            'employee' => $employee->load('user'),
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        if (! $request->filled('password')) {
            $request->merge(['password' => null, 'password_confirmation' => null]);
        }

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->user_id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'branch_role_id' => ['nullable', 'integer', Rule::exists('branch_roles', 'id')->where('branch_id', $branchId)],
            'salary_fixed' => ['nullable', 'numeric', 'min:0'],
            'salary_percent_goods' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'salary_percent_services' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($validated, $employee) {
            $user = $employee->user;
            $user->name = $validated['full_name'];
            $user->email = $validated['email'];
            if (filled($validated['password'] ?? null)) {
                $user->password = $validated['password'];
            }
            $user->branch_role_id = $validated['branch_role_id'] ?? null;
            $user->save();

            $employee->update([
                'full_name' => $validated['full_name'],
                'position' => $validated['position'] ?? null,
                'salary_fixed' => $validated['salary_fixed'] ?? null,
                'salary_percent_goods' => $validated['salary_percent_goods'] ?? null,
                'salary_percent_services' => $validated['salary_percent_services'] ?? null,
            ]);
        });

        return redirect()
            ->route('admin.settings.employees.edit', $employee)
            ->with('status', 'Данные сохранены.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        if ($employee->user_id === auth()->id()) {
            return redirect()
                ->route('admin.settings.employees')
                ->withErrors(['employee' => 'Нельзя удалить свою учётную запись.']);
        }

        DB::transaction(function () use ($employee) {
            $employee->user->delete();
        });

        return redirect()
            ->route('admin.settings.employees')
            ->with('status', 'Сотрудник и учётная запись удалены.');
    }
}
