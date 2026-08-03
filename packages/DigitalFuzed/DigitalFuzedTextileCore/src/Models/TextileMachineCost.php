<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileMachineCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'depreciation_cost' => 'decimal:2',
        'maintenance_cost' => 'decimal:2',
        'power_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'other_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
