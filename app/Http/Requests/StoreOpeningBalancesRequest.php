<?php

namespace App\Http\Requests;

use App\Models\OpeningStockBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOpeningBalancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('branch_id', $this->user()->branch_id)),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'deleted_good_ids' => ['nullable', 'array'],
            'deleted_good_ids.*' => ['integer', 'min:1'],
            'lines' => ['required', 'array'],
            'lines.*.article_code' => ['nullable', 'string', 'max:100'],
            'lines.*.name' => ['nullable', 'string', 'max:500'],
            'lines.*.barcode' => ['nullable', 'string', 'max:64'],
            'lines.*.category' => ['nullable', 'string', 'max:120'],
            'lines.*.quantity' => ['nullable'],
            'lines.*.unit_cost' => ['nullable'],
            'lines.*.wholesale_price' => ['nullable'],
            'lines.*.sale_price' => ['nullable'],
            'lines.*.oem' => ['nullable', 'string', 'max:120'],
            'lines.*.factory_number' => ['nullable', 'string', 'max:120'],
            'lines.*.min_stock' => ['nullable'],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.good_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                $v->errors()->add('lines', 'Некорректный формат данных.');

                return;
            }

            $hasAny = false;
            $articleCounts = [];
            $deletedRaw = $this->input('deleted_good_ids', []);
            $hasDeletes = is_array($deletedRaw) && $deletedRaw !== [];
            $hasClearedSavedLines = false;

            foreach ($lines as $i => $line) {
                if (! is_array($line)) {
                    continue;
                }
                $code = trim((string) ($line['article_code'] ?? ''));
                if ($code === '') {
                    $gRaw = $line['good_id'] ?? null;
                    if ($gRaw !== null && $gRaw !== '' && (int) $gRaw > 0) {
                        $hasClearedSavedLines = true;
                    }

                    continue;
                }

                $hasAny = true;
                $articleCounts[$code] = ($articleCounts[$code] ?? 0) + 1;

                if (trim((string) ($line['name'] ?? '')) === '') {
                    $v->errors()->add("lines.{$i}.name", 'Укажите наименование для артикула «'.$code.'».');
                }

                $qty = $line['quantity'] ?? null;
                if ($qty === null || $qty === '') {
                    $v->errors()->add("lines.{$i}.quantity", 'Укажите количество для «'.$code.'».');
                } elseif (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $qty)) || (float) str_replace([' ', ','], ['', '.'], (string) $qty) <= 0) {
                    $v->errors()->add("lines.{$i}.quantity", 'Количество должно быть числом больше 0.');
                }

                $cost = $line['unit_cost'] ?? null;
                if ($cost !== null && $cost !== '' && (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $cost)) || (float) str_replace([' ', ','], ['', '.'], (string) $cost) < 0)) {
                    $v->errors()->add("lines.{$i}.unit_cost", 'Закупочная цена не может быть отрицательной.');
                }

                $wholesale = $line['wholesale_price'] ?? null;
                if ($wholesale !== null && $wholesale !== '' && (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $wholesale)) || (float) str_replace([' ', ','], ['', '.'], (string) $wholesale) < 0)) {
                    $v->errors()->add("lines.{$i}.wholesale_price", 'Оптовая цена не может быть отрицательной.');
                }

                $sale = $line['sale_price'] ?? null;
                if ($sale !== null && $sale !== '' && (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $sale)) || (float) str_replace([' ', ','], ['', '.'], (string) $sale) < 0)) {
                    $v->errors()->add("lines.{$i}.sale_price", 'Продажная цена не может быть отрицательной.');
                }

                $minSt = $line['min_stock'] ?? null;
                if ($minSt !== null && $minSt !== '' && (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $minSt)) || (float) str_replace([' ', ','], ['', '.'], (string) $minSt) < 0)) {
                    $v->errors()->add("lines.{$i}.min_stock", 'Минимальный остаток должен быть числом не меньше нуля.');
                }

            }

            $dupArticles = array_keys(array_filter($articleCounts, fn (int $c): bool => $c > 1));
            if ($dupArticles !== []) {
                $v->errors()->add('lines', 'Повторяются артикулы: '.implode(', ', $dupArticles));
            }

            if (! $hasAny && ! $hasDeletes && ! $hasClearedSavedLines) {
                $v->errors()->add('lines', 'Добавьте хотя бы одну позицию с артикулом или удалите строки с остатком.');
            }

            $warehouseId = (int) $this->input('warehouse_id');
            $branchId = (int) $this->user()->branch_id;
            if (is_array($deletedRaw)) {
                foreach ($deletedRaw as $gid) {
                    if (! is_numeric($gid)) {
                        $v->errors()->add('deleted_good_ids', 'Некорректный идентификатор удаляемой позиции.');

                        continue;
                    }
                    $goodId = (int) $gid;
                    $exists = OpeningStockBalance::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('branch_id', $branchId)
                        ->where('good_id', $goodId)
                        ->exists();
                    if (! $exists) {
                        $v->errors()->add('deleted_good_ids', 'Недопустимое удаление позиции #'.$goodId.'.');
                    }
                }
            }

            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                if (trim((string) ($line['article_code'] ?? '')) !== '') {
                    continue;
                }
                $gRaw = $line['good_id'] ?? null;
                if ($gRaw === null || $gRaw === '') {
                    continue;
                }
                if (! is_numeric($gRaw)) {
                    $v->errors()->add('lines', 'Некорректный идентификатор сохранённой позиции.');

                    continue;
                }
                $clearedId = (int) $gRaw;
                if ($clearedId <= 0) {
                    continue;
                }
                $existsCleared = OpeningStockBalance::query()
                    ->where('warehouse_id', $warehouseId)
                    ->where('branch_id', $branchId)
                    ->where('good_id', $clearedId)
                    ->exists();
                if (! $existsCleared) {
                    $v->errors()->add('lines', 'Недопустимое изменение позиции #'.$clearedId.'.');
                }
            }
        });
    }
}
