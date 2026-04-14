<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->timestamp('esf_submitted_at')->nullable()->after('payment_invoice_sent');
        });
    }

    public function down(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->dropColumn('esf_submitted_at');
        });
    }
};
