<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Good;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $branchId = auth()->user()->branch_id;
        if ($branchId === null) {
            return response()->json([]);
        }

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $warehouseId = (int) $request->query('warehouse_id', 0);

        if ($request->boolean('barcode_exact') && $warehouseId > 0 && mb_strlen($term) >= 4) {
            $normalized = preg_replace('/\D+/', '', $term);
            $exactMatches = Good::query()
                ->where('goods.branch_id', (int) $branchId)
                ->where('goods.is_service', false)
                ->where(function ($q) use ($term, $normalized) {
                    $q->where('goods.barcode', $term);
                    if ($normalized !== '') {
                        $q->orWhereRaw("REPLACE(REPLACE(COALESCE(goods.barcode, ''), ' ', ''), '-', '') = ?", [$normalized]);
                    }
                })
                ->leftJoin('opening_stock_balances as osb', function ($join) use ($warehouseId) {
                    $join->on('goods.id', '=', 'osb.good_id')
                        ->where('osb.warehouse_id', '=', $warehouseId);
                })
                ->select([
                    'goods.id',
                    'goods.article_code',
                    'goods.name',
                    'goods.unit',
                    'goods.barcode',
                    'goods.category',
                    'goods.sale_price',
                    'goods.is_service',
                    'osb.unit_cost as opening_unit_cost',
                    'osb.quantity as stock_quantity',
                ])
                ->orderBy('goods.article_code')
                ->orderBy('goods.name')
                ->limit(25)
                ->get();
            if ($exactMatches->isNotEmpty()) {
                return response()->json($exactMatches->values()->all());
            }
        }

        $like = '%'.$term.'%';
        $barcodeDigits = preg_replace('/\D+/', '', $term);

        $query = Good::query()
            ->where('goods.branch_id', (int) $branchId)
            ->where(function ($q) use ($like, $barcodeDigits) {
                $q->where('goods.name', 'like', $like)
                    ->orWhere('goods.article_code', 'like', $like)
                    ->orWhere('goods.barcode', 'like', $like);
                if ($barcodeDigits !== '' && strlen($barcodeDigits) >= 2) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(COALESCE(goods.barcode, ''), ' ', ''), '-', '') like ?",
                        ['%'.$barcodeDigits.'%']
                    );
                }
            });

        if ($request->boolean('services_only')) {
            $query->where('goods.is_service', true);
        } elseif ($request->boolean('exclude_services')) {
            $query->where('goods.is_service', false);
        }

        if ($warehouseId > 0) {
            $query->leftJoin('opening_stock_balances as osb', function ($join) use ($warehouseId) {
                $join->on('goods.id', '=', 'osb.good_id')
                    ->where('osb.warehouse_id', '=', $warehouseId);
            });
            $query->select([
                'goods.id',
                'goods.article_code',
                'goods.name',
                'goods.unit',
                'goods.barcode',
                'goods.category',
                'goods.sale_price',
                'goods.is_service',
                'osb.unit_cost as opening_unit_cost',
                'osb.quantity as stock_quantity',
            ]);
        } else {
            $query->select([
                'goods.id',
                'goods.article_code',
                'goods.name',
                'goods.unit',
                'goods.barcode',
                'goods.category',
                'goods.sale_price',
                'goods.is_service',
            ]);
        }

        $goods = $query
            ->orderBy('goods.name')
            ->limit(25)
            ->get();

        return response()->json($goods);
    }
}
