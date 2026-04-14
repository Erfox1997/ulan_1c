<?php

namespace App\Services;

use App\Models\Counterparty;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CounterpartyImportService
{
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

            $kind = $this->parseKind($this->cell($row, $colMap['kind']));
            if ($kind === null) {
                $errors[] = "Строка {$lineNo}: не удалось распознать тип (Покупатель / Поставщик / Прочее).";

                continue;
            }

            $legalForm = $this->parseLegalForm($this->cell($row, $colMap['legal_form']));
            if ($legalForm === null) {
                $errors[] = "Строка {$lineNo}: не удалось распознать правовую форму (ИП / ОсОО / …).";

                continue;
            }

            $inn = $this->nullableString($this->cell($row, $colMap['inn']));
            $phone = $this->nullableString($this->cell($row, $colMap['phone']));
            $address = $this->nullableString($this->cell($row, $colMap['address']));

            $debtRaw = $this->cell($row, $colMap['opening_debt']);
            $debtParsed = $this->parseOpeningDebtCell($debtRaw);
            if (! $debtParsed['ok']) {
                $errors[] = "Строка {$lineNo}: в «Начальный долг» укажите число (например 1000 или 500,50).";

                continue;
            }
            $openingDebt = $debtParsed['value'];
            $debtBuyer = '0.00';
            $debtSupplier = '0.00';
            if ($openingDebt !== null) {
                if ($kind === Counterparty::KIND_SUPPLIER) {
                    $debtSupplier = $openingDebt;
                } else {
                    $debtBuyer = $openingDebt;
                }
            }

            $toInsert[] = [
                'branch_id' => $branchId,
                'kind' => $kind,
                'name' => $name,
                'legal_form' => $legalForm,
                'full_name' => Counterparty::buildFullName($legalForm, $name),
                'inn' => $inn,
                'phone' => $phone,
                'address' => $address,
                'opening_debt_as_buyer' => $debtBuyer,
                'opening_debt_as_supplier' => $debtSupplier,
            ];
        }

        if ($toInsert !== []) {
            DB::transaction(function () use ($toInsert, &$imported): void {
                foreach ($toInsert as $payload) {
                    Counterparty::query()->create($payload);
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
     * @return array{name: ?int, kind: ?int, legal_form: ?int, opening_debt: ?int, inn: ?int, phone: ?int, address: ?int}
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [
            'name' => null,
            'kind' => null,
            'legal_form' => null,
            'opening_debt' => null,
            'inn' => null,
            'phone' => null,
            'address' => null,
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
            if ($map['kind'] === null && $this->headerIs($key, ['тип', 'kind', 'вид', 'роль'])) {
                $map['kind'] = (int) $i;

                continue;
            }
            if ($map['legal_form'] === null && $this->headerIs($key, ['правовая форма', 'опф', 'legal_form', 'организационно-правовая форма'])) {
                $map['legal_form'] = (int) $i;

                continue;
            }
            if ($map['opening_debt'] === null && $this->headerIs($key, ['начальный долг', 'долг', 'opening_debt', 'сальдо'])) {
                $map['opening_debt'] = (int) $i;

                continue;
            }
            if ($map['inn'] === null && $this->headerIs($key, ['инн', 'inn'])) {
                $map['inn'] = (int) $i;

                continue;
            }
            if ($map['phone'] === null && $this->headerIs($key, ['телефон', 'phone', 'тел'])) {
                $map['phone'] = (int) $i;

                continue;
            }
            if ($map['address'] === null && $this->headerIs($key, ['адрес', 'address'])) {
                $map['address'] = (int) $i;

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

    private function nullableString(string $s): ?string
    {
        return $s === '' ? null : $s;
    }

    /**
     * @return array{ok: bool, value: ?string} value — отформатированная сумма или null если ячейка пустая
     */
    private function parseOpeningDebtCell(string $raw): array
    {
        $s = trim(str_replace(["\xc2\xa0", ' '], ['', ''], $raw));
        if ($s === '') {
            return ['ok' => true, 'value' => null];
        }
        $s = str_replace(',', '.', $s);
        if (! is_numeric($s)) {
            return ['ok' => false, 'value' => null];
        }

        return ['ok' => true, 'value' => number_format((float) $s, 2, '.', '')];
    }

    private function parseKind(string $raw): ?string
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') {
            return Counterparty::KIND_SUPPLIER;
        }

        $exact = [
            'покупатель' => Counterparty::KIND_BUYER,
            'buyer' => Counterparty::KIND_BUYER,
            'поставщик' => Counterparty::KIND_SUPPLIER,
            'supplier' => Counterparty::KIND_SUPPLIER,
            'прочее' => Counterparty::KIND_OTHER,
            'other' => Counterparty::KIND_OTHER,
        ];
        if (isset($exact[$s])) {
            return $exact[$s];
        }
        if (str_contains($s, 'покуп') || str_starts_with($s, 'клиент')) {
            return Counterparty::KIND_BUYER;
        }
        if (str_contains($s, 'постав')) {
            return Counterparty::KIND_SUPPLIER;
        }
        if (str_contains($s, 'проч')) {
            return Counterparty::KIND_OTHER;
        }

        return null;
    }

    private function parseLegalForm(string $raw): ?string
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') {
            return Counterparty::LEGAL_OSOO;
        }

        $exact = [
            'ип' => Counterparty::LEGAL_IP,
            'ip' => Counterparty::LEGAL_IP,
            'осоо' => Counterparty::LEGAL_OSOO,
            'osoo' => Counterparty::LEGAL_OSOO,
            'ооо' => Counterparty::LEGAL_OSOO,
            'физ' => Counterparty::LEGAL_INDIVIDUAL,
            'физлицо' => Counterparty::LEGAL_INDIVIDUAL,
            'физ. лицо' => Counterparty::LEGAL_INDIVIDUAL,
            'физ лицо' => Counterparty::LEGAL_INDIVIDUAL,
            'individual' => Counterparty::LEGAL_INDIVIDUAL,
            'прочее' => Counterparty::LEGAL_OTHER,
            'other' => Counterparty::LEGAL_OTHER,
        ];
        if (isset($exact[$s])) {
            return $exact[$s];
        }
        if (str_contains($s, 'ип') && ! str_contains($s, 'осоо')) {
            return Counterparty::LEGAL_IP;
        }
        if (str_contains($s, 'осоо') || str_contains($s, 'ооо')) {
            return Counterparty::LEGAL_OSOO;
        }
        if (str_contains($s, 'физ')) {
            return Counterparty::LEGAL_INDIVIDUAL;
        }

        return null;
    }
}
