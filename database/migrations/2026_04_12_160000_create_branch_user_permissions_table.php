<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('route_pattern', 255);
            $table->timestamps();

            $table->unique(['user_id', 'route_pattern']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user_permissions');
    }
};
