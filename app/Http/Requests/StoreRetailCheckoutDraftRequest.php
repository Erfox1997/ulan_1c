<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRetailSaleCartLines;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRetailCheckoutDraftRequest extends FormRequest
{
    use ValidatesRetailSaleCartLines;

    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return $this->retailCartLinesRules($branchId);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateRetailCartLinesAfter($v);
        });
    }
}
