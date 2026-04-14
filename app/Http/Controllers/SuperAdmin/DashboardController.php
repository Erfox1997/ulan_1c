<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('superadmin.dashboard', [
            'branchesCount' => Branch::count(),
            'branchAdminsCount' => User::query()->where('is_super_admin', false)->whereNotNull('branch_id')->count(),
        ]);
    }
}
