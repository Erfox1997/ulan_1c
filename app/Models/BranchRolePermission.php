<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchRolePermission extends Model
{
    protected $fillable = [
        'branch_role_id',
        'route_pattern',
    ];

    public function branchRole(): BelongsTo
    {
        return $this->belongsTo(BranchRole::class);
    }
}
