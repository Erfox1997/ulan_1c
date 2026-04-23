<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceTnVedRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return route('admin.accounting.tn-ved-gked-codes', ['tab' => 'services']);
    }

    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('service_tnved_code')) {
            $this->merge(['service_tnved_code' => trim((string) $this->input('service_tnved_code'))]);
        }
        if ($this->has('service_esf_export_name')) {
            $t = trim((string) $this->input('service_esf_export_name'));
            $this->merge(['service_esf_export_name' => $t === '' ? null : $t]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_tnved_code' => [
                'required',
                'string',
                'max:32',
                'regex:/^\d+(\.\d+)*$/',
            ],
            'service_esf_export_name' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_tnved_code.regex' => 'ГКЭД: только цифры и точки как разряды (например 85.59.0).',
        ];
    }
}
