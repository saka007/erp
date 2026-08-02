<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TextileReferenceMaster extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('master_type', $type);
    }

    public function scopeDomain(Builder $query, string $domain): Builder
    {
        return $query->where('master_domain', $domain);
    }
}
