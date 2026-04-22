<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_vehicles')->where('vin', '')->update(['vin' => null]);
        DB::table('customer_vehicles')->where('vehicle_brand', '')->update(['vehicle_brand' => null]);

        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->string('vehicle_brand', 120)->nullable()->change();
            $table->string('vin', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('customer_vehicles')->whereNull('vehicle_brand')->update(['vehicle_brand' => '']);
        DB::table('customer_vehicles')->whereNull('vin')->update(['vin' => '']);

        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->string('vehicle_brand', 120)->nullable(false)->change();
            $table->string('vin', 32)->nullable(false)->change();
        });
    }
};
