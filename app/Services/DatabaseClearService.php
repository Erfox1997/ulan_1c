<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseClearService
{
    /**
     * Таблицы, которые не очищаем: миграции, учётные записи, филиалы, склады и организации,
     * а также роли и права филиалов (чтобы админы филиалов оставались работоспособными).
     *
     * @var list<string>
     */
    private const EXCLUDED = [
        'migrations',
        'users',
        'branches',
        'warehouses',
        'organizations',
        'organization_bank_accounts',
        'branch_roles',
        'branch_role_permissions',
        'branch_user_permissions',
    ];

    /**
     * Удалить операционные данные из всех таблиц, кроме исключённых.
     */
    public function clearOperationalData(): void
    {
        $tableNames = Schema::getTableListing(null, false);
        $excludedLower = array_map('strtolower', self::EXCLUDED);

        $toClear = array_values(array_filter($tableNames, function (string $t) use ($excludedLower) {
            return ! in_array(strtolower($t), $excludedLower, true);
        }));

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($toClear as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
