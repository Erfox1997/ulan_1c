<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LegalEntitySale extends Model
{
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'buyer_name',
        'buyer_pin',
        'counterparty_id',
        'document_date',
        'comment',
        'esf_queue_goods',
        'esf_queue_services',
        'payment_invoice_sent',
        'esf_submitted_goods_at',
        'esf_submitted_services_at',
        'esf_exchange_code',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'esf_queue_goods' => 'boolean',
            'esf_queue_services' => 'boolean',
            'payment_invoice_sent' => 'boolean',
            'esf_submitted_goods_at' => 'datetime',
            'esf_submitted_services_at' => 'datetime',
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

    /**
     * Для ЭСФ: в одном документе товары и услуги выгружаются отдельными файлами (по признаку номенклатуры is_service).
     *
     * @return array{has_goods: bool, has_services: bool, mixed: bool}
     */
    public function esfGoodsServicesLinesProfile(): array
    {
        $this->loadMissing('lines.good');

        $hasGoods = false;
        $hasServices = false;

        foreach ($this->lines as $line) {
            $g = $line->good;
            if ($g === null) {
                continue;
            }
            if ($g->is_service) {
                $hasServices = true;
            } else {
                $hasGoods = true;
            }
        }

        return [
            'has_goods' => $hasGoods,
            'has_services' => $hasServices,
            'mixed' => $hasGoods && $hasServices,
        ];
    }

    /**
     * @param  'goods'|'services'  $kind
     */
    public function esfSubmittedAtForKind(string $kind): ?Carbon
    {
        return $kind === 'goods' ? $this->esf_submitted_goods_at : $this->esf_submitted_services_at;
    }

    /**
     * @param  'goods'|'services'  $kind
     */
    public function esfQueueSetForKind(string $kind, bool $queued): void
    {
        if ($kind === 'goods') {
            $this->esf_queue_goods = $queued;
        } else {
            $this->esf_queue_services = $queued;
        }
    }

    /**
     * @param  'goods'|'services'  $kind
     */
    public function esfCanQueueKind(string $kind): bool
    {
        $p = $this->esfGoodsServicesLinesProfile();

        return $kind === 'goods' ? $p['has_goods'] : $p['has_services'];
    }

    /**
     * @param  'goods'|'services'  $kind
     */
    public function esfIsKindQueued(string $kind): bool
    {
        return $kind === 'goods' ? (bool) $this->esf_queue_goods : (bool) $this->esf_queue_services;
    }

    /**
     * Галочка «Нужна ЭСФ» в форме реализации: поставить в очередь все типы строк, которые есть в документе.
     */
    public function esfApplyQueueFromFormCheckbox(): void
    {
        $p = $this->esfGoodsServicesLinesProfile();
        if ($p['has_goods']) {
            $this->esf_queue_goods = true;
        }
        if ($p['has_services']) {
            $this->esf_queue_services = true;
        }
        if (! $p['has_goods'] && ! $p['has_services']) {
            $this->esf_queue_goods = true;
        }
        $this->save();
    }

    /**
     * После смены строк документа: убрать очередь по типу, которого больше нет в реализации.
     */
    public function esfSyncQueueFlagsToDocumentLines(): self
    {
        $p = $this->esfGoodsServicesLinesProfile();
        if (! $p['has_goods']) {
            $this->esf_queue_goods = false;
            $this->esf_submitted_goods_at = null;
        }
        if (! $p['has_services']) {
            $this->esf_queue_services = false;
            $this->esf_submitted_services_at = null;
        }

        return $this;
    }

    /**
     * Список «Реализации без очереди ЭСФ»: показывать, пока не поставлена в очередь и соответствующий тип в документе.
     */
    public function esfStatusLineForPrint(): string
    {
        $p = $this->esfGoodsServicesLinesProfile();
        $bits = [];
        if ($p['has_goods']) {
            if ($this->esf_submitted_goods_at) {
                $bits[] = 'товары: записано в ЭСФ';
            } elseif ($this->esf_queue_goods) {
                $bits[] = 'товары: в очереди на ЭСФ';
            }
        }
        if ($p['has_services']) {
            if ($this->esf_submitted_services_at) {
                $bits[] = 'услуги: записано в ЭСФ';
            } elseif ($this->esf_queue_services) {
                $bits[] = 'услуги: в очереди на ЭСФ';
            }
        }
        if (! $p['has_goods'] && ! $p['has_services'] && ($this->esf_queue_goods || $this->esf_queue_services)) {
            $bits[] = 'ЭСФ: в очереди';
        }
        if ($bits === []) {
            return 'не отмечено';
        }

        return implode('; ', $bits);
    }

    public function esfIsAvailableListCandidate(): bool
    {
        $p = $this->esfGoodsServicesLinesProfile();
        if (! $p['has_goods'] && ! $p['has_services']) {
            return true;
        }
        if ($p['has_goods'] && ! $this->esf_queue_goods && $this->esf_submitted_goods_at === null) {
            return true;
        }
        if ($p['has_services'] && ! $this->esf_queue_services && $this->esf_submitted_services_at === null) {
            return true;
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LegalEntitySale>  $sales
     * @return \Illuminate\Support\Collection<int, object{sale: LegalEntitySale, esf_lines: 'goods'|'services'}>
     */
    public static function collectEsfPendingRows(\Illuminate\Support\Collection $sales): \Illuminate\Support\Collection
    {
        $out = collect();
        foreach ($sales as $sale) {
            $p = $sale->esfGoodsServicesLinesProfile();
            if ($sale->esf_queue_goods && $p['has_goods'] && $sale->esf_submitted_goods_at === null) {
                $out->push((object) ['sale' => $sale, 'esf_lines' => 'goods']);
            }
            if ($sale->esf_queue_services && $p['has_services'] && $sale->esf_submitted_services_at === null) {
                $out->push((object) ['sale' => $sale, 'esf_lines' => 'services']);
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LegalEntitySale>  $sales
     * @return \Illuminate\Support\Collection<int, object{sale: LegalEntitySale, esf_lines: 'goods'|'services', submitted_at: Carbon}>
     */
    public static function collectEsfRecordedRows(\Illuminate\Support\Collection $sales): \Illuminate\Support\Collection
    {
        $out = collect();
        foreach ($sales as $sale) {
            $p = $sale->esfGoodsServicesLinesProfile();
            if ($sale->esf_submitted_goods_at && $p['has_goods']) {
                $out->push((object) [
                    'sale' => $sale,
                    'esf_lines' => 'goods',
                    'submitted_at' => $sale->esf_submitted_goods_at,
                ]);
            }
            if ($sale->esf_submitted_services_at && $p['has_services']) {
                $out->push((object) [
                    'sale' => $sale,
                    'esf_lines' => 'services',
                    'submitted_at' => $sale->esf_submitted_services_at,
                ]);
            }
        }

        return $out->sortByDesc(fn (object $r) => $r->submitted_at->getTimestamp())->values();
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
