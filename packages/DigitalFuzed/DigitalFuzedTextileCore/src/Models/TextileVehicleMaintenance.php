<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileVehicleMaintenance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'maintenance_date' => 'date',
        'next_due_date' => 'date',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
