<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationBankAccount extends Model
{
    public const TYPE_BANK = 'bank';

    public const TYPE_CASH = 'cash';

    protected $attributes = [
        'account_type' => self::TYPE_BANK,
    ];

    protected $fillable = [
        'organization_id',
        'account_type',
        'account_number',
        'bank_name',
        'bik',
        'currency',
        'opening_balance',
        'is_default',
        'sort_order',
    ];

    public function isCash(): bool
    {
        return $this->account_type === self::TYPE_CASH;
    }

    public function isBank(): bool
    {
        return $this->account_type !== self::TYPE_CASH;
    }

    /** Краткая подпись для списков (организации, отчёты). */
    public function summaryLabel(): string
    {
        if ($this->isCash()) {
            $suffix = $this->account_number ? ' · '.$this->account_number : '';

            return 'Наличные ('.$this->currency.')'.$suffix;
        }

        return trim(($this->bank_name ?? 'Банк').' · '.($this->account_number ?? '—'));
    }

    /** Подпись без номера счёта (розница, касса). */
    public function labelWithoutAccountNumber(): string
    {
        if ($this->isCash()) {
            return 'Наличные ('.$this->currency.')';
        }

        $name = trim((string) ($this->bank_name ?? ''));

        return $name !== '' ? $name : 'Банковский счёт';
    }

    /**
     * Для отчёта «Движение денег»: только наименование (банк) или «Наличные» с подписью кассы без кода валюты и без р/с.
     */
    public function movementReportLabel(): string
    {
        if ($this->isCash()) {
            $suffix = $this->account_number ? ' · '.$this->account_number : '';

            return 'Наличные'.$suffix;
        }

        $name = trim((string) ($this->bank_name ?? ''));

        return $name !== '' ? $name : 'Банковский счёт';
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}

