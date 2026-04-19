<?php

namespace App\Http\Requests;

use App\Models\CustomerReturnLine;
use App\Models\RetailSale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRetailSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->branch_id !== null;
    }

    public function rules(): array
    {
        $branchId = (int) $this->user()->branch_id;

        return [
            'document_date' => ['required', 'date'],
            'organization_bank_account_id' => [
                'required',
                'integer',
                Rule::exists('organization_bank_accounts', 'id')->where(function ($q) use ($branchId) {
                    $q->whereExists(function ($sub) use ($branchId) {
                        $sub->from('organizations')
                            ->whereColumn('organizations.id', 'organization_bank_accounts.organization_id')
                            ->where('organizations.branch_id', $branchId)
                            ->selectRaw('1');
                    });
                }),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.retail_sale_line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $sale = $this->route('retailSale');
            if ($sale === null || ! $sale instanceof RetailSale) {
                return;
            }

            $branchId = (int) $this->user()->branch_id;
            if ((int) $sale->branch_id !== $branchId) {
                $v->errors()->add('sale', 'Чужой документ.');

                return;
            }

            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                return;
            }

            $sale->loadMissing('lines');
            $byId = $sale->lines->keyBy('id');

            $alreadyReturned = CustomerReturnLine::query()
                ->whereHas('customerReturn', fn ($q) => $q->where('retail_sale_id', $sale->id))
                ->whereNotNull('source_retail_sale_line_id')
                ->selectRaw('source_retail_sale_line_id, SUM(quantity) as sq')
                ->groupBy('source_retail_sale_line_id')
                ->pluck('sq', 'source_retail_sale_line_id');

            foreach ($lines as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lineId = (int) ($row['retail_sale_line_id'] ?? 0);
                $saleLine = $byId->get($lineId);
                if ($saleLine === null) {
                    $v->errors()->add("lines.{$i}.retail_sale_line_id", 'Строка не из этого чека.');

                    continue;
                }

                $qtyRaw = $row['quantity'] ?? null;
                $qty = $this->parsePositiveQty($qtyRaw);
                if ($qty === null) {
                    $v->errors()->add("lines.{$i}.quantity", 'Укажите количество больше 0.');

                    continue;
                }

                $sold = (string) $saleLine->quantity;
                $prev = (string) ($alreadyReturned[$lineId] ?? '0');
                $availStr = bcsub($sold, $prev, 4);
                if (bccomp($qty, $availStr, 4) > 0) {
                    $v->errors()->add(
                        "lines.{$i}.quantity",
                        'Нельзя вернуть больше, чем было продано (осталось к возврату: '.str_replace('.', ',', rtrim(rtrim($availStr, '0'), '.')).').'
                    );
                }
            }
        });
    }

    private function parsePositiveQty(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = str_replace([' ', ','], ['', '.'], (string) $raw);
        if (! is_numeric($s)) {
            return null;
        }
        $normalized = bcmul($s, '1', 4);
        if (bccomp($normalized, '0', 4) <= 0) {
            return null;
        }

        return $normalized;
    }
}
