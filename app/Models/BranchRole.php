<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchRole extends Model
{
    /**
     * Ограничение привязки маршрута филиалом текущего пользователя.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $this->where('branch_id', auth()->user()->branch_id)
            ->where($field, $value)
            ->firstOrFail();
    }

    protected $fillable = [
        'branch_id',
        'name',
        'is_full_access',
    ];

    protected function casts(): array
    {
        return [
            'is_full_access' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(BranchRolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_role_id');
    }
}
