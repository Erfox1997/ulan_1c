<?php

namespace App\Http\Requests;

use App\Models\Counterparty;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankCashIncomeOtherRequest extends FormRequest
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

        if (($this->input('income_kind') ?? '') !== 'loan') {
            $this->merge(['counterparty_id' => null]);
        }
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'income_kind' => ['required', 'string', Rule::in(['plain', 'loan'])],
            'occurred_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'our_account_id' => [
                'required',
                'integer',
                Rule::exists('organization_bank_accounts', 'id')->where(
                    fn ($q) => $q->whereIn(
                        'organization_id',
                        Organization::query()->where('branch_id', $branchId)->select('id')
                    )
                ),
            ],
            'counterparty_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('income_kind') === 'loan'),
                Rule::exists('counterparties', 'id')->where(
                    fn ($q) => $q->where('branch_id', $branchId)->where('kind', Counterparty::KIND_OTHER)
                ),
            ],
            'expense_category' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
