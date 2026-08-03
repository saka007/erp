<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextilePmSchedule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'next_due_date' => 'date',
        'last_completed_date' => 'date',
        'frequency_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
