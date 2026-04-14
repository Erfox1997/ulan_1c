<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalEntitySaleLine extends Model
{
    protected $fillable = [
        'legal_entity_sale_id',
        'good_id',
        'article_code',
        'name',
        'unit',
        'quantity',
        'unit_price',
        'line_sum',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_sum' => 'decimal:2',
        ];
    }

    public function legalEntitySale(): BelongsTo
    {
        return $this->belongsTo(LegalEntitySale::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
