<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseClearService
{
    /**
     * Таблицы, которые не очищаем: учётные записи и служебная история миграций Laravel.
     *
     * @var list<string>
     */
    private const EXCLUDED = ['users', 'migrations'];

    /**
     * Удалить данные из всех таблиц, кроме исключённых. Ссылки пользователей на филиалы сбрасываются.
     */
    public function clearOperationalData(): void
    {
        $tableNames = Schema::getTableListing(null, false);

        $toClear = array_values(array_filter($tableNames, function (string $t) {
            return ! in_array(strtolower($t), array_map('strtolower', self::EXCLUDED), true);
        }));

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($toClear as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                DB::table($table)->truncate();
            }

            if (Schema::hasColumn('users', 'branch_id')) {
                DB::table('users')->update(['branch_id' => null]);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
