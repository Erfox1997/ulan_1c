<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained('counterparties')->cascadeOnDelete();
            $table->string('vehicle_brand', 120);
            $table->string('vin', 32);
            $table->unsignedSmallInteger('vehicle_year')->nullable();
            $table->string('engine_volume', 64)->nullable();
            $table->string('plate_number', 32)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'vin']);
            $table->index(['branch_id', 'counterparty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vehicles');
    }
};
