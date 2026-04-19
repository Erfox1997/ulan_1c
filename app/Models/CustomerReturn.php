<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerReturn extends Model
{
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'retail_sale_id',
        'buyer_name',
        'document_date',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
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
        return $this->hasMany(CustomerReturnLine::class);
    }

    public function retailSale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class);
    }

    public function retailSaleRefund(): HasOne
    {
        return $this->hasOne(RetailSaleRefund::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('branch_id', auth()->user()->branch_id)
            ->firstOrFail();
    }
}
