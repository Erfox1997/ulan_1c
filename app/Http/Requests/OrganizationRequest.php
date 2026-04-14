<?php

namespace App\Http\Requests;

use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'legal_form' => ['nullable', 'string', 'max:100'],
            'inn' => ['nullable', 'string', 'max:32'],
            'legal_address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'bank_accounts' => ['nullable', 'array', 'max:30'],
            'bank_accounts.*.id' => ['nullable', 'integer'],
            'bank_accounts.*.account_type' => ['nullable', 'string', 'in:bank,cash'],
            'bank_accounts.*.account_number' => ['nullable', 'string', 'max:64'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.bik' => ['nullable', 'string', 'max:32'],
            'bank_accounts.*.currency' => ['nullable', 'string', 'size:3'],
            'default_bank_index' => ['nullable', 'integer', 'min:0', 'max:29'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите наименование организации.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $rows = $this->input('bank_accounts', []);
            if (! is_array($rows)) {
                return;
            }

            $money = app(OpeningBalanceService::class);

            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $type = ($row['account_type'] ?? 'bank') === 'cash' ? 'cash' : 'bank';
                if ($type === 'cash') {
                    $rawOb = $row['opening_balance'] ?? '';
                    if ($rawOb !== '' && $rawOb !== null && $money->parseOptionalMoney($rawOb) === null) {
                        $v->errors()->add(
                            "bank_accounts.{$i}.opening_balance",
                            'Укажите начальный остаток числом (неотрицательное значение) или оставьте пустым.'
                        );
                    }

                    continue;
                }

                $accountNumber = trim((string) ($row['account_number'] ?? ''));
                $bankName = trim((string) ($row['bank_name'] ?? ''));
                if ($accountNumber === '' && $bankName === '') {
                    continue;
                }
                if ($accountNumber === '' || $bankName === '') {
                    $v->errors()->add(
                        "bank_accounts.{$i}.account_number",
                        'Для банковского счёта укажите номер счёта и наименование банка (или удалите строку).'
                    );
                }

                $rawOb = $row['opening_balance'] ?? '';
                if ($rawOb !== '' && $rawOb !== null && $money->parseOptionalMoney($rawOb) === null) {
                    $v->errors()->add(
                        "bank_accounts.{$i}.opening_balance",
                        'Укажите начальный остаток числом (неотрицательное значение) или оставьте пустым.'
                    );
                }
            }
        });
    }
}
