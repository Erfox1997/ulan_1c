<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('good_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opening_stock_balance_id')->nullable()->constrained('opening_stock_balances')->nullOnDelete();
            $table->decimal('quantity_requested', 14, 4);
            $table->decimal('quantity_snapshot', 14, 4);
            $table->decimal('min_stock_snapshot', 14, 4)->nullable();
            $table->string('oem_snapshot', 512)->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id']);
        });

        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'good_id')) {
            $rows = DB::table('purchase_requests')->orderBy('id')->get();
            foreach ($rows as $row) {
                DB::table('purchase_request_lines')->insert([
                    'purchase_request_id' => $row->id,
                    'good_id' => $row->good_id,
                    'warehouse_id' => $row->warehouse_id,
                    'opening_stock_balance_id' => $row->opening_stock_balance_id,
                    'quantity_requested' => $row->quantity_snapshot,
                    'quantity_snapshot' => $row->quantity_snapshot,
                    'min_stock_snapshot' => $row->min_stock_snapshot,
                    'oem_snapshot' => $row->oem_snapshot,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropForeign(['good_id']);
                $table->dropForeign(['warehouse_id']);
                $table->dropForeign(['opening_stock_balance_id']);
                $table->dropColumn([
                    'good_id',
                    'warehouse_id',
                    'opening_stock_balance_id',
                    'quantity_snapshot',
                    'min_stock_snapshot',
                    'oem_snapshot',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_lines');
        if (Schema::hasTable('purchase_requests') && ! Schema::hasColumn('purchase_requests', 'good_id')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->foreignId('good_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('opening_stock_balance_id')->nullable()->constrained('opening_stock_balances')->nullOnDelete();
                $table->decimal('quantity_snapshot', 14, 4)->nullable();
                $table->decimal('min_stock_snapshot', 14, 4)->nullable();
                $table->string('oem_snapshot', 512)->nullable();
            });
        }
    }
};
