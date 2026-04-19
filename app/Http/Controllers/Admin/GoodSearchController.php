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

        $warehouseId = (int) $request->query('warehouse_id', 0);

        if ($request->boolean('exact_article')) {
            $code = trim((string) $request->query('q', ''));
            if ($code === '') {
                return response()->json([]);
            }

            $single = Good::query()
                ->where('goods.branch_id', (int) $branchId)
                ->where('goods.article_code', $code);

            if ($request->boolean('exclude_services')) {
                $single->where('goods.is_service', false);
            }

            if ($warehouseId > 0) {
                $single->leftJoin('opening_stock_balances as osb', function ($join) use ($warehouseId) {
                    $join->on('goods.id', '=', 'osb.good_id')
                        ->where('osb.warehouse_id', '=', $warehouseId);
                });
                $single->select([
                    'goods.id',
                    'goods.article_code',
                    'goods.name',
                    'goods.unit',
                    'goods.barcode',
                    'goods.category',
                    'goods.sale_price',
                    'goods.wholesale_price',
                    'goods.min_sale_price',
                    'goods.is_service',
                    'osb.unit_cost as opening_unit_cost',
                    'osb.quantity as stock_quantity',
                ]);
            } else {
                $single->select([
                    'goods.id',
                    'goods.article_code',
                    'goods.name',
                    'goods.unit',
                    'goods.barcode',
                    'goods.category',
                    'goods.sale_price',
                    'goods.wholesale_price',
                    'goods.min_sale_price',
                    'goods.is_service',
                ]);
            }

            $row = $single->first();

            return response()->json($row ? [$row->toArray()] : []);
        }

        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return response()->json([]);
        }

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
                    'goods.wholesale_price',
                    'goods.min_sale_price',
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

        $rawTokens = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $significantTokens = array_values(array_filter(
            $rawTokens,
            static fn (string $t) => mb_strlen($t) >= 2
        ));
        if ($significantTokens === []) {
            return response()->json([]);
        }

        $query = Good::query()
            ->where('goods.branch_id', (int) $branchId);

        foreach ($significantTokens as $token) {
            $query->where(function ($q) use ($token) {
                $escaped = addcslashes($token, '%_\\');
                $like = '%'.$escaped.'%';
                $q->where('goods.name', 'like', $like)
                    ->orWhere('goods.article_code', 'like', $like)
                    ->orWhere('goods.barcode', 'like', $like)
                    ->orWhere('goods.category', 'like', $like)
                    ->orWhere('goods.oem', 'like', $like)
                    ->orWhere('goods.factory_number', 'like', $like);
                $barcodeDigits = preg_replace('/\D+/', '', $token);
                if ($barcodeDigits !== '' && strlen($barcodeDigits) >= 2) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(COALESCE(goods.barcode, ''), ' ', ''), '-', '') like ?",
                        ['%'.$barcodeDigits.'%']
                    );
                }
            });
        }

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
                'goods.wholesale_price',
                'goods.min_sale_price',
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
                'goods.wholesale_price',
                'goods.min_sale_price',
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
