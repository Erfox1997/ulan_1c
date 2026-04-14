<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Штрихкод не обязан быть уникальным в рамках филиала — уникален артикул.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->unique(['branch_id', 'barcode']);
        });
    }
};
