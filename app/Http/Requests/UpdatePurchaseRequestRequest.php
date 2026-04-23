<?php

namespace App\Http\Requests;

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->branch_id;
    }

    public function rules(): array
    {
        $purchaseRequest = $this->route('purchaseRequest');
        if (! $purchaseRequest instanceof PurchaseRequest) {
            return [];
        }

        return [
            'note' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => [
                'required',
                'integer',
                Rule::exists('purchase_request_lines', 'id')->where(
                    'purchase_request_id', $purchaseRequest->id
                ),
            ],
            'lines.*.quantity' => ['nullable', 'numeric'],
            'lines.*.remove' => ['nullable', 'in:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                return;
            }
            $kept = 0;
            foreach ($lines as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (! empty($row['remove'])) {
                    continue;
                }
                $q = $row['quantity'] ?? null;
                if ($q === null || $q === '' || (float) $q <= 0) {
                    $validator->errors()->add("lines.{$i}.quantity", 'Укажите количество к закупке больше нуля для позиции, которую оставляете.');
                } else {
                    $kept++;
                }
            }
            if ($kept < 1) {
                $validator->errors()->add('lines', 'Нужна хотя бы одна позиция. Чтобы убрать заявку целиком, используйте кнопку «Удалить заявку».');
            }
        });
    }

    public function messages(): array
    {
        return [
            'lines.*.id.exists' => 'Позиция заявки не найдена или устарела. Обновите страницу.',
        ];
    }
}
