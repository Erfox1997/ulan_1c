<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->string('supplier_name', 255)->default('');
                $table->date('document_date');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_return_lines')) {
            Schema::create('purchase_return_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
                $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
                $table->string('article_code', 128);
                $table->string('name');
                $table->string('unit', 32)->default('шт.');
                $table->decimal('quantity', 18, 4);
                $table->decimal('unit_price', 18, 2)->nullable();
                $table->decimal('line_sum', 18, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_lines');
        Schema::dropIfExists('purchase_returns');
    }
};
