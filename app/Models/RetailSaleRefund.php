<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailSaleRefund extends Model
{
    protected $fillable = [
        'customer_return_id',
        'retail_sale_id',
        'organization_bank_account_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function retailSale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class);
    }

    public function organizationBankAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBankAccount::class);
    }
}
