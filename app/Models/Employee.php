<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'full_name',
        'position',
        'salary_fixed',
        'salary_percent_goods',
        'salary_percent_services',
    ];

    protected function casts(): array
    {
        return [
            'salary_fixed' => 'decimal:2',
            'salary_percent_goods' => 'decimal:2',
            'salary_percent_services' => 'decimal:2',
        ];
    }

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(EmployeePenalty::class);
    }
}
