<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesBranchStaffManagement;
use App\Http\Controllers\Controller;
use App\Models\BranchRole;
use App\Models\BranchRolePermission;
use App\Models\BranchUserPermission;
use App\Models\Employee;
use App\Models\User;
use App\Services\BranchPermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchRoleController extends Controller
{
    use AuthorizesBranchStaffManagement;

    public function __construct(
        private readonly BranchPermissionCatalog $catalog
    ) {}

    public function index(Request $request): View
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;
        $roles = BranchRole::query()
            ->where('branch_id', $branchId)
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $employees = Employee::query()
            ->where('branch_id', $branchId)
            ->with(['user.branchRole', 'user.branchUserPermissions'])
            ->orderBy('full_name')
            ->get();

        $selectedEmployee = null;
        $eid = $request->integer('employee');
        if ($eid > 0) {
            $selectedEmployee = Employee::query()
                ->where('branch_id', $branchId)
                ->with(['user.branchRole', 'user.branchUserPermissions'])
                ->find($eid);
        }

        $usersWithoutEmployee = User::query()
            ->where('branch_id', $branchId)
            ->where('is_super_admin', false)
            ->whereDoesntHave('employee')
            ->with('branchRole')
            ->orderBy('name')
            ->get();

        $catalogItems = $this->catalog->items();

        return view('admin.settings.responsible.index', [
            'pageTitle' => 'Ответственные лица и доступы',
            'roles' => $roles,
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'usersWithoutEmployee' => $usersWithoutEmployee,
            'catalogItems' => $catalogItems,
        ]);
    }

    public function edit(BranchRole $branchRole): View
    {
        $this->ensureCanManageBranchStaff();
        $this->ensureSameBranch($branchRole);

        $branchRole->load('permissions');
        $catalogItems = $this->catalog->items();
        $selected = $branchRole->permissions->pluck('route_pattern')->all();

        return view('admin.settings.responsible.edit', [
            'pageTitle' => 'Роль: '.$branchRole->name,
            'role' => $branchRole,
            'catalogItems' => $catalogItems,
            'selectedPatterns' => $selected,
            'canDelete' => ! $branchRole->users()->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branch_roles', 'name')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'is_full_access' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ]);

        $isFull = $request->boolean('is_full_access');

        $perms = $request->input('permissions', []);
        if (! $isFull && count($perms) === 0) {
            return back()->withInput()->withErrors([
                'permissions' => 'Отметьте хотя бы один раздел или включите «Полный доступ ко всем разделам».',
            ]);
        }

        $role = DB::transaction(function () use ($validated, $branchId, $isFull, $request) {
            $role = BranchRole::query()->create([
                'branch_id' => $branchId,
                'name' => $validated['name'],
                'is_full_access' => $isFull,
            ]);

            if (! $isFull) {
                $this->syncPermissions($role, $request->input('permissions', []));
            }

            return $role;
        });

        return redirect()
            ->route('admin.settings.responsible.roles.edit', $role)
            ->with('status', 'Роль «'.$role->name.'» создана.');
    }

    public function update(Request $request, BranchRole $branchRole): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();
        $this->ensureSameBranch($branchRole);

        $branchId = (int) auth()->user()->branch_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branch_roles', 'name')->where(fn ($q) => $q->where('branch_id', $branchId))->ignore($branchRole->id),
            ],
            'is_full_access' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ]);

        $isFull = $request->boolean('is_full_access');

        $perms = $request->input('permissions', []);
        if (! $isFull && count($perms) === 0) {
            return back()->withInput()->withErrors([
                'permissions' => 'Отметьте хотя бы один раздел или включите «Полный доступ ко всем разделам».',
            ]);
        }

        DB::transaction(function () use ($validated, $branchRole, $isFull, $request) {
            $branchRole->update([
                'name' => $validated['name'],
                'is_full_access' => $isFull,
            ]);

            $branchRole->permissions()->delete();

            if (! $isFull) {
                $this->syncPermissions($branchRole, $request->input('permissions', []));
            }
        });

        return redirect()
            ->route('admin.settings.responsible.roles.edit', $branchRole)
            ->with('status', 'Роль сохранена.');
    }

    public function destroy(BranchRole $branchRole): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();
        $this->ensureSameBranch($branchRole);

        if ($branchRole->users()->exists()) {
            return redirect()
                ->route('admin.settings.responsible')
                ->withErrors(['role' => 'Нельзя удалить роль: к ней привязаны пользователи. Снимите роль у пользователей.']);
        }

        $branchRole->permissions()->delete();
        $branchRole->delete();

        return redirect()
            ->route('admin.settings.responsible')
            ->with('status', 'Роль удалена.');
    }

    public function updateUserRole(Request $request, User $branchUser): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $branchUser->branch_id !== $branchId || $branchUser->is_super_admin) {
            abort(403);
        }

        $validated = $request->validate([
            'branch_role_id' => ['nullable', 'integer', 'exists:branch_roles,id'],
        ]);

        $roleId = $validated['branch_role_id'] ?? null;

        if ($roleId !== null) {
            $role = BranchRole::query()
                ->where('branch_id', $branchId)
                ->whereKey($roleId)
                ->firstOrFail();
            $branchUser->branch_role_id = $role->id;
        } else {
            $branchUser->branch_role_id = null;
        }

        $branchUser->save();

        return redirect()
            ->route('admin.settings.responsible')
            ->with('status', 'Роль пользователя обновлена.');
    }

    public function updateEmployeeAccess(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureCanManageBranchStaff();

        $branchId = (int) auth()->user()->branch_id;
        if ((int) $employee->branch_id !== $branchId) {
            abort(404);
        }

        $validated = $request->validate([
            'branch_role_id' => ['nullable', 'integer', Rule::exists('branch_roles', 'id')->where('branch_id', $branchId)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ]);

        $user = $employee->user;
        $user->branch_role_id = $validated['branch_role_id'] ?? null;
        $user->save();

        $perms = array_unique(array_filter(array_map('strval', $validated['permissions'] ?? [])));

        DB::transaction(function () use ($user, $perms) {
            $user->branchUserPermissions()->delete();
            foreach ($perms as $p) {
                BranchUserPermission::query()->create([
                    'user_id' => $user->id,
                    'route_pattern' => $p,
                ]);
            }
        });

        return redirect()
            ->route('admin.settings.responsible', ['employee' => $employee->id])
            ->with('status', 'Доступ для сотрудника сохранён.');
    }

    private function syncPermissions(BranchRole $role, array $patterns): void
    {
        $patterns = array_unique(array_filter(array_map('strval', $patterns)));

        foreach ($patterns as $p) {
            BranchRolePermission::query()->create([
                'branch_role_id' => $role->id,
                'route_pattern' => $p,
            ]);
        }
    }

    private function ensureSameBranch(BranchRole $branchRole): void
    {
        if ((int) $branchRole->branch_id !== (int) auth()->user()->branch_id) {
            abort(404);
        }
    }

}
