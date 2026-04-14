<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAudit extends Model
{
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'document_date',
        'note',
        'is_draft',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'is_draft' => 'boolean',
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

    public function lines(): HasMany
    {
        return $this->hasMany(StockAuditLine::class);
    }
}
