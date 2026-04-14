<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'short_name',
        'legal_form',
        'inn',
        'legal_address',
        'phone',
        'notes',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(OrganizationBankAccount::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Ограничение маршрута admin: только организации текущего филиала.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('branch_id', auth()->user()->branch_id)
            ->firstOrFail();
    }
}
