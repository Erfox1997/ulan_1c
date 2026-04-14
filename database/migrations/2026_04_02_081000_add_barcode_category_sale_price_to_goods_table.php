<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->after('name');
            $table->string('category', 120)->nullable()->after('barcode');
            $table->decimal('sale_price', 18, 2)->nullable()->after('unit');
        });

        Schema::table('goods', function (Blueprint $table) {
            $table->unique(['branch_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'barcode']);
        });

        Schema::table('goods', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'category', 'sale_price']);
        });
    }
};
