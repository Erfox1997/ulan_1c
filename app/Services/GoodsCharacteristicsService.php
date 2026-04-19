<?php

namespace App\Services;

use App\Models\Good;
use App\Models\OpeningStockBalance;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class GoodsCharacteristicsService
{
    /** @var array<string, string> */
    public const FILTER_OPTIONS = [
        'all' => 'Все неполные',
        'barcode' => 'Штрихкод',
        'category' => 'Категория',
        'article_code' => 'Артикул',
        'unit' => 'Ед. изм.',
        'quantity' => 'Количество',
        'unit_cost' => 'Цена (закуп.)',
        'wholesale_price' => 'Оптовая цена',
        'sale_price' => 'Цена (продаж.)',
        'min_sale_price' => 'Мин. цена (продаж.)',
        'oem' => 'ОЭМ',
        'factory_number' => 'Заводской №',
        'min_stock' => 'Мин. остаток',
    ];

    /** @return list<string> Ключи фильтра без режима «все неполные» */
    public static function missingFilterKeyList(): array
    {
        return array_values(array_filter(
            array_keys(self::FILTER_OPTIONS),
            static fn (string $k): bool => $k !== 'all'
        ));
    }

    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    /**
     * Сохранить строку из формы «Товары без полной характеристики».
     *
     * @param  array<string, mixed>  $line
     */
    public function syncRow(int $branchId, int $warehouseId, array $line): void
    {
        $goodId = (int) ($line['good_id'] ?? 0);
        if ($goodId <= 0) {
            return;
        }

        $good = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->find($goodId);

        if (! $good) {
            return;
        }

        $code = trim((string) ($line['article_code'] ?? ''));
        if ($code !== '') {
            $good->article_code = $code;
        }

        $name = trim((string) ($line['name'] ?? ''));
        if ($name !== '') {
            $good->name = $name;
        }

        $barcodeRaw = trim((string) ($line['barcode'] ?? ''));
        $good->barcode = $barcodeRaw === '' ? null : $barcodeRaw;

        $categoryRaw = trim((string) ($line['category'] ?? ''));
        $good->category = $categoryRaw === '' ? null : $categoryRaw;

        $unitRaw = trim((string) ($line['unit'] ?? ''));
        if ($unitRaw !== '') {
            $good->unit = $unitRaw;
        }

        $good->wholesale_price = $this->openingBalanceService->parseOptionalMoney($line['wholesale_price'] ?? null);
        $good->sale_price = $this->openingBalanceService->parseOptionalMoney($line['sale_price'] ?? null);
        $good->min_sale_price = $this->openingBalanceService->parseOptionalMoney($line['min_sale_price'] ?? null);

        $oemRaw = trim((string) ($line['oem'] ?? ''));
        $good->oem = $oemRaw === '' ? null : $oemRaw;

        $factoryRaw = trim((string) ($line['factory_number'] ?? ''));
        $good->factory_number = $factoryRaw === '' ? null : $factoryRaw;

        $good->min_stock = $this->openingBalanceService->parseOptionalNonNegativeDecimal($line['min_stock'] ?? null);

        $good->save();

        $this->propagateMinStockToSameOemGoods($branchId, $good);

        $qtyRaw = trim((string) ($line['quantity'] ?? ''));
        $costRaw = $line['unit_cost'] ?? null;
        $costIsEmpty = $costRaw === null || $costRaw === '';

        $balance = OpeningStockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('good_id', $good->id)
            ->first();

        if ($qtyRaw === '') {
            if ($balance && ! $costIsEmpty) {
                $balance->unit_cost = $this->openingBalanceService->parseOptionalMoney($costRaw);
                $balance->save();
            }

            return;
        }

        $qty = $this->openingBalanceService->parseDecimal($qtyRaw);
        if ($qty === null || (float) $qty <= 0) {
            OpeningStockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('good_id', $good->id)
                ->delete();

            return;
        }

        $unitCost = $this->openingBalanceService->parseOptionalMoney($costRaw);
        OpeningStockBalance::query()->updateOrCreate(
            ['warehouse_id' => $warehouseId, 'good_id' => $good->id],
            ['branch_id' => $branchId, 'quantity' => $qty, 'unit_cost' => $unitCost]
        );
    }

    /**
     * После сохранения «Мин. остаток»: тем же значением обновить остальные товары филиала с тем же непустым ОЭМ.
     * Товары с пустым ОЭМ не объединяются (правка одного не затрагивает других с пустым ОЭМ).
     */
    private function propagateMinStockToSameOemGoods(int $branchId, Good $good): void
    {
        $oem = trim((string) ($good->oem ?? ''));
        if ($oem === '') {
            return;
        }

        Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false)
            ->whereKeyNot($good->id)
            ->whereRaw("TRIM(COALESCE(oem, '')) = ?", [$oem])
            ->update(['min_stock' => $good->min_stock]);
    }

    /**
     * Сколько товаров с неполной характеристикой (фильтр «все») для склада по умолчанию —
     * как на странице отчёта при первом открытии.
     */
    public function countIncompleteForBranchDefaultWarehouse(int $branchId): int
    {
        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $warehouseId = (int) ($warehouses->firstWhere('is_default')?->id ?? $warehouses->first()?->id ?? 0);
        if ($warehouseId === 0) {
            return 0;
        }

        return (int) $this->incompleteGoodsQueryForMissingKeys($branchId, $warehouseId, [])->count();
    }

    /**
     * Товары (не услуги) филиала с неполной характеристикой по выбранному фильтру (один ключ или «все»).
     * Для полей «Количество» и «Цена (закуп.)» учитывается выбранный склад.
     */
    public function incompleteGoodsQuery(int $branchId, int $warehouseId, string $filter): Builder
    {
        $filter = $filter === '' ? 'all' : $filter;
        if ($filter === 'all') {
            return $this->incompleteGoodsQueryForMissingKeys($branchId, $warehouseId, []);
        }

        return $this->incompleteGoodsQueryForMissingKeys($branchId, $warehouseId, [$filter]);
    }

    /**
     * Пустой список ключей — как «Все неполные». Несколько ключей — товары, у которых не заполнена
     * хотя бы одна из отмеченных характеристик (логика ИЛИ).
     *
     * @param  list<string>  $missingKeys
     */
    public function incompleteGoodsQueryForMissingKeys(int $branchId, int $warehouseId, array $missingKeys): Builder
    {
        $q = Good::query()
            ->where('branch_id', $branchId)
            ->where('is_service', false);

        $allowed = self::missingFilterKeyList();
        $keys = [];
        foreach ($missingKeys as $k) {
            $k = (string) $k;
            if (in_array($k, $allowed, true)) {
                $keys[] = $k;
            }
        }
        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            $this->applyAnyIncomplete($q, $warehouseId);

            return $q;
        }

        if (count($keys) === 1) {
            $this->applySingleFilter($q, $warehouseId, $keys[0]);

            return $q;
        }

        $q->where(function (Builder $outer) use ($warehouseId, $keys) {
            foreach (array_values($keys) as $i => $key) {
                if ($i === 0) {
                    $outer->where(function (Builder $inner) use ($warehouseId, $key) {
                        $this->applySingleFilter($inner, $warehouseId, $key);
                    });
                } else {
                    $outer->orWhere(function (Builder $inner) use ($warehouseId, $key) {
                        $this->applySingleFilter($inner, $warehouseId, $key);
                    });
                }
            }
        });

        return $q;
    }

    private function applyAnyIncomplete(Builder $q, int $warehouseId): void
    {
        $q->where(function (Builder $outer) use ($warehouseId) {
            $outer->where(fn (Builder $q) => $q->whereNull('barcode')->orWhere('barcode', ''))
                ->orWhere(fn (Builder $q) => $q->whereNull('category')->orWhere('category', ''))
                ->orWhere(fn (Builder $q) => $q->whereNull('article_code')->orWhere('article_code', ''))
                ->orWhere(fn (Builder $q) => $q->whereNull('unit')->orWhere('unit', ''))
                ->orWhere(fn (Builder $q) => $q->whereNull('wholesale_price'))
                ->orWhere(fn (Builder $q) => $q->whereNull('sale_price'))
                ->orWhere(fn (Builder $q) => $q->whereNull('min_sale_price'))
                ->orWhere(fn (Builder $q) => $q->whereNull('oem')->orWhere('oem', ''))
                ->orWhere(fn (Builder $q) => $q->whereNull('factory_number')->orWhere('factory_number', ''))
                ->orWhere(fn (Builder $q) => $q->whereNull('min_stock'))
                ->orWhere(fn (Builder $q) => $q->whereDoesntHave(
                    'openingStockBalances',
                    fn (Builder $b) => $b->where('warehouse_id', $warehouseId)->where('quantity', '>', 0)
                ))
                ->orWhere(fn (Builder $q) => $q->where(function (Builder $inner) use ($warehouseId) {
                    $inner->whereDoesntHave(
                        'openingStockBalances',
                        fn (Builder $b) => $b->where('warehouse_id', $warehouseId)
                    )->orWhereHas(
                        'openingStockBalances',
                        fn (Builder $b) => $b->where('warehouse_id', $warehouseId)->whereNull('unit_cost')
                    );
                }));
        });
    }

    private function applySingleFilter(Builder $q, int $warehouseId, string $filter): void
    {
        match ($filter) {
            'barcode' => $q->where(fn (Builder $q) => $q->whereNull('barcode')->orWhere('barcode', '')),
            'category' => $q->where(fn (Builder $q) => $q->whereNull('category')->orWhere('category', '')),
            'article_code' => $q->where(fn (Builder $q) => $q->whereNull('article_code')->orWhere('article_code', '')),
            'unit' => $q->where(fn (Builder $q) => $q->whereNull('unit')->orWhere('unit', '')),
            'wholesale_price' => $q->whereNull('wholesale_price'),
            'sale_price' => $q->whereNull('sale_price'),
            'min_sale_price' => $q->whereNull('min_sale_price'),
            'oem' => $q->where(fn (Builder $q) => $q->whereNull('oem')->orWhere('oem', '')),
            'factory_number' => $q->where(fn (Builder $q) => $q->whereNull('factory_number')->orWhere('factory_number', '')),
            'min_stock' => $q->whereNull('min_stock'),
            'quantity' => $q->whereDoesntHave(
                'openingStockBalances',
                fn (Builder $b) => $b->where('warehouse_id', $warehouseId)->where('quantity', '>', 0)
            ),
            'unit_cost' => $q->where(function (Builder $inner) use ($warehouseId) {
                $inner->whereDoesntHave(
                    'openingStockBalances',
                    fn (Builder $b) => $b->where('warehouse_id', $warehouseId)
                )->orWhereHas(
                    'openingStockBalances',
                    fn (Builder $b) => $b->where('warehouse_id', $warehouseId)->whereNull('unit_cost')
                );
            }),
            default => throw new \InvalidArgumentException('Неизвестный фильтр характеристик: '.$filter),
        };
    }
}
