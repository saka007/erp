<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileFreightCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'freight_date' => 'date',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
