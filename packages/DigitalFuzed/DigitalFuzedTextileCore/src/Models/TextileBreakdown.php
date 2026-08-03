<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileBreakdown extends Model
{
    protected $guarded = [];

    protected $casts = [
        'breakdown_date' => 'date',
        'resolved_date' => 'date',
        'downtime_minutes' => 'integer',
        'is_active' => 'boolean',
    ];
}
