<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entity_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('buyer_name', 255)->default('');
            $table->date('document_date');
            $table->timestamps();
        });

        Schema::create('legal_entity_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_sale_id')->constrained()->cascadeOnDelete();
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

    public function down(): void
    {
        Schema::dropIfExists('legal_entity_sale_lines');
        Schema::dropIfExists('legal_entity_sales');
    }
};
