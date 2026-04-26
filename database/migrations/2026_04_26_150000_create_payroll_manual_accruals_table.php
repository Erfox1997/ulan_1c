<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_manual_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['branch_id', 'employee_id', 'period_from', 'period_to'], 'payroll_manual_accrual_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_manual_accruals');
    }
};
