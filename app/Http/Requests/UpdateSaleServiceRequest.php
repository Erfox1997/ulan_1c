<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->branch_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:500'],
            'unit' => ['nullable', 'string', 'max:32'],
            'sale_price' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:120'],
        ];
    }
}
