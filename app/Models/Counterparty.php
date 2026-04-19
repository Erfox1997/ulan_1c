<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counterparty extends Model
{
    public const KIND_BUYER = 'buyer';

    public const KIND_SUPPLIER = 'supplier';

    public const KIND_OTHER = 'other';

    public const LEGAL_IP = 'ip';

    public const LEGAL_OSOO = 'osoo';

    public const LEGAL_INDIVIDUAL = 'individual';

    public const LEGAL_OTHER = 'other';

    protected $fillable = [
        'branch_id',
        'kind',
        'name',
        'legal_form',
        'full_name',
        'inn',
        'phone',
        'address',
        'opening_debt_as_buyer',
        'opening_debt_as_supplier',
    ];

    protected function casts(): array
    {
        return [
            'opening_debt_as_buyer' => 'decimal:2',
            'opening_debt_as_supplier' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CounterpartyBankAccount::class)->orderBy('sort_order')->orderBy('id');
    }

    public function customerVehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class)->orderBy('id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $this->where($field, $value)
            ->where('branch_id', auth()->user()->branch_id)
            ->firstOrFail();
    }

    public static function buildFullName(string $legalForm, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return match ($legalForm) {
            self::LEGAL_IP => 'ИП '.$name,
            self::LEGAL_OSOO => 'ОсОО «'.$name.'»',
            self::LEGAL_INDIVIDUAL, self::LEGAL_OTHER => $name,
            default => $name,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            self::KIND_BUYER => 'Покупатель',
            self::KIND_SUPPLIER => 'Поставщик',
            self::KIND_OTHER => 'Прочее',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function legalFormLabels(): array
    {
        return [
            self::LEGAL_IP => 'ИП',
            self::LEGAL_OSOO => 'ОсОО',
            self::LEGAL_INDIVIDUAL => 'Физ. лицо',
            self::LEGAL_OTHER => 'Прочее',
        ];
    }

    /**
     * Варианты строки «Покупатель» в реализации, чтобы сопоставить с legal_entity_sales.buyer_name.
     *
     * @return list<string>
     */
    public function legalSaleBuyerNameAliases(): array
    {
        $candidates = [
            trim((string) $this->full_name),
            self::buildFullName($this->legal_form, (string) $this->name),
            trim((string) $this->name),
        ];
        $out = [];
        foreach ($candidates as $s) {
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Варианты строки «Поставщик» в поступлении, чтобы сопоставить с purchase_receipts.supplier_name.
     *
     * @return list<string>
     */
    public function supplierNameAliases(): array
    {
        return $this->legalSaleBuyerNameAliases();
    }
}
