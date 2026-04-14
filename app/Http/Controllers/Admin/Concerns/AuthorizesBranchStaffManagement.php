<?php

namespace App\Http\Controllers\Admin\Concerns;

trait AuthorizesBranchStaffManagement
{
    protected function ensureCanManageBranchStaff(): void
    {
        $u = auth()->user();
        if (! $u || ! $u->branch_id) {
            abort(403);
        }

        if ($u->branch_role_id === null) {
            return;
        }

        $role = $u->branchRole;
        if ($role && $role->is_full_access) {
            return;
        }

        abort(403, 'Этот раздел доступен только главному администратору филиала (без ограничивающей роли или с ролью «полный доступ»).');
    }
}
