<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Повторяет исправление индексов MySQL (на случай, если 070000 не дошла до конца).
 * Снимает FK → удаляет unique(branch_id, good_id) → unique(warehouse_id, good_id) → возвращает FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opening_stock_balances')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('opening_stock_balances', function (Blueprint $table) {
            foreach (['warehouse_id', 'good_id', 'branch_id'] as $col) {
                if (! Schema::hasColumn('opening_stock_balances', $col)) {
                    continue;
                }
                try {
                    $table->dropForeign([$col]);
                } catch (Throwable) {
                    //
                }
            }
        });

        $conn = Schema::getConnection();
        $database = $conn->getDatabaseName();

        $badIndexes = $conn->select(
            <<<'SQL'
            SELECT INDEX_NAME,
                   GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols,
                   MAX(NON_UNIQUE) AS max_non_unique
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'opening_stock_balances'
              AND INDEX_NAME <> 'PRIMARY'
            GROUP BY INDEX_NAME
            HAVING max_non_unique = 0
               AND (cols = 'branch_id,good_id' OR cols = 'good_id,branch_id')
            SQL,
            [$database]
        );

        foreach ($badIndexes as $row) {
            $name = $row->INDEX_NAME;
            if ($name === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', (string) $name)) {
                continue;
            }
            try {
                $conn->statement('ALTER TABLE `opening_stock_balances` DROP INDEX `'.$name.'`');
            } catch (Throwable) {
                //
            }
        }

        $hasWarehouseGood = $conn->select(
            <<<'SQL'
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'opening_stock_balances'
              AND INDEX_NAME = 'opening_stock_balances_warehouse_id_good_id_unique'
            LIMIT 1
            SQL,
            [$database]
        );

        if ($hasWarehouseGood === []) {
            try {
                $conn->statement(
                    'ALTER TABLE `opening_stock_balances` ADD UNIQUE INDEX `opening_stock_balances_warehouse_id_good_id_unique` (`warehouse_id`, `good_id`)'
                );
            } catch (Throwable) {
                //
            }
        }

        $defs = [
            ['col' => 'branch_id', 'on' => 'branches'],
            ['col' => 'good_id', 'on' => 'goods'],
        ];
        if (Schema::hasColumn('opening_stock_balances', 'warehouse_id')) {
            $defs[] = ['col' => 'warehouse_id', 'on' => 'warehouses'];
        }

        foreach ($defs as $def) {
            try {
                Schema::table('opening_stock_balances', function (Blueprint $table) use ($def) {
                    $table->foreign($def['col'])->references('id')->on($def['on'])->cascadeOnDelete();
                });
            } catch (Throwable) {
                //
            }
        }
    }

    public function down(): void
    {
        //
    }
};
