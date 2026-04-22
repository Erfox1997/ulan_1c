<?php

use App\Models\LegalEntitySale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->boolean('esf_queue_goods')->default(false)->after('document_date');
            $table->boolean('esf_queue_services')->default(false)->after('esf_queue_goods');
            $table->timestamp('esf_submitted_goods_at')->nullable()->after('payment_invoice_sent');
            $table->timestamp('esf_submitted_services_at')->nullable()->after('esf_submitted_goods_at');
        });

        LegalEntitySale::query()
            ->with('lines.good')
            ->orderBy('id')
            ->chunk(100, function ($chunk) {
                foreach ($chunk as $sale) {
                    $flags = DB::table('legal_entity_sales')
                        ->where('id', $sale->id)
                        ->select('issue_esf', 'esf_submitted_at')
                        ->first();
                    $issueEsf = (bool) ($flags->issue_esf ?? false);
                    $subAt = $flags->esf_submitted_at !== null
                        ? \Illuminate\Support\Carbon::parse($flags->esf_submitted_at)
                        : null;
                    $p = $sale->esfGoodsServicesLinesProfile();
                    $queueGoods = false;
                    $queueServices = false;
                    $subGoods = null;
                    $subServices = null;
                    if ($issueEsf) {
                        if ($p['has_goods']) {
                            $queueGoods = true;
                        }
                        if ($p['has_services']) {
                            $queueServices = true;
                        }
                        if (! $p['has_goods'] && ! $p['has_services']) {
                            $queueGoods = true;
                        }
                    }
                    if ($subAt !== null) {
                        if ($p['has_goods'] || (! $p['has_goods'] && ! $p['has_services'])) {
                            $subGoods = $subAt;
                        }
                        if ($p['has_services']) {
                            $subServices = $subAt;
                        }
                    }
                    $sale->forceFill([
                        'esf_queue_goods' => $queueGoods,
                        'esf_queue_services' => $queueServices,
                        'esf_submitted_goods_at' => $subGoods,
                        'esf_submitted_services_at' => $subServices,
                    ])->save();
                }
            });

        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->dropColumn(['issue_esf', 'esf_submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->boolean('issue_esf')->default(false)->after('document_date');
            $table->timestamp('esf_submitted_at')->nullable()->after('payment_invoice_sent');
        });

        LegalEntitySale::query()
            ->orderBy('id')
            ->chunk(100, function ($chunk) {
                foreach ($chunk as $sale) {
                    $issueEsf = (bool) $sale->esf_queue_goods
                        || (bool) $sale->esf_queue_services
                        || $sale->esf_submitted_goods_at !== null
                        || $sale->esf_submitted_services_at !== null;
                    $subAt = $sale->esf_submitted_goods_at
                        ?? $sale->esf_submitted_services_at;
                    $sale->forceFill([
                        'issue_esf' => $issueEsf,
                        'esf_submitted_at' => $subAt,
                    ])->save();
                }
            });

        Schema::table('legal_entity_sales', function (Blueprint $table) {
            $table->dropColumn([
                'esf_queue_goods',
                'esf_queue_services',
                'esf_submitted_goods_at',
                'esf_submitted_services_at',
            ]);
        });
    }
};
