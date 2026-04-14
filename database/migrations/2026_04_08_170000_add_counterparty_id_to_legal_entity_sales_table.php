<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->foreignId('counterparty_id')
                ->nullable()
                ->after('buyer_pin')
                ->constrained('counterparties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->dropForeign(['counterparty_id']);
        });
    }
};
