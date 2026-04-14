<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('name');
            $table->string('legal_form', 32);
            $table->text('full_name');
            $table->string('inn', 32)->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('address')->nullable();
            $table->text('accounts_note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparties');
    }
};
