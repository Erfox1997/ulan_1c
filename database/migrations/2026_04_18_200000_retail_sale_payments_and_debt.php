<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('retail_sales', 'debt_amount')) {
            Schema::table('retail_sales', function (Blueprint $table) {
                $table->decimal('debt_amount', 14, 2)->default('0.00')->after('total_amount');
                $table->string('debtor_name', 255)->nullable()->after('debt_amount');
                $table->string('debtor_phone', 64)->nullable()->after('debtor_name');
                $table->text('debtor_comment')->nullable()->after('debtor_phone');
            });
        }

        if (! Schema::hasTable('retail_sale_payments')) {
            Schema::create('retail_sale_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('retail_sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organization_bank_account_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->index(['retail_sale_id', 'organization_bank_account_id'], 'rsp_sale_account_idx');
            });
        }

        if (! Schema::hasTable('retail_sale_payments')) {
            return;
        }

        $now = now()->toDateTimeString();
        $rows = DB::table('retail_sales as rs')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('retail_sale_payments as p')
                    ->whereColumn('p.retail_sale_id', 'rs.id');
            })
            ->select('rs.id', 'rs.organization_bank_account_id', 'rs.total_amount', 'rs.created_at', 'rs.updated_at')
            ->get();

        foreach ($rows as $row) {
            DB::table('retail_sale_payments')->insert([
                'retail_sale_id' => $row->id,
                'organization_bank_account_id' => $row->organization_bank_account_id,
                'amount' => $row->total_amount,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_sale_payments');

        if (Schema::hasColumn('retail_sales', 'debt_amount')) {
            Schema::table('retail_sales', function (Blueprint $table) {
                $table->dropColumn(['debt_amount', 'debtor_name', 'debtor_phone', 'debtor_comment']);
            });
        }
    }
};
