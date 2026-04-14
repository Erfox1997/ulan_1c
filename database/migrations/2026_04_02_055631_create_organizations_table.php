<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('inn', 32)->nullable();
            $table->text('legal_address')->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('organization_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('account_type', 16)->default('bank');
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bik', 32)->nullable();
            $table->string('currency', 3)->default('KGS');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_bank_accounts');
        Schema::dropIfExists('organizations');
    }
};
