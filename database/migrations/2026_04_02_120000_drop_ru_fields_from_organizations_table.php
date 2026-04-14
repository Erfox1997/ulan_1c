<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Удаление полей, не используемых для организаций КР (ранее добавленных «под РФ»).
 * Безопасно на новых БД, где этих колонок уже нет в create_organizations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        $columns = [
            'kpp',
            'ogrn',
            'registration_number',
            'actual_address',
            'email',
            'director_name',
            'director_position',
            'accountant_name',
        ];

        $toDrop = array_filter($columns, fn (string $c) => Schema::hasColumn('organizations', $c));

        if ($toDrop === []) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'kpp')) {
                $table->string('kpp', 16)->nullable();
            }
            if (! Schema::hasColumn('organizations', 'ogrn')) {
                $table->string('ogrn', 32)->nullable();
            }
            if (! Schema::hasColumn('organizations', 'registration_number')) {
                $table->string('registration_number')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'actual_address')) {
                $table->text('actual_address')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'director_name')) {
                $table->string('director_name')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'director_position')) {
                $table->string('director_position')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'accountant_name')) {
                $table->string('accountant_name')->nullable();
            }
        });
    }
};
