<?php

namespace App\Providers;

use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\GoodsCharacteristicsService;
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

        $mayAccessRouteComposer = function ($view) {
            $access = app(BranchAccessService::class);
            $view->with('mayAccessRoute', function (string $routeName) use ($access) {
                $user = auth()->user();
                if (! $user) {
                    return false;
                }

                return $access->userMayAccessRoute($user, $routeName, request());
            });
        };
        View::composer([
            'admin.service-sales.sell',
            'admin.service-sales.sell-lines',
            'admin.service-sales.edit-request',
            'admin.service-sales.requests',
            'admin.service-sales.fulfill',
        ], $mayAccessRouteComposer);

        View::composer('admin.partials.sidebar', function ($view) {
            $user = auth()->user();
            $base = config('branch_menu', []);
            $menu = ($user && $user->branch_id)
                ? app(\App\Services\BranchAccessService::class)->filterMenuForUser($user, $base)
                : $base;
            $goodsCharacteristicsIncompleteCount = 0;
            if ($user && $user->branch_id) {
                $branchId = (int) $user->branch_id;
                $attrKey = 'goods_characteristics_incomplete_count';
                $request = request();
                if (! $request->attributes->has($attrKey)) {
                    $request->attributes->set(
                        $attrKey,
                        app(GoodsCharacteristicsService::class)->countIncompleteForBranchDefaultWarehouse($branchId)
                    );
                }
                $goodsCharacteristicsIncompleteCount = (int) $request->attributes->get($attrKey);
            }
            $view->with([
                'menu' => $menu,
                'goodsCharacteristicsIncompleteCount' => $goodsCharacteristicsIncompleteCount,
            ]);
        });
    }
}
