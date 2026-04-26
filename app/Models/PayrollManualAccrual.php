<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollManualAccrual extends Model
{
    protected $table = 'payroll_manual_accruals';

    protected $fillable = [
        'branch_id',
        'employee_id',
        'period_from',
        'period_to',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
