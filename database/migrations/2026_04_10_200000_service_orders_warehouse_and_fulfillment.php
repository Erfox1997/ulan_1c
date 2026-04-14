<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->foreignId('retail_sale_id')->nullable()->constrained('retail_sales')->nullOnDelete();
            $table->foreignId('legal_entity_sale_id')->nullable()->constrained('legal_entity_sales')->nullOnDelete();
        });

        DB::table('service_orders')->whereIn('status', ['pending', 'in_progress'])->update(['status' => 'awaiting_fulfillment']);
        DB::table('service_orders')->where('status', 'completed')->update(['status' => 'fulfilled']);
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['retail_sale_id']);
            $table->dropForeign(['legal_entity_sale_id']);
            $table->dropColumn(['warehouse_id', 'retail_sale_id', 'legal_entity_sale_id']);
        });

        DB::table('service_orders')->where('status', 'awaiting_fulfillment')->update(['status' => 'pending']);
        DB::table('service_orders')->where('status', 'fulfilled')->update(['status' => 'completed']);
    }
};
