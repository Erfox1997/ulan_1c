<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BranchAccessService
{
    public function userMayAccessRoute(User $user, ?string $routeName, Request $request): bool
    {
        if (! $user->branch_id || $user->is_super_admin) {
            return true;
        }

        if ($routeName === null || $routeName === '') {
            return false;
        }

        $user->loadMissing(['branchRole.permissions', 'branchUserPermissions']);

        $individual = $user->branchUserPermissions->pluck('route_pattern')->all();

        if ($user->branch_role_id === null) {
            if ($individual === []) {
                return true;
            }

            return $this->matchRoutePatterns($routeName, $request, $individual);
        }

        $role = $user->branchRole;
        if (! $role) {
            return $this->matchRoutePatterns($routeName, $request, $individual);
        }

        $role->loadMissing('permissions');

        if ($role->is_full_access) {
            return true;
        }

        $rolePatterns = $role->permissions->pluck('route_pattern')->all();
        $merged = array_unique(array_merge($rolePatterns, $individual));

        return $this->matchRoutePatterns($routeName, $request, $merged);
    }

    /**
     * @param  array<int, array<string, mixed>>  $menu
     * @return array<int, array<string, mixed>>
     */
    public function filterMenuForUser(User $user, array $menu): array
    {
        if (! $user->branch_id || $user->is_super_admin) {
            return $menu;
        }

        $user->loadMissing(['branchRole.permissions', 'branchUserPermissions']);

        $individual = $user->branchUserPermissions->pluck('route_pattern')->all();

        if ($user->branch_role_id === null) {
            if ($individual === []) {
                return $menu;
            }

            return $this->filterMenuByPatterns($menu, $individual);
        }

        $role = $user->branchRole;
        if (! $role) {
            return $individual === [] ? [] : $this->filterMenuByPatterns($menu, $individual);
        }

        $role->loadMissing('permissions');

        if ($role->is_full_access) {
            return $menu;
        }

        $rolePatterns = $role->permissions->pluck('route_pattern')->all();
        $merged = array_unique(array_merge($rolePatterns, $individual));

        return $this->filterMenuByPatterns($menu, $merged);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchRoutePatterns(string $routeName, Request $request, array $patterns): bool
    {
        if ($routeName === 'admin.placeholder') {
            $key = (string) $request->route('key', '');

            return collect($patterns)->contains(fn (string $p) => $p === 'placeholder:'.$key);
        }

        // Выплата зарплаты — тот же доступ, что и к странице «Зарплата» (точное правило admin.payroll).
        if ($routeName === 'admin.payroll.payout' && in_array('admin.payroll', $patterns, true)) {
            return true;
        }

        foreach ($patterns as $p) {
            if (Str::startsWith($p, 'placeholder:')) {
                continue;
            }
            if (Str::is($p, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $menu
     * @param  list<string>  $patterns
     * @return array<int, array<string, mixed>>
     */
    private function filterMenuByPatterns(array $menu, array $patterns): array
    {
        $out = [];
        foreach ($menu as $section) {
            if (($section['type'] ?? null) === 'route') {
                $route = $section['route'] ?? '';
                if ($this->routeNameMatchesPatterns($route, $patterns)) {
                    $out[] = $section;
                }

                continue;
            }

            $children = [];
            foreach ($section['children'] ?? [] as $child) {
                if ($this->childMatches($child, $patterns)) {
                    $children[] = $child;
                }
            }
            if ($children !== []) {
                $section['children'] = $children;
                $out[] = $section;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function childMatches(array $child, array $patterns): bool
    {
        if (isset($child['route'])) {
            return $this->routeNameMatchesPatterns($child['route'], $patterns);
        }

        if (isset($child['key'])) {
            return collect($patterns)->contains(fn (string $p) => $p === 'placeholder:'.$child['key']);
        }

        return false;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function routeNameMatchesPatterns(string $routeName, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if (Str::startsWith($p, 'placeholder:')) {
                continue;
            }
            if (Str::is($p, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
