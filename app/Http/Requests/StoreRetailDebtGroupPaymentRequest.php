<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRetailDebtGroupPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['integer', 'distinct'],
            'amount' => ['required', 'string', 'max:32'],
            'organization_bank_account_id' => [
                'required',
                'integer',
                Rule::exists('organization_bank_accounts', 'id')->where(function ($q) use ($branchId) {
                    $q->whereExists(function ($sub) use ($branchId) {
                        $sub->from('organizations')
                            ->whereColumn('organizations.id', 'organization_bank_accounts.organization_id')
                            ->where('organizations.branch_id', $branchId)
                            ->selectRaw('1');
                    });
                }),
            ],
            'limit' => ['nullable', 'integer'],
        ];
    }
}
