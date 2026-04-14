<?php

namespace App\Http\Requests;

use App\Models\OrganizationBankAccount;
use Illuminate\Foundation\Http\FormRequest;

class FulfillServiceOrderRetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'organization_bank_account_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($branchId): void {
                    $acc = OrganizationBankAccount::query()->with('organization:id,branch_id')->find((int) $value);
                    if ($acc === null || $acc->organization === null || (int) $acc->organization->branch_id !== $branchId) {
                        $fail('Выберите счёт или кассу из справочника организаций филиала.');
                    }
                },
            ],
            'document_date' => ['required', 'date'],
        ];
    }
}
