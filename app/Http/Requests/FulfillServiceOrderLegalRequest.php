<?php

namespace App\Http\Requests;

use App\Models\Counterparty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FulfillServiceOrderLegalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'counterparty_id' => [
                'required',
                'integer',
                Rule::exists('counterparties', 'id')->where(fn ($q) => $q
                    ->where('branch_id', $branchId)
                    ->where('kind', Counterparty::KIND_BUYER)),
            ],
            'document_date' => ['required', 'date'],
        ];
    }
}
