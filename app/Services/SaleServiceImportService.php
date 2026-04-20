<?php

namespace App\Services;

use App\Models\Good;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SaleServiceImportService
{
    public function __construct(
        private readonly OpeningBalanceService $openingBalanceService
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFromFile(string $path, int $branchId): array
    {
        $errors = [];
        $imported = 0;
        $skipped = 0;

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Не удалось прочитать файл: '.$e->getMessage()],
            ];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === [] || $rows === [[]]) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Файл пустой.']];
        }

        $headerRow = array_shift($rows);
        if (! is_array($headerRow)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Некорректная первая строка (ожидались заголовки).']];
        }

        $colMap = $this->mapHeaders($headerRow);
        if ($colMap['name'] === null) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['В первой строке не найден столбец «Наименование».'],
            ];
        }

        $lineNo = 1;
        $toInsert = [];

        foreach ($rows as $row) {
            $lineNo++;
            if (! is_array($row)) {
                continue;
            }
            $name = $this->cell($row, $colMap['name']);
            if ($name === '') {
                if ($this->rowLooksEmpty($row)) {
                    $skipped++;

                    continue;
                }
                $errors[] = "Строка {$lineNo}: не указано наименование.";

                continue;
            }

            if (mb_strlen($name) > 500) {
                $errors[] = "Строка {$lineNo}: наименование длиннее 500 символов.";

                continue;
            }

            $unitRaw = $this->cell($row, $colMap['unit']);
            $unit = trim($unitRaw) !== '' ? trim($unitRaw) : 'усл.';
            if (mb_strlen($unit) > 32) {
                $errors[] = "Строка {$lineNo}: единица измерения длиннее 32 символов.";

                continue;
            }

            $priceRaw = $this->cell($row, $colMap['sale_price']);
            $salePrice = $this->openingBalanceService->parseOptionalMoney($priceRaw);
            if ($priceRaw !== '' && $salePrice === null) {
                $errors[] = "Строка {$lineNo}: в «Цена» укажите число (например 1000 или 500,50).";

                continue;
            }

            $toInsert[] = [
                'branch_id' => $branchId,
                'article_code' => $this->generateUniqueServiceArticleCode($branchId),
                'name' => $name,
                'unit' => $unit ?: 'усл.',
                'sale_price' => $salePrice,
                'is_service' => true,
            ];
        }

        if ($toInsert !== []) {
            DB::transaction(function () use ($toInsert, &$imported): void {
                foreach ($toInsert as $payload) {
                    Good::query()->create($payload);
                    $imported++;
                }
            });
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 50),
        ];
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array{name: ?int, unit: ?int, sale_price: ?int}
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [
            'name' => null,
            'unit' => null,
            'sale_price' => null,
        ];

        foreach ($headerRow as $i => $cell) {
            $key = $this->normalizeHeader((string) $cell);
            if ($key === '') {
                continue;
            }

            if ($map['name'] === null && $this->headerIs($key, ['наименование', 'name', 'название'])) {
                $map['name'] = (int) $i;

                continue;
            }
            if ($map['unit'] === null && $this->headerIs($key, ['единица', 'ед', 'unit', 'ед. изм', 'ед изм'])) {
                $map['unit'] = (int) $i;

                continue;
            }
            if ($map['sale_price'] === null && $this->headerIs($key, ['цена', 'price', 'стоимость', 'sale_price', 'продаж'])) {
                $map['sale_price'] = (int) $i;

                continue;
            }
        }

        return $map;
    }

    private function headerIs(string $normalized, array $candidates): bool
    {
        foreach ($candidates as $c) {
            if ($normalized === $c || str_contains($normalized, $c)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = str_replace(['*', '—', '–'], '', $s);
        $s = trim($s);

        return $s;
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private function cell(array $row, ?int $colIndex): string
    {
        if ($colIndex === null) {
            return '';
        }
        $v = $row[$colIndex] ?? '';

        return trim((string) $v);
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private function rowLooksEmpty(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function generateUniqueServiceArticleCode(int $branchId): string
    {
        for ($i = 0; $i < 25; $i++) {
            $code = 'SVC-'.Str::ulid();
            if (! Good::query()->where('branch_id', $branchId)->where('article_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Не удалось сгенерировать код услуги.');
    }
}
