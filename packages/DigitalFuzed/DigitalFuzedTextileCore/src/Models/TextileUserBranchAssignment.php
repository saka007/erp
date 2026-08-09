<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TextileUserBranchAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'creator_id',
        'created_by',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'branch_id' => 'integer',
        'creator_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('created_by', $tenantId);
    }
}
