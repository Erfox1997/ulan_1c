<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchArticleCounter extends Model
{
    protected $fillable = [
        'branch_id',
        'next_num',
    ];

    protected function casts(): array
    {
        return [
            'next_num' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
