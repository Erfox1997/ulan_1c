<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('opening_stock_balances', 'warehouse_id')) {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained('warehouses')->cascadeOnDelete();
            });
        }

        $branchIds = DB::table('opening_stock_balances')
            ->whereNull('warehouse_id')
            ->distinct()
            ->pluck('branch_id');

        foreach ($branchIds as $branchId) {
            $warehouseId = DB::table('warehouses')
                ->where('branch_id', $branchId)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');

            if ($warehouseId) {
                DB::table('opening_stock_balances')
                    ->where('branch_id', $branchId)
                    ->whereNull('warehouse_id')
                    ->update(['warehouse_id' => $warehouseId]);
            }
        }

        DB::table('opening_stock_balances')->whereNull('warehouse_id')->delete();

        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->dropForeign(['good_id']);
            });
        });

        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->dropUnique(['branch_id', 'good_id']);
            });
        });

        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->unique(['warehouse_id', 'good_id']);
            });
        });

        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->foreign('good_id')->references('id')->on('goods')->cascadeOnDelete();
            });
        });
    }

    public function down(): void
    {
        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->dropForeign(['good_id']);
            });
        });

        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->dropUnique(['warehouse_id', 'good_id']);
            });
        });

        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->unique(['branch_id', 'good_id']);
            });
        });

        $this->trySchema(function () {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->foreign('good_id')->references('id')->on('goods')->cascadeOnDelete();
            });
        });

        if (Schema::hasColumn('opening_stock_balances', 'warehouse_id')) {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            });
        }
    }

    private function trySchema(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Уже применено или неприменимо (повторный прогон / частичное состояние БД).
        }
    }
};
