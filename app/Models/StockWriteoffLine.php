<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockWriteoffLine extends Model
{
    protected $fillable = [
        'stock_writeoff_id',
        'good_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function stockWriteoff(): BelongsTo
    {
        return $this->belongsTo(StockWriteoff::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
