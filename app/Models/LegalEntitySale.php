<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntitySale extends Model
{
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'buyer_name',
        'buyer_pin',
        'counterparty_id',
        'document_date',
        'issue_esf',
        'payment_invoice_sent',
        'esf_submitted_at',
        'esf_exchange_code',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'issue_esf' => 'boolean',
            'payment_invoice_sent' => 'boolean',
            'esf_submitted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LegalEntitySaleLine::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('branch_id', auth()->user()->branch_id)
            ->firstOrFail();
    }

    /**
     * ИНН/ПИН для XML ЭСФ (contractorPin): поле документа, затем справочник контрагентов.
     */
    public function resolvedBuyerPinForEsf(): string
    {
        $direct = preg_replace('/\D+/', '', (string) ($this->buyer_pin ?? ''));
        if (strlen($direct) >= 10) {
            return $direct;
        }

        $branchId = (int) $this->branch_id;
        $buyerName = trim((string) $this->buyer_name);

        if ($buyerName !== '') {
            $byName = Counterparty::query()
                ->where('branch_id', $branchId)
                ->whereIn('kind', [Counterparty::KIND_BUYER, Counterparty::KIND_OTHER])
                ->get()
                ->first(function (Counterparty $c) use ($buyerName) {
                    $aliases = $c->legalSaleBuyerNameAliases();
                    if (in_array($buyerName, $aliases, true)) {
                        return true;
                    }
                    foreach ($aliases as $alias) {
                        if ($buyerName !== '' && mb_strtolower($buyerName) === mb_strtolower($alias)) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($byName !== null) {
                $inn = preg_replace('/\D+/', '', (string) ($byName->inn ?? ''));
                if (strlen($inn) >= 10) {
                    return $inn;
                }
            }
        }

        if ($this->counterparty_id !== null) {
            $cp = Counterparty::query()
                ->where('branch_id', $branchId)
                ->find($this->counterparty_id);
            if ($cp !== null) {
                $inn = preg_replace('/\D+/', '', (string) ($cp->inn ?? ''));
                if (strlen($inn) >= 10) {
                    return $inn;
                }
            }
        }

        return '';
    }

    /**
     * Контрагент-покупатель для подстановки счёта в ЭСФ (по связи или совпадению наименования).
     */
    public function resolvedCounterpartyForEsf(): ?Counterparty
    {
        $branchId = (int) $this->branch_id;

        if ($this->counterparty_id !== null) {
            $cp = Counterparty::query()
                ->where('branch_id', $branchId)
                ->find($this->counterparty_id);
            if ($cp !== null) {
                return $cp;
            }
        }

        $buyerName = trim((string) $this->buyer_name);
        if ($buyerName === '') {
            return null;
        }

        return Counterparty::query()
            ->where('branch_id', $branchId)
            ->whereIn('kind', [Counterparty::KIND_BUYER, Counterparty::KIND_OTHER])
            ->get()
            ->first(function (Counterparty $c) use ($buyerName) {
                $aliases = $c->legalSaleBuyerNameAliases();
                if (in_array($buyerName, $aliases, true)) {
                    return true;
                }
                foreach ($aliases as $alias) {
                    if ($buyerName !== '' && mb_strtolower($buyerName) === mb_strtolower($alias)) {
                        return true;
                    }
                }

                return false;
            });
    }

    /**
     * Номер банковского счёта покупателя для XML (contractorBankAccount) — счёт по умолчанию из карточки контрагента.
     */
    public function resolvedBuyerBankAccountNumberForEsf(): ?string
    {
        $cp = $this->resolvedCounterpartyForEsf();
        if ($cp === null) {
            return null;
        }

        $cp->loadMissing('bankAccounts');

        $accounts = $cp->bankAccounts->filter(fn (CounterpartyBankAccount $a) => ! $a->isCash());
        $acc = $accounts->firstWhere('is_default', true) ?? $accounts->first();
        if ($acc === null) {
            return null;
        }

        $num = trim((string) ($acc->account_number ?? ''));

        return $num !== '' ? $num : null;
    }
}
