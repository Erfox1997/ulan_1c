<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_full_access')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('branch_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_role_id')->constrained()->cascadeOnDelete();
            $table->string('route_pattern', 255);
            $table->timestamps();

            $table->unique(['branch_role_id', 'route_pattern']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_role_id')->nullable()->after('branch_id')->constrained('branch_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_role_id']);
            $table->dropColumn('branch_role_id');
        });

        Schema::dropIfExists('branch_role_permissions');
        Schema::dropIfExists('branch_roles');
    }
};
