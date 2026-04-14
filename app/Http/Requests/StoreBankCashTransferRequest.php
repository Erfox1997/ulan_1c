<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBankCashTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $a = $this->input('amount');
        if (is_string($a)) {
            $n = str_replace(["\u{00a0}", ' '], '', $a);
            $n = str_replace(',', '.', $n);
            $this->merge(['amount' => $n]);
        }
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;
        $acctRule = [
            'required',
            'integer',
            Rule::exists('organization_bank_accounts', 'id')->where(
                fn ($q) => $q->whereIn(
                    'organization_id',
                    Organization::query()->where('branch_id', $branchId)->select('id')
                )
            ),
        ];

        return [
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'from_account_id' => $acctRule,
            'to_account_id' => $acctRule,
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $from = (int) $this->input('from_account_id', 0);
            $to = (int) $this->input('to_account_id', 0);
            if ($from !== 0 && $to !== 0 && $from === $to) {
                $v->errors()->add('to_account_id', 'Счёт зачисления должен отличаться от счёта списания.');
            }
        });
    }
}
