<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->decimal('min_sale_price', 18, 2)->nullable()->after('is_service');
            $table->string('oem', 120)->nullable()->after('min_sale_price');
            $table->string('factory_number', 120)->nullable()->after('oem');
            $table->decimal('min_stock', 18, 4)->nullable()->after('factory_number');
        });
    }

    public function down(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->dropColumn(['min_sale_price', 'oem', 'factory_number', 'min_stock']);
        });
    }
};
