<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSurplusLine extends Model
{
    protected $fillable = [
        'stock_surplus_id',
        'good_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function stockSurplus(): BelongsTo
    {
        return $this->belongsTo(StockSurplus::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
