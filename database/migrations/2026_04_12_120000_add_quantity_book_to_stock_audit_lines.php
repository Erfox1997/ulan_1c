<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_audit_lines', function (Blueprint $table) {
            $table->decimal('quantity_book', 18, 4)->nullable()->after('good_id');
            $table->decimal('unit_cost_snapshot', 18, 4)->nullable()->after('quantity_book');
        });
    }

    public function down(): void
    {
        Schema::table('stock_audit_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_book', 'unit_cost_snapshot']);
        });
    }
};
