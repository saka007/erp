<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileDispatchRoute extends Model
{
    protected $guarded = [];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'transit_hours' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
