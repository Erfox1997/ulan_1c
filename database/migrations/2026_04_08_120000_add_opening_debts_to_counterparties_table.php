<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->decimal('opening_debt_as_buyer', 15, 2)
                ->default(0)
                ->after('address')
                ->comment('Долг покупателя нам до учёта в программе (+ = должен нам)');
            $table->decimal('opening_debt_as_supplier', 15, 2)
                ->default(0)
                ->after('opening_debt_as_buyer')
                ->comment('Наш долг поставщику до учёта в программе (+ = мы должны)');
        });
    }

    public function down(): void
    {
        Schema::table('counterparties', function (Blueprint $table) {
            $table->dropColumn(['opening_debt_as_buyer', 'opening_debt_as_supplier']);
        });
    }
};
