<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('article_code');
            $table->string('name');
            $table->string('unit', 32)->default('шт.');
            $table->timestamps();

            $table->unique(['branch_id', 'article_code']);
        });

        Schema::create('opening_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'good_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_stock_balances');
        Schema::dropIfExists('goods');
    }
};
