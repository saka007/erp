<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileServiceSchedule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_active' => 'boolean',
    ];
}
