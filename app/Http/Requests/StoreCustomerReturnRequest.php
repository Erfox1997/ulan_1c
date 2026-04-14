<?php

namespace App\Http\Requests;

use App\Models\Good;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerReturnRequest extends FormRequest
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
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'document_date' => ['required', 'date'],
            'lines' => ['required', 'array'],
            'lines.*.article_code' => ['nullable', 'string', 'max:128'],
            'lines.*.name' => ['nullable', 'string', 'max:500'],
            'lines.*.barcode' => ['nullable', 'string', 'max:128'],
            'lines.*.category' => ['nullable', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:32'],
            'lines.*.quantity' => ['nullable'],
            'lines.*.unit_price' => ['nullable'],
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

            $branchId = (int) $this->user()->branch_id;
            $hasAny = false;
            $articleCounts = [];

            foreach ($lines as $i => $line) {
                if (! is_array($line)) {
                    continue;
                }
                $code = trim((string) ($line['article_code'] ?? ''));
                if ($code === '') {
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

                $price = $line['unit_price'] ?? null;
                if ($price === null || $price === '') {
                    $v->errors()->add("lines.{$i}.unit_price", 'Укажите сумму/цену возврата для «'.$code.'».');
                } elseif (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $price)) || (float) str_replace([' ', ','], ['', '.'], (string) $price) < 0) {
                    $v->errors()->add("lines.{$i}.unit_price", 'Сумма возврата не может быть отрицательной.');
                }

                $good = Good::query()
                    ->where('branch_id', $branchId)
                    ->where('article_code', $code)
                    ->first();

                if ($good === null) {
                    $v->errors()->add("lines.{$i}.article_code", 'Товар «'.$code.'» не найден — заведите карточку через поступление или начальные остатки.');
                }
            }

            $dupArticles = array_keys(array_filter($articleCounts, fn (int $c): bool => $c > 1));
            if ($dupArticles !== []) {
                $v->errors()->add('lines', 'В документе повторяются артикулы: '.implode(', ', $dupArticles));
            }

            if (! $hasAny) {
                $v->errors()->add('lines', 'Добавьте хотя бы одну позицию с артикулом.');
            }
        });
    }
}
