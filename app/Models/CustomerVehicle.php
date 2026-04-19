<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerVehicle extends Model
{
    protected $fillable = [
        'branch_id',
        'counterparty_id',
        'vehicle_brand',
        'vin',
        'vehicle_year',
        'engine_volume',
        'plate_number',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_year' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'customer_vehicle_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $branchId = auth()->user()?->branch_id;
        if ($branchId === null) {
            abort(403);
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('branch_id', (int) $branchId)
            ->firstOrFail();
    }

    public function label(): string
    {
        $parts = array_filter([
            $this->vehicle_brand,
            $this->plate_number ? '№ '.$this->plate_number : null,
            $this->vin ? 'VIN '.$this->vin : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : 'Авто #'.$this->id;
    }
}
