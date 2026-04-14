<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditLine extends Model
{
    protected $fillable = [
        'stock_audit_id',
        'good_id',
        'quantity_book',
        'unit_cost_snapshot',
        'quantity_counted',
    ];

    protected function casts(): array
    {
        return [
            'quantity_book' => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:4',
            'quantity_counted' => 'decimal:4',
        ];
    }

    public function stockAudit(): BelongsTo
    {
        return $this->belongsTo(StockAudit::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
