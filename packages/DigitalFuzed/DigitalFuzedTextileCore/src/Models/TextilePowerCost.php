<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextilePowerCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'meter_reading_start' => 'decimal:2',
        'meter_reading_end' => 'decimal:2',
        'units_consumed' => 'decimal:2',
        'rate_per_unit' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
