<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportOpeningBalancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('branch_id', $this->user()->branch_id)),
            ],
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл Excel.',
            'file.mimes' => 'Допустимые форматы: xlsx, xls, csv.',
        ];
    }
}
