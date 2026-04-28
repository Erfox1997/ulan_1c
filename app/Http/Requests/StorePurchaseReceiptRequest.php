<?php

namespace App\Http\Requests;

use App\Support\PurchaseReceiptLineDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseReceiptRequest extends FormRequest
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
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'document_date' => ['required', 'date'],
            'lines' => ['required', 'array'],
            'lines.*.article_code' => ['nullable', 'string', 'max:128'],
            'lines.*.name' => ['nullable', 'string', 'max:500'],
            'lines.*.barcode' => ['nullable', 'string', 'max:128'],
            'lines.*.markup_percent' => ['nullable', 'string', 'max:32'],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.quantity' => ['nullable'],
            'lines.*.unit_price' => ['nullable'],
            'lines.*.sale_price' => ['nullable'],
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

            foreach ($lines as $i => $line) {
                if (! is_array($line)) {
                    continue;
                }
                if (PurchaseReceiptLineDraft::isGhost($line)) {
                    continue;
                }

                $hasAny = true;
                $code = trim((string) ($line['article_code'] ?? ''));
                if ($code !== '') {
                    $articleCounts[$code] = ($articleCounts[$code] ?? 0) + 1;
                }

                if (trim((string) ($line['name'] ?? '')) === '') {
                    $v->errors()->add("lines.{$i}.name", 'Укажите наименование.');
                }

                $qty = $line['quantity'] ?? null;
                if ($qty === null || $qty === '') {
                    $v->errors()->add("lines.{$i}.quantity", 'Укажите количество.');
                } elseif (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $qty)) || (float) str_replace([' ', ','], ['', '.'], (string) $qty) <= 0) {
                    $v->errors()->add("lines.{$i}.quantity", 'Количество должно быть числом больше 0.');
                }

                $price = $line['unit_price'] ?? null;
                if ($price === null || $price === '') {
                    $v->errors()->add("lines.{$i}.unit_price", 'Укажите цену закупки.');
                } elseif (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $price)) || (float) str_replace([' ', ','], ['', '.'], (string) $price) < 0) {
                    $v->errors()->add("lines.{$i}.unit_price", 'Цена закупки не может быть отрицательной.');
                }

                $sale = $line['sale_price'] ?? null;
                if ($sale !== null && $sale !== '' && (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $sale)) || (float) str_replace([' ', ','], ['', '.'], (string) $sale) < 0)) {
                    $v->errors()->add("lines.{$i}.sale_price", 'Продажная цена не может быть отрицательной.');
                }
            }

            $dupArticles = array_keys(array_filter($articleCounts, fn (int $c): bool => $c > 1));
            if ($dupArticles !== []) {
                $v->errors()->add('lines', 'В документе повторяются артикулы: '.implode(', ', $dupArticles));
            }

            if (! $hasAny) {
                $v->errors()->add('lines', 'Добавьте хотя бы одну позицию по наименованию, количеству и закупке.');
            }
        });
    }
}
