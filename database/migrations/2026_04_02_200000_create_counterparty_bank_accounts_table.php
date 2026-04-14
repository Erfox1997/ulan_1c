<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparty_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counterparty_id')->constrained()->cascadeOnDelete();
            $table->string('account_type', 16)->default('bank');
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bik', 32)->nullable();
            $table->string('currency', 3)->default('KGS');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (Schema::hasColumn('counterparties', 'accounts_note')) {
            Schema::table('counterparties', function (Blueprint $table) {
                $table->dropColumn('accounts_note');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_bank_accounts');

        if (! Schema::hasColumn('counterparties', 'accounts_note')) {
            Schema::table('counterparties', function (Blueprint $table) {
                $table->text('accounts_note')->nullable();
            });
        }
    }
};
