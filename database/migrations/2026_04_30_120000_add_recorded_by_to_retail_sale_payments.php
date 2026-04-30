<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retail_sale_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('retail_sale_payments', 'recorded_by_user_id')) {
                $table->foreignId('recorded_by_user_id')
                    ->nullable()
                    ->after('amount')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('retail_sale_payments', 'recorded_by_user_id')) {
            return;
        }

        $rows = DB::table('retail_sale_payments as p')
            ->join('retail_sales as rs', 'rs.id', '=', 'p.retail_sale_id')
            ->whereNull('p.recorded_by_user_id')
            ->select(['p.id', 'rs.user_id as uid'])
            ->get();

        foreach ($rows->chunk(500) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('retail_sale_payments')
                    ->where('id', $row->id)
                    ->update(['recorded_by_user_id' => $row->uid]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('retail_sale_payments', function (Blueprint $table) {
            if (Schema::hasColumn('retail_sale_payments', 'recorded_by_user_id')) {
                $table->dropConstrainedForeignId('recorded_by_user_id');
            }
        });
    }
};
