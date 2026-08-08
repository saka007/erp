<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TextileRoleCapability extends Model
{
    protected $guarded = [];

    protected $casts = [
        'capabilities' => 'array',
    ];

    /**
     * Get the role this capability override belongs to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('created_by', $tenantId);
    }

    public function scopeForRole(Builder $query, int $roleId): Builder
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Check if a specific capability is disabled (set to false) in this override.
     */
    public function isDisabled(string $capability): bool
    {
        return ($this->capabilities[$capability] ?? null) === false;
    }

    /**
     * Get all disabled capabilities as a flat list of keys.
     */
    public function disabledKeys(): array
    {
        return array_keys(array_filter($this->capabilities ?? [], fn ($v) => $v === false));
    }
}
