<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileLabourCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'labour_date' => 'date',
        'worker_count' => 'integer',
        'hours_worked' => 'decimal:2',
        'rate_per_hour' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
