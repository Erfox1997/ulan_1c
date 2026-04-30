<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailSalePayment extends Model
{
    protected $fillable = [
        'retail_sale_id',
        'organization_bank_account_id',
        'amount',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function retailSale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function organizationBankAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBankAccount::class);
    }
}
