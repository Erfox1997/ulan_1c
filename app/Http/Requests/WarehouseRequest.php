<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;
        $warehouseId = $this->route('warehouse')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('warehouses', 'code')
                    ->where('branch_id', $branchId)
                    ->ignore($warehouseId),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите наименование склада.',
            'code.unique' => 'Код склада уже занят в этом филиале.',
        ];
    }
}
