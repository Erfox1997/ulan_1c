<?php

namespace App\Http\Middleware;

use App\Services\BranchAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchRouteAccess
{
    public function __construct(
        private readonly BranchAccessService $access
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->branch_id) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! $this->access->userMayAccessRoute($user, $routeName, $request)) {
            abort(403, 'Нет доступа к этому разделу. Обратитесь к администратору филиала.');
        }

        return $next($request);
    }
}
