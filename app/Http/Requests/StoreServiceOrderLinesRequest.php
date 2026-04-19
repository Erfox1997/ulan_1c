<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Good;
use App\Models\OpeningStockBalance;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreServiceOrderLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*.article_code' => ['nullable', 'string', 'max:128'],
            'lines.*.quantity' => ['nullable'],
            'lines.*.unit_price' => ['nullable'],
            'lines.*.performer_employee_id' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var ServiceOrder|null $order */
            $order = $this->route('serviceOrder');
            if (! $order instanceof ServiceOrder) {
                $v->errors()->add('lines', 'Заявка не найдена.');

                return;
            }

            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                $v->errors()->add('lines', 'Некорректный формат данных.');

                return;
            }

            $branchId = (int) $this->user()->branch_id;
            $warehouseId = (int) $order->warehouse_id;
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

                $qty = $line['quantity'] ?? null;
                if ($qty === null || $qty === '') {
                    $v->errors()->add("lines.{$i}.quantity", 'Укажите количество для «'.$code.'».');
                } elseif (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $qty)) || (float) str_replace([' ', ','], ['', '.'], (string) $qty) <= 0) {
                    $v->errors()->add("lines.{$i}.quantity", 'Количество должно быть числом больше 0.');
                }

                $price = $line['unit_price'] ?? null;
                if ($price === null || $price === '') {
                    $v->errors()->add("lines.{$i}.unit_price", 'Укажите цену для «'.$code.'».');
                } elseif (! is_numeric(str_replace([' ', ','], ['', '.'], (string) $price)) || (float) str_replace([' ', ','], ['', '.'], (string) $price) < 0) {
                    $v->errors()->add("lines.{$i}.unit_price", 'Цена не может быть отрицательной.');
                }

                $good = Good::query()
                    ->where('branch_id', $branchId)
                    ->where('article_code', $code)
                    ->first();

                if ($good === null) {
                    $v->errors()->add("lines.{$i}.article_code", 'Позиция «'.$code.'» не найдена в номенклатуре филиала.');

                    continue;
                }

                if ($good->is_service) {
                    $pid = $line['performer_employee_id'] ?? null;
                    if ($pid === null || $pid === '') {
                        $v->errors()->add("lines.{$i}.performer_employee_id", 'Укажите мастера для услуги «'.$good->name.'».');
                    } else {
                        $ok = Employee::query()
                            ->where('branch_id', $branchId)
                            ->where('job_type', Employee::JOB_MASTER)
                            ->whereKey((int) $pid)
                            ->exists();
                        if (! $ok) {
                            $v->errors()->add("lines.{$i}.performer_employee_id", 'Некорректный мастер для услуги «'.$good->name.'».');
                        }
                    }
                }

                if (! $good->is_service && $warehouseId > 0 && $qty !== null && $qty !== '' && is_numeric(str_replace([' ', ','], ['', '.'], (string) $qty))) {
                    $qNum = (float) str_replace([' ', ','], ['', '.'], (string) $qty);
                    $balance = OpeningStockBalance::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('good_id', $good->id)
                        ->first();
                    $avail = $balance !== null ? (float) $balance->quantity : 0.0;
                    if ($avail + 1e-9 < $qNum) {
                        $v->errors()->add(
                            "lines.{$i}.quantity",
                            'На складе недостаточно «'.$code.'» (доступно: '.rtrim(rtrim((string) $avail, '0'), '.').').'
                        );
                    }
                }
            }

            $dupArticles = array_keys(array_filter($articleCounts, fn (int $c): bool => $c > 1));
            if ($dupArticles !== []) {
                $v->errors()->add('lines', 'В заявке повторяются артикулы: '.implode(', ', $dupArticles));
            }

            if (! $hasAny) {
                $v->errors()->add('lines', 'Добавьте минимум одну позицию.');
            }
        });
    }
}
