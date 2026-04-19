<?php

namespace App\Http\Requests;

use App\Models\Good;
use App\Models\OrganizationBankAccount;
use App\Models\OpeningStockBalance;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRetailSaleCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $date = $this->input('document_date');
        if ($date === null || $date === '') {
            $draft = $this->session()->get('retail_checkout_draft');
            $fromDraft = is_array($draft) ? trim((string) ($draft['document_date'] ?? '')) : '';
            $this->merge([
                'document_date' => $fromDraft !== '' ? $fromDraft : now()->toDateString(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'document_date' => ['required', 'date'],
            'checkout_action' => ['nullable', 'string', 'in:with_receipt,without_receipt'],
            'payments' => ['nullable', 'array'],
            'payments.*.organization_bank_account_id' => ['nullable', 'integer'],
            'payments.*.amount' => ['nullable', 'string'],
            'debtor_name' => ['nullable', 'string', 'max:255'],
            'debtor_phone' => ['nullable', 'string', 'max:64'],
            'debtor_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $draft = $this->session()->get('retail_checkout_draft');
            if (! is_array($draft)) {
                $v->errors()->add('checkout', 'Сессия оформления истекла. Вернитесь к корзине и нажмите «К оплате» снова.');

                return;
            }

            $lines = $draft['lines'] ?? [];
            if (! is_array($lines) || $lines === []) {
                $v->errors()->add('checkout', 'Чек пуст. Вернитесь к корзине.');

                return;
            }

            $branchId = (int) $this->user()->branch_id;
            $warehouseId = (int) ($draft['warehouse_id'] ?? 0);
            if ($warehouseId <= 0) {
                $v->errors()->add('checkout', 'Не указан склад.');

                return;
            }

            $opening = app(OpeningBalanceService::class);
            $total = '0';
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
                $good = Good::query()
                    ->where('branch_id', $branchId)
                    ->where('article_code', $code)
                    ->first();
                if ($good === null) {
                    $v->errors()->add('lines', 'Товар «'.$code.'» не найден.');

                    continue;
                }
                $qty = $opening->parseDecimal($line['quantity'] ?? 0);
                $price = $opening->parseOptionalMoney($line['unit_price'] ?? null);
                if ($qty === null || $qty === '0') {
                    $v->errors()->add('lines', 'Проверьте количество для «'.$code.'».');

                    continue;
                }
                if ($price === null) {
                    $v->errors()->add('lines', 'Проверьте цену для «'.$code.'».');

                    continue;
                }
                if (! $good->is_service && $warehouseId > 0) {
                    $balance = OpeningStockBalance::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('good_id', $good->id)
                        ->first();
                    $avail = $balance !== null ? (float) $balance->quantity : 0.0;
                    $qNum = (float) $qty;
                    if ($avail + 1e-9 < $qNum) {
                        $v->errors()->add('lines', 'На складе недостаточно «'.$code.'» (доступно: '.rtrim(rtrim((string) $avail, '0'), '.').').');
                    }
                }
                $lineSum = bcmul((string) $qty, (string) $price, 2);
                $total = bcadd($total, $lineSum, 2);
            }

            if (! $hasAny) {
                $v->errors()->add('lines', 'Добавьте в чек минимум одну позицию.');

                return;
            }

            $dupArticles = array_keys(array_filter($articleCounts, fn (int $c): bool => $c > 1));
            if ($dupArticles !== []) {
                $v->errors()->add('lines', 'В чеке повторяются артикулы: '.implode(', ', $dupArticles));

                return;
            }

            $payments = $this->input('payments', []);
            if (! is_array($payments)) {
                $payments = [];
            }

            $sumPaid = '0';
            foreach ($payments as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $amtRaw = $row['amount'] ?? null;
                if ($amtRaw === null || trim((string) $amtRaw) === '') {
                    continue;
                }
                $parsed = $opening->parseOptionalMoney($amtRaw);
                if ($parsed === null || bccomp($parsed, '0', 2) <= 0) {
                    $v->errors()->add("payments.{$i}.amount", 'Укажите сумму больше 0 или оставьте поле пустым.');

                    continue;
                }
                $accId = (int) ($row['organization_bank_account_id'] ?? 0);
                if ($accId <= 0) {
                    $v->errors()->add("payments.{$i}.organization_bank_account_id", 'Выберите счёт для оплаты.');

                    continue;
                }
                $acc = OrganizationBankAccount::query()->with('organization:id,branch_id')->find($accId);
                if ($acc === null || $acc->organization === null || (int) $acc->organization->branch_id !== $branchId) {
                    $v->errors()->add("payments.{$i}.organization_bank_account_id", 'Недопустимый счёт.');

                    continue;
                }
                $sumPaid = bcadd($sumPaid, $parsed, 2);
            }

            if (bccomp($sumPaid, $total, 2) > 0) {
                $v->errors()->add('payments', 'Сумма оплат ('.$sumPaid.') не может превышать итог чека ('.$total.' сом).');
            }

            $debt = bcsub($total, $sumPaid, 2);
            if (bccomp($debt, '0', 2) > 0) {
                $name = trim((string) $this->input('debtor_name'));
                $phone = trim((string) $this->input('debtor_phone'));
                if ($name === '') {
                    $v->errors()->add('debtor_name', 'Укажите имя должника (есть долг '.$debt.' сом).');
                }
                if ($phone === '') {
                    $v->errors()->add('debtor_phone', 'Укажите телефон должника.');
                }
            }
        });
    }
}
