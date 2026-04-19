<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('counterparty_id')->nullable()->after('recipient_kind')->constrained('counterparties')->nullOnDelete();
            $table->string('vehicle_brand', 120)->nullable()->after('counterparty_id');
            $table->string('vin', 32)->nullable()->after('vehicle_brand');
            $table->unsignedSmallInteger('vehicle_year')->nullable()->after('vin');
            $table->string('engine_volume', 64)->nullable()->after('vehicle_year');
            $table->string('plate_number', 32)->nullable()->after('engine_volume');
            $table->string('customer_name', 255)->nullable()->after('plate_number');
            $table->string('customer_phone', 64)->nullable()->after('customer_name');
            $table->decimal('mileage_km', 12, 2)->nullable()->after('customer_phone');
            $table->foreignId('lead_master_employee_id')->nullable()->after('mileage_km')->constrained('employees')->nullOnDelete();
            $table->date('deadline_date')->nullable()->after('lead_master_employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['counterparty_id']);
            $table->dropForeign(['lead_master_employee_id']);
            $table->dropColumn([
                'counterparty_id',
                'vehicle_brand',
                'vin',
                'vehicle_year',
                'engine_volume',
                'plate_number',
                'customer_name',
                'customer_phone',
                'mileage_km',
                'lead_master_employee_id',
                'deadline_date',
            ]);
        });
    }
};
