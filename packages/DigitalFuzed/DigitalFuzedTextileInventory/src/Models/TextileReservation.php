<?php

namespace DigitalFuzed\TextileInventory\Models;

use Illuminate\Database\Eloquent\Model;

class TextileReservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reserved_quantity' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
