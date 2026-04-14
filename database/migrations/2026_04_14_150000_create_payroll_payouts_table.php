<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('amount', 14, 2);
            $table->foreignId('cash_movement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['branch_id', 'employee_id', 'period_from', 'period_to'],
                'payroll_payouts_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payouts');
    }
};
