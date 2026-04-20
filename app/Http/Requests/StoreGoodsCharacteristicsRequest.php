<?php

namespace App\Http\Requests;

use App\Models\Good;
use App\Services\GoodsCharacteristicsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoodsCharacteristicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;
        $missingKeys = GoodsCharacteristicsService::missingFilterKeyList();

        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'all_incomplete' => ['nullable', 'boolean'],
            'missing' => ['nullable', 'array'],
            'missing.*' => ['string', Rule::in($missingKeys)],
            'page' => ['nullable', 'integer', 'min:1'],
            'lines' => ['required', 'array'],
            'lines.*.good_id' => [
                'required',
                'integer',
                Rule::exists('goods', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)->where('is_service', false)),
            ],
            'lines.*.article_code' => ['required', 'string', 'max:100'],
            'lines.*.name' => ['required', 'string', 'max:500'],
            'lines.*.barcode' => ['nullable', 'string', 'max:64'],
            'lines.*.category' => ['nullable', 'string', 'max:120'],
            'lines.*.unit_cost' => ['nullable'],
            'lines.*.wholesale_price' => ['nullable'],
            'lines.*.sale_price' => ['nullable'],
            'lines.*.oem' => ['nullable', 'string', 'max:120'],
            'lines.*.factory_number' => ['nullable', 'string', 'max:120'],
            'lines.*.min_stock' => ['nullable'],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                return;
            }
            $branchId = (int) $this->user()->branch_id;
            $articleCounts = [];

            foreach ($lines as $i => $line) {
                if (! is_array($line)) {
                    continue;
                }
                $code = trim((string) ($line['article_code'] ?? ''));
                if ($code !== '') {
                    $articleCounts[$code] = ($articleCounts[$code] ?? 0) + 1;
                    $gid = (int) ($line['good_id'] ?? 0);
                    $dupOther = Good::query()
                        ->where('branch_id', $branchId)
                        ->where('article_code', $code)
                        ->where('id', '!=', $gid)
                        ->exists();
                    if ($dupOther) {
                        $v->errors()->add("lines.{$i}.article_code", 'Артикул «'.$code.'» уже занят другим товаром.');
                    }
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
                $v->errors()->add('lines', 'Повторяются артикулы в форме: '.implode(', ', $dupArticles));
            }
        });
    }
}
