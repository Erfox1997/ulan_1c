<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_bank_account_id')->constrained()->restrictOnDelete();
            $table->date('document_date');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 14, 2);
            $table->timestamps();

            $table->index(['branch_id', 'document_date']);
        });

        Schema::create('retail_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retail_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->constrained()->restrictOnDelete();
            $table->string('article_code', 128);
            $table->string('name', 500);
            $table->string('unit', 32)->nullable();
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('line_sum', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['retail_sale_id', 'good_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_sale_lines');
        Schema::dropIfExists('retail_sales');
    }
};
