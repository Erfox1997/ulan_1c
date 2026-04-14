<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->string('esf_exchange_code', 36)->nullable()->after('esf_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->dropColumn('esf_exchange_code');
        });
    }
};
