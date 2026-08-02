<?php

namespace DigitalFuzed\TextileInventory\Models;

use Illuminate\Database\Eloquent\Model;

class TextileCycleCount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expected_quantity' => 'decimal:2',
        'counted_quantity' => 'decimal:2',
        'variance_quantity' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}