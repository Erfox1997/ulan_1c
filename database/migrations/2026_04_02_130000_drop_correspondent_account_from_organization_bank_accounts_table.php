<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_bank_accounts')) {
            return;
        }

        if (! Schema::hasColumn('organization_bank_accounts', 'correspondent_account')) {
            return;
        }

        Schema::table('organization_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('correspondent_account');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_bank_accounts')) {
            return;
        }

        if (Schema::hasColumn('organization_bank_accounts', 'correspondent_account')) {
            return;
        }

        Schema::table('organization_bank_accounts', function (Blueprint $table) {
            $table->string('correspondent_account', 64)->nullable();
        });
    }
};
