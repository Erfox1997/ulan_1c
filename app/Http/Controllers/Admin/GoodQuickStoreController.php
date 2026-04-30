<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuickGoodRequest;
use App\Models\Good;
use App\Services\ArticleSequenceService;
use App\Services\OpeningBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GoodQuickStoreController extends Controller
{
    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService,
        private readonly ArticleSequenceService $articleSequence,
    ) {}

    public function __invoke(StoreQuickGoodRequest $request): JsonResponse
    {
        $branchId = (int) $request->user()->branch_id;

        try {
            $codes = $this->articleSequence->reserveNextArticleCodes($branchId, 1);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $articleCode = $codes[0];
        $v = $request->validated();

        $unit = isset($v['unit']) ? trim((string) $v['unit']) : '';
        $unit = $unit !== '' ? $unit : 'шт.';

        $salePrice = $this->openingBalanceService->parseOptionalMoney($v['sale_price'] ?? null);
        $wholesalePrice = $this->openingBalanceService->parseOptionalMoney($v['wholesale_price'] ?? null);
        $minSalePrice = $this->openingBalanceService->parseOptionalMoney($v['min_sale_price'] ?? null);
        $minStock = $this->openingBalanceService->parseOptionalNonNegativeDecimal($v['min_stock'] ?? null);

        $barcode = isset($v['barcode']) ? trim((string) $v['barcode']) : '';
        $barcode = $barcode === '' ? null : $barcode;
        $category = isset($v['category']) ? trim((string) $v['category']) : '';
        $category = $category === '' ? null : $category;
        $oem = isset($v['oem']) ? trim((string) $v['oem']) : '';
        $oem = $oem === '' ? null : $oem;
        $factory = isset($v['factory_number']) ? trim((string) $v['factory_number']) : '';
        $factory = $factory === '' ? null : $factory;

        $good = Good::query()->create([
            'branch_id' => $branchId,
            'article_code' => $articleCode,
            'name' => trim((string) $v['name']),
            'barcode' => $barcode,
            'category' => $category,
            'unit' => $unit,
            'sale_price' => $salePrice,
            'wholesale_price' => $wholesalePrice,
            'is_service' => false,
            'min_sale_price' => $minSalePrice,
            'oem' => $oem,
            'factory_number' => $factory,
            'min_stock' => $minStock,
        ]);

        $warehouseId = (int) $request->integer('warehouse_id', 0);

        if ($warehouseId > 0) {
            $row = Good::query()
                ->where('goods.branch_id', $branchId)
                ->where('goods.id', $good->id)
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
                    'goods.is_service',
                    'osb.unit_cost as opening_unit_cost',
                    DB::raw('COALESCE(osb.quantity, 0) as stock_quantity'),
                ])
                ->first();

            if ($row === null) {
                return response()->json(['message' => 'Не удалось загрузить товар.'], 500);
            }

            $payload = $row->toArray();
            $q = $row->getAttribute('stock_quantity');
            if ($q === null || $q === '') {
                $payload['stock_quantity'] = '0';
            } else {
                $payload['stock_quantity'] = is_numeric($q) ? (string) $q : trim((string) $q);
            }

            return response()->json($payload);
        }

        return response()->json($good->fresh()->toArray());
    }
}
