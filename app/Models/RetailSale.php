<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailSale extends Model
{
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'organization_bank_account_id',
        'document_date',
        'user_id',
        'total_amount',
        'debt_amount',
        'debtor_name',
        'debtor_phone',
        'debtor_comment',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'total_amount' => 'decimal:2',
            'debt_amount' => 'decimal:2',
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

    public function organizationBankAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBankAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RetailSaleLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RetailSalePayment::class);
    }

    public function customerReturns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class);
    }

    public function retailSaleRefunds(): HasMany
    {
        return $this->hasMany(RetailSaleRefund::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('branch_id', auth()->user()->branch_id)
            ->firstOrFail();
    }
}
