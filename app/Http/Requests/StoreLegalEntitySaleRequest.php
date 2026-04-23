<?php

namespace App\Http\Requests;

use App\Models\Good;
use App\Models\LegalEntitySale;
use App\Models\OpeningStockBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLegalEntitySaleRequest extends FormRequest
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
            'buyer_pin' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]*$/'],
            'counterparty_id' => [
                'nullable',
                'integer',
                Rule::exists('counterparties', 'id')->where(fn ($q) => $q->where('branch_id', $this->user()->branch_id)),
            ],
            'document_date' => ['required', 'date'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'issue_esf' => ['sometimes', 'boolean'],
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
            $warehouseId = (int) $this->input('warehouse_id');
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
                    $v->errors()->add("lines.{$i}.unit_price", 'Укажите цену продажи для «'.$code.'».');
                } elseif (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $price)) || (float) str_replace([' ', ','], ['', '.'], (string) $price) < 0) {
                    $v->errors()->add("lines.{$i}.unit_price", 'Цена продажи не может быть отрицательной.');
                }

                $good = Good::query()
                    ->where('branch_id', $branchId)
                    ->where('article_code', $code)
                    ->first();

                if ($good === null) {
                    $v->errors()->add("lines.{$i}.article_code", 'Товар «'.$code.'» не найден — заведите карточку через поступление или начальные остатки.');

                    continue;
                }

                if (! $good->is_service && $warehouseId > 0 && $qty !== null && $qty !== '' && is_numeric(str_replace([' ', ','], ['', '.'], (string) $qty))) {
                    $qNum = (float) str_replace([' ', ','], ['', '.'], (string) $qty);
                    $balance = OpeningStockBalance::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('good_id', $good->id)
                        ->first();
                    $avail = $balance !== null ? (float) $balance->quantity : 0.0;
                    if ($avail + 1e-9 < $qNum) {
                        $availText = rtrim(rtrim((string) $avail, '0'), '.');
                        if ($availText === '' || $availText === '-') {
                            $availText = '0';
                        }
                        $goodName = trim((string) ($good->name ?? ''));
                        $itemLabel = $goodName !== ''
                            ? '«'.$code.'», наименование: «'.$goodName.'»'
                            : '«'.$code.'»';
                        $v->errors()->add(
                            "lines.{$i}.quantity",
                            'На складе недостаточно '.$itemLabel.' (доступно: '.$availText.').'
                        );
                    }
                }

            }

            $dupArticles = array_keys(array_filter($articleCounts, fn (int $c): bool => $c > 1));
            if ($dupArticles !== []) {
                $v->errors()->add('lines', 'В документе повторяются артикулы: '.implode(', ', $dupArticles));
            }

            if (! $hasAny) {
                $v->errors()->add('lines', 'Добавьте хотя бы одну позицию с артикулом.');
            }

            if ($this->boolean('issue_esf')) {
                $cpId = $this->input('counterparty_id');
                $temp = new LegalEntitySale([
                    'branch_id' => (int) $this->user()->branch_id,
                    'buyer_name' => trim((string) $this->input('buyer_name', '')),
                    'buyer_pin' => preg_replace('/\D+/', '', (string) $this->input('buyer_pin', '')),
                    'counterparty_id' => $cpId !== null && $cpId !== '' ? (int) $cpId : null,
                ]);
                if (strlen($temp->resolvedBuyerPinForEsf()) < 10) {
                    $v->errors()->add(
                        'buyer_pin',
                        'Для ЭСФ нужен ИНН покупателя: заполните его в карточке контрагента и выберите покупателя из подсказки (или совпадение наименования со справочником), либо введите ИНН в поле ниже.'
                    );
                }
            }
        });
    }
}
