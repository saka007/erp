<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileDispatchVehicle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'capacity' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
