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
        'is_service',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'is_service' => 'boolean',
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
}
