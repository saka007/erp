<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileMaintenanceSparePartUsage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'usage_date' => 'date',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
