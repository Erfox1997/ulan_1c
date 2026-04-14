<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->is_super_admin) {
            return redirect()->route('superadmin.dashboard');
        }

        if (! $user->branch_id) {
            abort(403, 'Пользователю не назначен филиал.');
        }

        return $next($request);
    }
}
