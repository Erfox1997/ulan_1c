<?php

namespace App\Http\Requests;

use App\Models\Counterparty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CounterpartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in([
                Counterparty::KIND_BUYER,
                Counterparty::KIND_SUPPLIER,
                Counterparty::KIND_OTHER,
            ])],
            'name' => ['required', 'string', 'max:500'],
            'legal_form' => ['required', 'string', Rule::in([
                Counterparty::LEGAL_IP,
                Counterparty::LEGAL_OSOO,
                Counterparty::LEGAL_INDIVIDUAL,
                Counterparty::LEGAL_OTHER,
            ])],
            'inn' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:1000'],
            'opening_debt' => ['nullable', 'numeric'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $rows = $this->input('bank_accounts', []);
            if (! is_array($rows)) {
                return;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $type = ($row['account_type'] ?? 'bank') === 'cash' ? 'cash' : 'bank';
                if ($type === 'cash') {
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
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $name = trim((string) $this->validated('name'));

        return [
            'kind' => $this->validated('kind'),
            'name' => $name,
            'legal_form' => $this->validated('legal_form'),
            'full_name' => Counterparty::buildFullName($this->validated('legal_form'), $name),
            'inn' => $this->filled('inn') ? trim((string) $this->input('inn')) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'address' => $this->filled('address') ? trim((string) $this->input('address')) : null,
            ...$this->openingDebtsForKind($this->validated('kind')),
        ];
    }

    /**
     * @return array{opening_debt_as_buyer: string, opening_debt_as_supplier: string}
     */
    private function openingDebtsForKind(string $kind): array
    {
        $amount = $this->nullableDecimal('opening_debt');

        if ($kind === Counterparty::KIND_SUPPLIER) {
            return [
                'opening_debt_as_buyer' => '0.00',
                'opening_debt_as_supplier' => $amount,
            ];
        }

        return [
            'opening_debt_as_buyer' => $amount,
            'opening_debt_as_supplier' => '0.00',
        ];
    }

    private function nullableDecimal(string $key): string
    {
        if (! $this->filled($key)) {
            return '0.00';
        }

        return number_format((float) str_replace(',', '.', (string) $this->input($key)), 2, '.', '');
    }
}
