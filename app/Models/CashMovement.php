<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    public const KIND_INCOME_CLIENT = 'income_client';

    public const KIND_INCOME_OTHER = 'income_other';

    public const KIND_EXPENSE_SUPPLIER = 'expense_supplier';

    public const KIND_EXPENSE_OTHER = 'expense_other';

    public const KIND_TRANSFER = 'transfer';

    protected $fillable = [
        'branch_id',
        'kind',
        'occurred_on',
        'amount',
        'our_account_id',
        'from_account_id',
        'to_account_id',
        'counterparty_id',
        'expense_category',
        'comment',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function ourAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBankAccount::class, 'our_account_id');
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBankAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBankAccount::class, 'to_account_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
