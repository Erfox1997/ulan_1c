<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->string('buyer_pin', 32)->default('')->after('buyer_name');
        });
    }

    public function down(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->dropColumn('buyer_pin');
        });
    }
};
