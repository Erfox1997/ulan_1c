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

        Schema::table('organization_bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_bank_accounts', 'account_type')) {
                $table->string('account_type', 16)->default('bank')->after('organization_id');
            }
        });

        if (Schema::hasColumn('organization_bank_accounts', 'account_number')) {
            Schema::table('organization_bank_accounts', function (Blueprint $table) {
                $table->string('account_number')->nullable()->change();
            });
        }

        if (Schema::hasColumn('organization_bank_accounts', 'bank_name')) {
            Schema::table('organization_bank_accounts', function (Blueprint $table) {
                $table->string('bank_name')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_bank_accounts')) {
            return;
        }

        if (Schema::hasColumn('organization_bank_accounts', 'account_type')) {
            Schema::table('organization_bank_accounts', function (Blueprint $table) {
                $table->dropColumn('account_type');
            });
        }
    }
};
