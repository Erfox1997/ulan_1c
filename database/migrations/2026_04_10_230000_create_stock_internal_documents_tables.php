<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->date('document_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
        });

        Schema::create('stock_surpluses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->date('document_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_surplus_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_surplus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_writeoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->date('document_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_writeoff_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_writeoff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
        });

        Schema::create('stock_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->date('document_date');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_audit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->constrained('goods')->cascadeOnDelete();
            $table->decimal('quantity_counted', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_lines');
        Schema::dropIfExists('stock_audits');
        Schema::dropIfExists('stock_writeoff_lines');
        Schema::dropIfExists('stock_writeoffs');
        Schema::dropIfExists('stock_surplus_lines');
        Schema::dropIfExists('stock_surpluses');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
