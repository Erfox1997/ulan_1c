<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->string('tnved_code', 32)->nullable()->after('min_stock');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('service_tnved_code', 32)->nullable()->after('is_active');
        });

        Schema::create('tnved_keyword_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 255);
            $table->string('tnved_code', 32);
            $table->timestamps();

            $table->unique(['branch_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tnved_keyword_rules');
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('service_tnved_code');
        });
        Schema::table('goods', function (Blueprint $table) {
            $table->dropColumn('tnved_code');
        });
    }
};
