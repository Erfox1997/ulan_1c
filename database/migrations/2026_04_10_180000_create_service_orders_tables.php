<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->date('document_date');
            $table->decimal('total_amount', 14, 2);
            $table->foreignId('organization_bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'document_date']);
        });

        Schema::create('service_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->restrictOnDelete();
            $table->string('article_code', 128);
            $table->string('name', 500);
            $table->string('unit', 32)->nullable();
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_sum', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['service_order_id', 'good_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_lines');
        Schema::dropIfExists('service_orders');
    }
};
