<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('branchUser', function ($value) {
            return User::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->where('is_super_admin', false)
                ->whereKey($value)
                ->firstOrFail();
        });

        View::composer('admin.partials.sidebar', function ($view) {
            $user = auth()->user();
            $base = config('branch_menu', []);
            $menu = ($user && $user->branch_id)
                ? app(\App\Services\BranchAccessService::class)->filterMenuForUser($user, $base)
                : $base;
            $view->with('menu', $menu);
        });
    }
}
