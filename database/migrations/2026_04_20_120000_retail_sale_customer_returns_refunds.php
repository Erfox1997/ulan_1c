<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_returns', 'retail_sale_id')) {
            Schema::table('customer_returns', function (Blueprint $table) {
                $table->foreignId('retail_sale_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('retail_sales')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('customer_return_lines', 'source_retail_sale_line_id')) {
            Schema::table('customer_return_lines', function (Blueprint $table) {
                $table->foreignId('source_retail_sale_line_id')
                    ->nullable()
                    ->after('customer_return_id')
                    ->constrained('retail_sale_lines')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('retail_sale_refunds')) {
            Schema::create('retail_sale_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_return_id')->constrained()->cascadeOnDelete();
                $table->foreignId('retail_sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organization_bank_account_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->index(['retail_sale_id', 'organization_bank_account_id'], 'rsr_sale_account_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_sale_refunds');

        if (Schema::hasColumn('customer_return_lines', 'source_retail_sale_line_id')) {
            Schema::table('customer_return_lines', function (Blueprint $table) {
                $table->dropConstrainedForeignId('source_retail_sale_line_id');
            });
        }

        if (Schema::hasColumn('customer_returns', 'retail_sale_id')) {
            Schema::table('customer_returns', function (Blueprint $table) {
                $table->dropConstrainedForeignId('retail_sale_id');
            });
        }
    }
};
