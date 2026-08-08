<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Branch assignment for a vendor/customer party.
 *
 * Semantics:
 *  - A party with NO assignments rows  → visible in ALL branches (global).
 *  - A party with assignment rows      → visible ONLY in the assigned branches.
 */
class TextilePartyBranchAssignment extends Model
{
    public const PARTY_VENDOR = 'vendor';
    public const PARTY_CUSTOMER = 'customer';

    protected $guarded = [];

    protected $casts = [
        'party_id' => 'integer',
        'branch_id' => 'integer',
        'creator_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('created_by', $tenantId);
    }

    public function scopeForParty(Builder $query, string $partyType, int $partyId): Builder
    {
        return $query->where('party_type', $partyType)->where('party_id', $partyId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
