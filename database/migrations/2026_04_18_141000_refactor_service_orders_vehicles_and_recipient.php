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
            $table->foreignId('customer_vehicle_id')->nullable()->after('counterparty_id')->constrained('customer_vehicles')->nullOnDelete();
        });

        $this->migrateExistingVehiclesToTable();

        if (Schema::hasColumn('service_orders', 'recipient_kind')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->dropColumn([
                    'recipient_kind',
                    'vehicle_brand',
                    'vin',
                    'vehicle_year',
                    'engine_volume',
                    'plate_number',
                    'customer_name',
                    'customer_phone',
                ]);
            });
        }
    }

    private function migrateExistingVehiclesToTable(): void
    {
        if (! Schema::hasColumn('service_orders', 'vin')) {
            return;
        }

        $rows = DB::table('service_orders')
            ->whereNotNull('counterparty_id')
            ->whereNotNull('vin')
            ->where('vin', '!=', '')
            ->get([
                'id',
                'branch_id',
                'counterparty_id',
                'vehicle_brand',
                'vin',
                'vehicle_year',
                'engine_volume',
                'plate_number',
            ]);

        foreach ($rows as $row) {
            $exists = DB::table('customer_vehicles')
                ->where('branch_id', $row->branch_id)
                ->where('vin', $row->vin)
                ->first();
            if ($exists) {
                DB::table('service_orders')->where('id', $row->id)->update(['customer_vehicle_id' => $exists->id]);

                continue;
            }
            $vid = DB::table('customer_vehicles')->insertGetId([
                'branch_id' => $row->branch_id,
                'counterparty_id' => $row->counterparty_id,
                'vehicle_brand' => $row->vehicle_brand ?? '',
                'vin' => $row->vin,
                'vehicle_year' => $row->vehicle_year,
                'engine_volume' => $row->engine_volume,
                'plate_number' => $row->plate_number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('service_orders')->where('id', $row->id)->update(['customer_vehicle_id' => $vid]);
        }
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['customer_vehicle_id']);
            $table->dropColumn('customer_vehicle_id');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->string('recipient_kind', 16)->nullable()->after('warehouse_id');
            $table->string('vehicle_brand', 120)->nullable()->after('counterparty_id');
            $table->string('vin', 32)->nullable()->after('vehicle_brand');
            $table->unsignedSmallInteger('vehicle_year')->nullable()->after('vin');
            $table->string('engine_volume', 64)->nullable()->after('vehicle_year');
            $table->string('plate_number', 32)->nullable()->after('engine_volume');
            $table->string('customer_name', 255)->nullable()->after('plate_number');
            $table->string('customer_phone', 64)->nullable()->after('customer_name');
        });
    }
};
