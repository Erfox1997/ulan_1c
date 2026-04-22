<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrder extends Model
{
    public const STATUS_AWAITING_FULFILLMENT = 'awaiting_fulfillment';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    public const RECIPIENT_PHYSICAL = 'physical';

    public const RECIPIENT_LEGAL = 'legal';

    /** @var list<string> */
    public const RECIPIENT_KINDS = [
        self::RECIPIENT_PHYSICAL,
        self::RECIPIENT_LEGAL,
    ];

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_AWAITING_FULFILLMENT,
        self::STATUS_FULFILLED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'counterparty_id',
        'contact_name',
        'customer_vehicle_id',
        'mileage_km',
        'lead_master_employee_id',
        'deadline_date',
        'user_id',
        'status',
        'document_date',
        'total_amount',
        'organization_bank_account_id',
        'notes',
        'retail_sale_id',
        'legal_entity_sale_id',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'deadline_date' => 'date',
            'total_amount' => 'decimal:2',
            'mileage_km' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organizationBankAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationBankAccount::class);
    }

    public function retailSale(): BelongsTo
    {
        return $this->belongsTo(RetailSale::class);
    }

    public function legalEntitySale(): BelongsTo
    {
        return $this->belongsTo(LegalEntitySale::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ServiceOrderLine::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function customerVehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class, 'customer_vehicle_id');
    }

    public function leadMasterEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_master_employee_id');
    }

    /** Очередь «Мастер: Оформление заявок» (вкладка по умолчанию). */
    public function scopeAwaitingFulfillmentQueue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AWAITING_FULFILLMENT)
            ->whereNull('retail_sale_id')
            ->whereNull('legal_entity_sale_id');
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

    public function isAwaitingFulfillment(): bool
    {
        return $this->status === self::STATUS_AWAITING_FULFILLMENT
            && $this->retail_sale_id === null
            && $this->legal_entity_sale_id === null;
    }

    public function recipientKindLabel(): string
    {
        $cp = $this->relationLoaded('counterparty') ? $this->counterparty : null;
        if ($cp === null) {
            return '—';
        }

        return (string) ($cp->full_name ?: $cp->name);
    }

    /** Имя клиента / контактное лицо (как на печатной форме заказ-наряда). */
    public function clientDisplayLabel(): string
    {
        if ($this->contact_name !== null && trim((string) $this->contact_name) !== '') {
            return trim((string) $this->contact_name);
        }

        $cp = $this->relationLoaded('counterparty') ? $this->counterparty : null;
        if ($cp === null) {
            return '—';
        }

        $name = trim((string) $cp->name);
        $fullName = trim((string) $cp->full_name);

        return $name !== '' ? $name : ($fullName !== '' ? $fullName : '—');
    }
}
