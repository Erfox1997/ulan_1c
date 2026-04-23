<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Good extends Model
{
    protected $fillable = [
        'branch_id',
        'article_code',
        'name',
        'barcode',
        'category',
        'unit',
        'sale_price',
        'wholesale_price',
        'is_service',
        'min_sale_price',
        'oem',
        'factory_number',
        'min_stock',
        'tnved_code',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'is_service' => 'boolean',
            'min_sale_price' => 'decimal:2',
            'min_stock' => 'decimal:4',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openingBalance(): HasOne
    {
        return $this->hasOne(OpeningStockBalance::class);
    }

    public function retailSaleLines(): HasMany
    {
        return $this->hasMany(RetailSaleLine::class, 'good_id');
    }

    public function openingStockBalances(): HasMany
    {
        return $this->hasMany(OpeningStockBalance::class, 'good_id');
    }

    public function serviceOrderLines(): HasMany
    {
        return $this->hasMany(ServiceOrderLine::class, 'good_id');
    }

    public function legalEntitySaleLines(): HasMany
    {
        return $this->hasMany(LegalEntitySaleLine::class, 'good_id');
    }

    public function purchaseReceiptLines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class, 'good_id');
    }

    public function customerReturnLines(): HasMany
    {
        return $this->hasMany(CustomerReturnLine::class, 'good_id');
    }
}
