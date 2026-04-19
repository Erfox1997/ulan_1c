<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Откат схемы возвратов по реализации юрлиц (таблица refunds и FK в customer_returns / customer_return_lines).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('legal_entity_sale_refunds');

        if (Schema::hasColumn('customer_return_lines', 'source_legal_entity_sale_line_id')) {
            Schema::table('customer_return_lines', function (Blueprint $table) {
                $table->dropConstrainedForeignId('source_legal_entity_sale_line_id');
            });
        }

        if (Schema::hasColumn('customer_returns', 'legal_entity_sale_id')) {
            Schema::table('customer_returns', function (Blueprint $table) {
                $table->dropConstrainedForeignId('legal_entity_sale_id');
            });
        }
    }

    public function down(): void
    {
        // Не восстанавливаем удалённую фичу.
    }
};
