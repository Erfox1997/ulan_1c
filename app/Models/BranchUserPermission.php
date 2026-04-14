<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchUserPermission extends Model
{
    protected $fillable = [
        'user_id',
        'route_pattern',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
