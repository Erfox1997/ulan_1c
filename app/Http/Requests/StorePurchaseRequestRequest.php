<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->branch_id;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.opening_stock_balance_id' => [
                'required',
                'integer',
                Rule::exists('opening_stock_balances', 'id')->where('branch_id', $branchId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Выберите хотя бы одну позицию.',
            'items.min' => 'Выберите хотя бы одну позицию.',
            'items.*.quantity.gt' => 'Количество к закупке должно быть больше нуля.',
        ];
    }
}
