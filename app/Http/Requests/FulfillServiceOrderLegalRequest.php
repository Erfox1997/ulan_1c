<?php

namespace App\Http\Requests;

use App\Models\Counterparty;
use App\Models\LegalEntitySale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FulfillServiceOrderLegalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'counterparty_id' => [
                'required',
                'integer',
                Rule::exists('counterparties', 'id')->where(fn ($q) => $q
                    ->where('branch_id', $branchId)
                    ->where('kind', Counterparty::KIND_BUYER)),
            ],
            'document_date' => ['required', 'date'],
            'issue_esf' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! $this->boolean('issue_esf')) {
                return;
            }

            $cpId = (int) $this->input('counterparty_id');
            $cp = Counterparty::query()
                ->where('branch_id', (int) $this->user()->branch_id)
                ->whereKey($cpId)
                ->first();

            if ($cp === null) {
                return;
            }

            $buyerName = trim((string) ($cp->full_name ?: $cp->name));
            $buyerPin = preg_replace('/\D+/', '', (string) ($cp->inn ?? ''));

            $temp = new LegalEntitySale([
                'branch_id' => (int) $this->user()->branch_id,
                'buyer_name' => $buyerName,
                'buyer_pin' => $buyerPin,
                'counterparty_id' => $cp->id,
            ]);

            if (strlen($temp->resolvedBuyerPinForEsf()) < 10) {
                $v->errors()->add(
                    'counterparty_id',
                    'Для ЭСФ у выбранного контрагента должен быть ИНН в карточке (или укажите ИНН позже в карточке контрагента).'
                );
            }
        });
    }
}
