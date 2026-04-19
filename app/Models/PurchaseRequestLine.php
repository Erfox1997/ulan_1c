<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestLine extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'good_id',
        'warehouse_id',
        'opening_stock_balance_id',
        'quantity_requested',
        'quantity_snapshot',
        'min_stock_snapshot',
        'oem_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:4',
            'quantity_snapshot' => 'decimal:4',
            'min_stock_snapshot' => 'decimal:4',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function openingStockBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningStockBalance::class);
    }
}
