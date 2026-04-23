<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyUniversalTnVedRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return route('admin.accounting.tn-ved-gked-codes', ['tab' => 'goods']);
    }

    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('universal_tnved_code')) {
            $this->merge(['universal_tnved_code' => trim((string) $this->input('universal_tnved_code'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'universal_tnved_code' => ['required', 'string', 'max:32', 'regex:/^\d+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'universal_tnved_code.regex' => 'ТНВЭД — только цифры.',
        ];
    }
}
