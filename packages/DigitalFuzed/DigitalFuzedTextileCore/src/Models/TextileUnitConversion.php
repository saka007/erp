<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileUnitConversion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'factor' => 'decimal:6',
        'is_active' => 'boolean',
    ];
}
