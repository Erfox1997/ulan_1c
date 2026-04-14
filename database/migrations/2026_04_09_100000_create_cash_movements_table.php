<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->date('occurred_on');
            $table->decimal('amount', 14, 2);
            $table->foreignId('our_account_id')
                ->nullable()
                ->constrained('organization_bank_accounts')
                ->restrictOnDelete();
            $table->foreignId('from_account_id')
                ->nullable()
                ->constrained('organization_bank_accounts')
                ->restrictOnDelete();
            $table->foreignId('to_account_id')
                ->nullable()
                ->constrained('organization_bank_accounts')
                ->restrictOnDelete();
            $table->foreignId('counterparty_id')
                ->nullable()
                ->constrained('counterparties')
                ->nullOnDelete();
            $table->string('expense_category', 255)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'occurred_on']);
            $table->index(['branch_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
