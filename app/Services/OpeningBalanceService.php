<?php

namespace App\Services;

use App\Models\Good;
use App\Models\OpeningStockBalance;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OpeningBalanceService
{
    /**
     * @param  list<array{article_code: string, name: string, quantity: float|int|string, unit_cost: ?float, unit: string, barcode?: string, category?: string, sale_price?: mixed}>  $lines
     */
    public function syncManualLines(int $branchId, int $warehouseId, array $lines, bool $deleteMissing = true): void
    {
        DB::transaction(function () use ($branchId, $warehouseId, $lines, $deleteMissing) {
            $goodIds = [];

            foreach ($lines as $line) {
                $id = $this->upsertLine($branchId, $warehouseId, $line);
                if ($id !== null) {
                    $goodIds[] = $id;
                }
            }

            if ($deleteMissing && $goodIds !== []) {
                OpeningStockBalance::query()
                    ->where('warehouse_id', $warehouseId)
                    ->whereNotIn('good_id', $goodIds)
                    ->delete();
            }
        });
    }

    /**
     * @param  array{article_code: string, name: string, quantity: mixed, unit_cost: mixed, unit?: string, barcode?: string, category?: string, sale_price?: mixed}  $line
     */
    public function upsertLine(int $branchId, int $warehouseId, array $line): ?int
    {
        $code = trim((string) ($line['article_code'] ?? ''));
        if ($code === '') {
            return null;
        }

        $name = trim((string) ($line['name'] ?? ''));
        $quantity = $this->parseDecimal($line['quantity'] ?? 0);
        if ($quantity === null || (float) $quantity <= 0) {
            return null;
        }

        $unit = trim((string) ($line['unit'] ?? '')) ?: 'шт.';
        $unitCost = $this->parseOptionalMoney($line['unit_cost'] ?? null);
        $barcodeRaw = trim((string) ($line['barcode'] ?? ''));
        $barcode = $barcodeRaw === '' ? null : $barcodeRaw;
        $category = trim((string) ($line['category'] ?? ''));
        $category = $category === '' ? null : $category;
        $salePrice = $this->parseOptionalMoney($line['sale_price'] ?? null);

        $good = Good::query()->updateOrCreate(
            ['branch_id' => $branchId, 'article_code' => $code],
            [
                'name' => $name !== '' ? $name : $code,
                'unit' => $unit,
                'barcode' => $barcode,
                'category' => $category,
                'sale_price' => $salePrice,
            ]
        );

        OpeningStockBalance::query()->updateOrCreate(
            ['warehouse_id' => $warehouseId, 'good_id' => $good->id],
            ['branch_id' => $branchId, 'quantity' => $quantity, 'unit_cost' => $unitCost]
        );

        return $good->id;
    }

    /**
     * Приход товара по закупке: увеличивает остаток на складе, пересчитывает закупочную цену средневзвешенно, при необходимости обновляет продажную цену в карточке.
     *
     * @param  array{article_code: string, name: string, quantity: mixed, unit?: string, unit_price?: mixed, sale_price?: mixed, barcode?: string, category?: string}  $line
     */
    public function addIncomingLine(int $branchId, int $warehouseId, array $line): ?Good
    {
        $code = trim((string) ($line['article_code'] ?? ''));
        if ($code === '') {
            return null;
        }

        $name = trim((string) ($line['name'] ?? ''));
        $quantity = $this->parseDecimal($line['quantity'] ?? 0);
        if ($quantity === null || (float) $quantity <= 0) {
            return null;
        }

        $unit = trim((string) ($line['unit'] ?? '')) ?: 'шт.';
        $unitPrice = $this->parseOptionalMoney($line['unit_price'] ?? null);
        $barcodeRaw = trim((string) ($line['barcode'] ?? ''));
        $barcode = $barcodeRaw === '' ? null : $barcodeRaw;
        $category = trim((string) ($line['category'] ?? ''));
        $category = $category === '' ? null : $category;
        $salePrice = $this->parseOptionalMoney($line['sale_price'] ?? null);

        $good = Good::query()
            ->where('branch_id', $branchId)
            ->where('article_code', $code)
            ->first();

        if ($good === null) {
            $good = Good::query()->create([
                'branch_id' => $branchId,
                'article_code' => $code,
                'name' => $name !== '' ? $name : $code,
                'unit' => $unit,
                'barcode' => $barcode,
                'category' => $category,
                'sale_price' => $salePrice,
            ]);
        } else {
            if ($name !== '') {
                $good->name = $name;
            }
            $good->unit = $unit;
            if ($barcode !== null) {
                $good->barcode = $barcode;
            }
            if ($category !== null) {
                $good->category = $category;
            }
            if ($salePrice !== null) {
                $good->sale_price = $salePrice;
            }
            $good->save();
        }

        $addQty = (float) $quantity;

        $balance = OpeningStockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('good_id', $good->id)
            ->first();

        if ($balance === null) {
            OpeningStockBalance::query()->create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'good_id' => $good->id,
                'quantity' => (string) $addQty,
                'unit_cost' => $unitPrice,
            ]);
        } else {
            $oldQty = (float) $balance->quantity;
            $newQty = $oldQty + $addQty;
            $oldCost = $balance->unit_cost !== null ? (float) $balance->unit_cost : null;
            $p = $unitPrice !== null ? (float) $unitPrice : null;

            $newCost = null;
            if ($newQty > 0) {
                if ($oldQty <= 0) {
                    $newCost = $p;
                } elseif ($p !== null && $oldCost !== null) {
                    $newCost = ($oldQty * $oldCost + $addQty * $p) / $newQty;
                } elseif ($p !== null) {
                    $newCost = $p;
                } else {
                    $newCost = $oldCost;
                }
            }

            $balance->quantity = (string) $newQty;
            $balance->unit_cost = $newCost !== null ? (string) round($newCost, 2) : null;
            $balance->save();
        }

        return $good;
    }

    /**
     * Отмена эффекта ранее проведённой строки поступления на остаток (обратная к addIncomingLine для той же пары q и закуп. цены).
     *
     * @throws \RuntimeException
     */
    public function reverseIncomingLine(int $warehouseId, int $goodId, mixed $quantity, mixed $unitPrice): void
    {
        $qRemove = $this->parseDecimal($quantity);
        if ($qRemove === null || (float) $qRemove <= 0) {
            return;
        }

        $q = (float) $qRemove;
        $p = $this->parseOptionalMoney($unitPrice);

        $balance = OpeningStockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('good_id', $goodId)
            ->first();

        if ($balance === null) {
            throw new \RuntimeException('Не найден складской остаток по строке — редактирование документа невозможно.');
        }

        $Q = (float) $balance->quantity;
        $C = $balance->unit_cost !== null ? (float) $balance->unit_cost : null;

        if ($Q + 1e-9 < $q) {
            throw new \RuntimeException('Недостаточно остатка для отмены строки документа (товар мог быть отгружен).');
        }

        $Qnew = $Q - $q;

        if ($Qnew <= 1e-9) {
            $balance->delete();

            return;
        }

        if ($p === null || $C === null) {
            $balance->quantity = (string) round($Qnew, 4);
            $balance->save();

            return;
        }

        $Cnew = ($Q * $C - $q * $p) / $Qnew;
        if (! is_finite($Cnew) || $Cnew < 0) {
            $Cnew = $C;
        }

        $balance->quantity = (string) round($Qnew, 4);
        $balance->unit_cost = (string) round($Cnew, 2);
        $balance->save();
    }

    /**
     * Списание товара со склада при продаже. Средняя закупочная (unit_cost) на остатке не пересчитывается.
     *
     * @throws \RuntimeException
     */
    public function applyOutboundSaleLine(int $warehouseId, int $goodId, mixed $quantity): void
    {
        $qRemove = $this->parseDecimal($quantity);
        if ($qRemove === null || (float) $qRemove <= 0) {
            return;
        }

        $good = Good::query()->find($goodId);
        if ($good !== null && $good->is_service) {
            return;
        }

        $q = (float) $qRemove;

        $balance = OpeningStockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('good_id', $goodId)
            ->first();

        if ($balance === null) {
            throw new \RuntimeException('Нет остатка товара на выбранном складе.');
        }

        $Q = (float) $balance->quantity;
        if ($Q + 1e-9 < $q) {
            throw new \RuntimeException('Недостаточно товара на складе для списания.');
        }

        $Qnew = $Q - $q;

        if ($Qnew <= 1e-9) {
            $balance->delete();

            return;
        }

        $balance->quantity = (string) round($Qnew, 4);
        $balance->save();
    }

    /**
     * Возврат количества на склад при отмене/правке строки реализации (средняя закупочная сохраняется, если строка уже была).
     *
     * @throws \RuntimeException
     */
    /**
     * Перемещение между складами: списание со склада-отправителя по средней и приход на склад-получатель с той же закупочной ценой за единицу.
     *
     * @throws RuntimeException
     */
    public function transferBetweenWarehouses(int $branchId, int $fromWarehouseId, int $toWarehouseId, int $goodId, mixed $quantity): void
    {
        $qRemove = $this->parseDecimal($quantity);
        if ($qRemove === null || (float) $qRemove <= 0) {
            throw new RuntimeException('Укажите количество больше нуля.');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new RuntimeException('Склад отправления и получения должны различаться.');
        }

        $q = (float) $qRemove;

        $good = Good::query()->find($goodId);
        if ($good === null) {
            throw new RuntimeException('Товар не найден.');
        }
        if ($good->is_service) {
            throw new RuntimeException('Услуги не перемещаются по складу.');
        }
        if ((int) $good->branch_id !== $branchId) {
            throw new RuntimeException('Товар не принадлежит филиалу.');
        }

        $balance = OpeningStockBalance::query()
            ->where('warehouse_id', $fromWarehouseId)
            ->where('good_id', $goodId)
            ->first();

        if ($balance === null) {
            throw new RuntimeException('Нет остатка на складе-отправителе.');
        }

        $Q = (float) $balance->quantity;
        if ($Q + 1e-9 < $q) {
            throw new RuntimeException('Недостаточно товара на складе-отправителе.');
        }

        $unitCost = $balance->unit_cost !== null ? (float) $balance->unit_cost : null;

        $this->applyOutboundSaleLine($fromWarehouseId, $goodId, $q);

        $this->addIncomingLine($branchId, $toWarehouseId, [
            'article_code' => $good->article_code,
            'name' => $good->name,
            'quantity' => $q,
            'unit' => $good->unit,
            'unit_price' => $unitCost,
            'barcode' => $good->barcode,
            'category' => $good->category,
            'sale_price' => $good->sale_price,
        ]);
    }

    /**
     * Ревизия: фактическое количество; расхождение с учётным остатком оформляется оприходованием или списанием.
     *
     * @throws RuntimeException
     */
    public function applyAuditAdjustment(int $branchId, int $warehouseId, int $goodId, mixed $countedQuantity): void
    {
        $countedRaw = $this->parseDecimal($countedQuantity);
        if ($countedRaw === null) {
            throw new RuntimeException('Некорректное фактическое количество.');
        }

        $counted = (float) $countedRaw;
        if ($counted < -1e-9) {
            throw new RuntimeException('Фактическое количество не может быть отрицательным.');
        }

        $good = Good::query()->find($goodId);
        if ($good === null) {
            throw new RuntimeException('Товар не найден.');
        }
        if ($good->is_service) {
            throw new RuntimeException('Услуги не участвуют в ревизии.');
        }
        if ((int) $good->branch_id !== $branchId) {
            throw new RuntimeException('Товар не принадлежит филиалу.');
        }

        $balance = OpeningStockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('good_id', $goodId)
            ->first();

        $book = $balance === null ? 0.0 : (float) $balance->quantity;
        $diff = $counted - $book;

        if (abs($diff) < 1e-9) {
            return;
        }

        if ($diff > 0) {
            $unitCost = $balance?->unit_cost !== null ? (float) $balance->unit_cost : null;

            $this->addIncomingLine($branchId, $warehouseId, [
                'article_code' => $good->article_code,
                'name' => $good->name,
                'quantity' => $diff,
                'unit' => $good->unit,
                'unit_price' => $unitCost,
                'barcode' => $good->barcode,
                'category' => $good->category,
                'sale_price' => $good->sale_price,
            ]);

            return;
        }

        $this->applyOutboundSaleLine($warehouseId, $goodId, abs($diff));
    }

    public function reverseOutboundSaleLine(int $branchId, int $warehouseId, int $goodId, mixed $quantity): void
    {
        $qAdd = $this->parseDecimal($quantity);
        if ($qAdd === null || (float) $qAdd <= 0) {
            return;
        }

        $good = Good::query()->find($goodId);
        if ($good !== null && $good->is_service) {
            return;
        }

        $add = (float) $qAdd;

        $balance = OpeningStockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('good_id', $goodId)
            ->first();

        if ($balance === null) {
            OpeningStockBalance::query()->create([
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'good_id' => $goodId,
                'quantity' => (string) round($add, 4),
                'unit_cost' => null,
            ]);

            return;
        }

        $Q = (float) $balance->quantity;
        $balance->quantity = (string) round($Q + $add, 4);
        $balance->save();
    }

    /**
     * @return array{imported: int, errors: list<string>, skipped: int}
     */
    public function importFromFile(string $path, int $branchId, int $warehouseId): array
    {
        $spreadsheet = IOFactory::load($path);
        $data = $spreadsheet->getActiveSheet()->toArray();

        $headerRow = isset($data[0]) && is_array($data[0]) ? $data[0] : [];
        $joined = mb_strtolower(implode(' ', array_map(fn ($c) => (string) $c, $headerRow)));
        $hasHeader = str_contains($joined, 'артикул')
            || str_contains($joined, 'наимен')
            || str_contains($joined, 'колич')
            || str_contains($joined, 'штрих')
            || str_contains($joined, 'категор');

        $startRow = $hasHeader ? 1 : 0;
        $columnMap = $hasHeader
            ? $this->resolveImportColumnMap($headerRow)
            : $this->defaultImportColumnMapNoHeader();

        if (! $hasHeader && isset($data[0]) && is_array($data[0])) {
            $legacyMap = $this->inferLegacyImportColumnMap($data[0]);
            if ($legacyMap !== null) {
                $columnMap = $legacyMap;
            }
        }

        $errors = [];
        $imported = 0;
        $skipped = 0;

        $toUpsert = [];

        for ($r = $startRow, $max = count($data); $r < $max; $r++) {
            $row = $data[$r] ?? [];
            if (! is_array($row)) {
                continue;
            }

            $article = $this->importCell($row, $columnMap['article'] ?? null);
            $name = $this->importCell($row, $columnMap['name'] ?? null);
            $qtyRaw = $this->importCellRaw($row, $columnMap['qty'] ?? null);
            $barcode = $this->importCell($row, $columnMap['barcode'] ?? null);
            $category = $this->importCell($row, $columnMap['category'] ?? null);
            $unit = $this->importCell($row, $columnMap['unit'] ?? null) ?: 'шт.';
            $purchaseRaw = $this->importCellRaw($row, $columnMap['purchase'] ?? null);
            $saleRaw = $this->importCellRaw($row, $columnMap['sale'] ?? null);

            if ($article === '' && $name === '' && ($qtyRaw === null || $qtyRaw === '')) {
                $skipped++;

                continue;
            }

            if ($article === '') {
                $errors[] = 'Строка '.($r + 1).': не указан артикул.';

                continue;
            }

            if ($name === '') {
                $errors[] = 'Строка '.($r + 1).': не указано наименование.';

                continue;
            }

            $quantity = $this->parseDecimal($qtyRaw);
            if ($quantity === null || (float) $quantity <= 0) {
                $errors[] = 'Строка '.($r + 1).': количество должно быть числом больше 0.';

                continue;
            }

            $toUpsert[] = [
                'article_code' => $article,
                'name' => $name,
                'barcode' => $barcode,
                'category' => $category,
                'quantity' => $quantity,
                'unit_cost' => $this->parseOptionalMoney($purchaseRaw),
                'sale_price' => $this->parseOptionalMoney($saleRaw),
                'unit' => $unit !== '' ? $unit : 'шт.',
            ];
        }

        DB::transaction(function () use ($branchId, $warehouseId, $toUpsert, &$imported) {
            foreach ($toUpsert as $line) {
                if ($this->upsertLine($branchId, $warehouseId, $line) !== null) {
                    $imported++;
                }
            }
        });

        return ['imported' => $imported, 'errors' => $errors, 'skipped' => $skipped];
    }

    /**
     * @return array<string, int>
     */
    private function defaultImportColumnMapNoHeader(): array
    {
        return [
            'name' => 0,
            'barcode' => 1,
            'category' => 2,
            'article' => 3,
            'qty' => 4,
            'unit' => 5,
            'purchase' => 6,
            'sale' => 7,
        ];
    }

    /**
     * Старый формат без заголовка: A=артикул, B=наименование, C=кол-во, D=закупка, E=ед. изм.
     *
     * @param  list<mixed>  $probe
     */
    private function inferLegacyImportColumnMap(array $probe): ?array
    {
        $c0 = trim((string) ($probe[0] ?? ''));
        $c1 = trim((string) ($probe[1] ?? ''));
        $c3 = trim((string) ($probe[3] ?? ''));
        if ($c0 === '' || $c1 === '') {
            return null;
        }

        $priceLike = $c3 === '' || is_numeric(str_replace([' ', ','], ['', '.'], $c3));
        if (! $priceLike) {
            return null;
        }

        $maxIdx = -1;
        foreach ($probe as $i => $v) {
            if (trim((string) $v) !== '') {
                $maxIdx = max($maxIdx, (int) $i);
            }
        }
        if ($maxIdx > 5) {
            return null;
        }

        return [
            'article' => 0,
            'name' => 1,
            'qty' => 2,
            'purchase' => 3,
            'unit' => 4,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function resolveImportColumnMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $cell) {
            $key = $this->matchImportHeader(mb_strtolower(trim((string) $cell)));
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = (int) $idx;
            }
        }

        if (isset($map['article'], $map['name'], $map['qty'])) {
            return $map;
        }

        return [
            'article' => 0,
            'name' => 1,
            'qty' => 2,
            'purchase' => 3,
            'unit' => 4,
        ];
    }

    private function matchImportHeader(string $h): ?string
    {
        if ($h === '') {
            return null;
        }

        if (str_contains($h, 'штрих') || str_contains($h, 'ean') || str_contains($h, 'gtin')) {
            return 'barcode';
        }
        if (str_contains($h, 'категор')) {
            return 'category';
        }
        if (str_contains($h, 'артикул')) {
            return 'article';
        }
        if ($h === 'код' || str_starts_with($h, 'код ')) {
            return 'article';
        }
        if (str_contains($h, 'наименование') || str_contains($h, 'название')) {
            return 'name';
        }
        if (str_contains($h, 'товар') && ! str_contains($h, 'артикул')) {
            return 'name';
        }
        if (str_contains($h, 'колич') || str_contains($h, 'кол-во') || str_contains($h, 'кол -')) {
            return 'qty';
        }
        if (str_contains($h, 'ед.') || str_contains($h, 'ед ') || (str_contains($h, 'изм') && str_contains($h, 'ед'))) {
            return 'unit';
        }
        if (str_contains($h, 'закуп') || str_contains($h, 'себестоим')) {
            return 'purchase';
        }
        if (str_contains($h, 'продаж') || str_contains($h, 'розниц')) {
            return 'sale';
        }
        if (str_contains($h, 'цена') && str_contains($h, 'продаж')) {
            return 'sale';
        }
        if (str_contains($h, 'цена') && (str_contains($h, 'закуп') || str_contains($h, 'себестоим'))) {
            return 'purchase';
        }
        if (str_contains($h, 'цена') && str_contains($h, 'ед') && ! str_contains($h, 'продаж')) {
            return 'purchase';
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     */
    private function importCell(array $row, ?int $idx): string
    {
        if ($idx === null) {
            return '';
        }

        return isset($row[$idx]) ? trim((string) $row[$idx]) : '';
    }

    /**
     * @param  list<mixed>  $row
     */
    private function importCellRaw(array $row, ?int $idx): mixed
    {
        if ($idx === null) {
            return null;
        }

        return $row[$idx] ?? null;
    }

    public function parseDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $s = str_replace([' ', ','], ['', '.'], trim((string) $value));
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return $s;
    }

    public function parseOptionalMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = $this->parseDecimal($value);
        if ($s === null) {
            return null;
        }

        if ((float) $s < 0) {
            return null;
        }

        return $s;
    }
}
