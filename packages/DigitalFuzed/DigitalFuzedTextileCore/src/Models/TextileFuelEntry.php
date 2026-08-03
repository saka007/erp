<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileFuelEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fuel_date' => 'date',
        'fuel_quantity_liters' => 'decimal:2',
        'fuel_rate_per_liter' => 'decimal:2',
        'fuel_total_cost' => 'decimal:2',
        'odometer_km' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
