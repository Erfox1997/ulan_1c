<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TnvedKeywordRule extends Model
{
    protected $table = 'tnved_keyword_rules';

    protected $fillable = [
        'branch_id',
        'keyword',
        'tnved_code',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
