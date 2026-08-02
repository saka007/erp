<?php

namespace DigitalFuzed\TextileInventory\Models;

use Illuminate\Database\Eloquent\Model;

class TextileLot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'received_quantity' => 'decimal:2',
        'available_quantity' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
