<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_lines', function (Blueprint $table) {
            $table->foreignId('performer_employee_id')->nullable()->after('good_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_order_lines', function (Blueprint $table) {
            $table->dropForeign(['performer_employee_id']);
            $table->dropColumn('performer_employee_id');
        });
    }
};
