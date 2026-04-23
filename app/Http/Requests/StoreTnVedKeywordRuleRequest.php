<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreTnVedKeywordRuleRequest extends FormRequest
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
        if ($this->has('keyword')) {
            $this->merge(['keyword' => mb_strtolower(trim((string) $this->input('keyword')))]);
        }
        if ($this->has('tnved_code')) {
            $this->merge(['tnved_code' => trim((string) $this->input('tnved_code'))]);
        }
    }

    /**
     * @return array<string, array<int, Unique|string>>
     */
    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'keyword' => [
                'required',
                'string',
                'min:1',
                'max:255',
                Rule::unique('tnved_keyword_rules', 'keyword')->where('branch_id', $branchId),
            ],
            'tnved_code' => ['required', 'string', 'max:32', 'regex:/^\d+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.unique' => 'Правило с таким ключевым словом уже есть в справочнике.',
            'tnved_code.regex' => 'ТНВЭД указывается цифрами.',
        ];
    }

    /**
     * @return array{keyword: string, tnved_code: string}
     */
    public function validatedPayload(): array
    {
        $v = $this->validated();

        return [
            'keyword' => (string) $v['keyword'],
            'tnved_code' => (string) $v['tnved_code'],
        ];
    }
}
