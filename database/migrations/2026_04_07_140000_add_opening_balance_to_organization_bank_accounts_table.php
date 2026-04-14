<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_bank_accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 14, 2)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('organization_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
