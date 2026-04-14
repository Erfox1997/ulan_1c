<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashShift extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'business_date',
        'opened_at',
        'closed_at',
        'opening_cash',
        'opening_by_account',
        'closing_cash',
        'closing_by_account',
        'open_note',
        'close_note',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'opening_by_account' => 'array',
            'closing_cash' => 'decimal:2',
            'closing_by_account' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('branch_id', auth()->user()->branch_id)
            ->firstOrFail();
    }
}
