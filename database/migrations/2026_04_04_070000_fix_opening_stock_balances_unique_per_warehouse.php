<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Остатки — по паре (склад, товар). Уникальный ключ (branch_id, good_id) запрещает
 * один товар на нескольких складах одного филиала.
 *
 * Для MySQL индекс снимаем по фактическому имени из information_schema (не полагаемся на Schema::dropUnique).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opening_stock_balances')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->upgradeMysql();

            return;
        }

        try {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->dropUnique(['branch_id', 'good_id']);
            });
        } catch (Throwable) {
            //
        }
        try {
            Schema::table('opening_stock_balances', function (Blueprint $table) {
                $table->unique(['warehouse_id', 'good_id']);
            });
        } catch (Throwable) {
            //
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('opening_stock_balances')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $this->downgradeMysql();

            return;
        }

        Schema::table('opening_stock_balances', function (Blueprint $table) {
            $table->dropUnique(['warehouse_id', 'good_id']);
        });
        Schema::table('opening_stock_balances', function (Blueprint $table) {
            $table->unique(['branch_id', 'good_id']);
        });
    }

    private function upgradeMysql(): void
    {
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
            $conn->statement('ALTER TABLE `opening_stock_balances` DROP INDEX `'.$name.'`');
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
            $conn->statement(
                'ALTER TABLE `opening_stock_balances` ADD UNIQUE INDEX `opening_stock_balances_warehouse_id_good_id_unique` (`warehouse_id`, `good_id`)'
            );
        }

        $this->restoreMysqlForeignKeys();
    }

    private function restoreMysqlForeignKeys(): void
    {
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

    private function downgradeMysql(): void
    {
        $conn = Schema::getConnection();
        $database = $conn->getDatabaseName();

        $rows = $conn->select(
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

        if ($rows !== []) {
            $conn->statement('ALTER TABLE `opening_stock_balances` DROP INDEX `opening_stock_balances_warehouse_id_good_id_unique`');
        }

        try {
            $conn->statement(
                'ALTER TABLE `opening_stock_balances` ADD UNIQUE INDEX `opening_stock_balances_branch_id_good_id_unique` (`branch_id`, `good_id`)'
            );
        } catch (Throwable) {
            //
        }
    }
};
