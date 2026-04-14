<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class)->orderBy('sort_order')->orderBy('name');
    }

    public function goods(): HasMany
    {
        return $this->hasMany(Good::class)->orderBy('article_code');
    }

    public function openingStockBalances(): HasMany
    {
        return $this->hasMany(OpeningStockBalance::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class)->orderBy('sort_order')->orderBy('name');
    }

    public function cashShifts(): HasMany
    {
        return $this->hasMany(CashShift::class)->orderByDesc('opened_at');
    }
}
