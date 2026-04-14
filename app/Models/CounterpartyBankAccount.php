<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterpartyBankAccount extends Model
{
    public const TYPE_BANK = 'bank';

    public const TYPE_CASH = 'cash';

    protected $attributes = [
        'account_type' => self::TYPE_BANK,
    ];

    protected $fillable = [
        'counterparty_id',
        'account_type',
        'account_number',
        'bank_name',
        'bik',
        'currency',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function isCash(): bool
    {
        return $this->account_type === self::TYPE_CASH;
    }

    /** Краткая подпись для списков. */
    public function summaryLabel(): string
    {
        if ($this->isCash()) {
            $suffix = $this->account_number ? ' · '.$this->account_number : '';

            return 'Наличные ('.$this->currency.')'.$suffix;
        }

        return trim(($this->bank_name ?? 'Банк').' · '.($this->account_number ?? '—'));
    }
}
