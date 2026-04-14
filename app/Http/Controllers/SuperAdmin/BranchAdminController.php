<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class BranchAdminController extends Controller
{
    public function index(): View
    {
        $admins = User::query()
            ->with('branch')
            ->where('is_super_admin', false)
            ->whereNotNull('branch_id')
            ->orderBy('name')
            ->paginate(20);

        return view('superadmin.admins.index', compact('admins'));
    }

    public function create(): View
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();

        return view('superadmin.admins.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'branch_id' => (int) $validated['branch_id'],
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('superadmin.admins.index')
            ->with('status', 'Администратор филиала создан.');
    }
}
