<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuickGoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->branch_id !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:500'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:32'],
            'sale_price' => ['nullable', 'string'],
            'wholesale_price' => ['nullable', 'string'],
            'min_sale_price' => ['nullable', 'string'],
            'oem' => ['nullable', 'string', 'max:120'],
            'factory_number' => ['nullable', 'string', 'max:120'],
            'min_stock' => ['nullable', 'string'],
        ];
    }
}
