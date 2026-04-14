<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->branch_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opening_by_account' => ['required', 'array'],
            'opening_by_account.*' => ['nullable', 'string', 'max:32'],
            'open_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
