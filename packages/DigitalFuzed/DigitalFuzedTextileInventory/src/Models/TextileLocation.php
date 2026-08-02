<?php

namespace DigitalFuzed\TextileInventory\Models;

use Illuminate\Database\Eloquent\Model;

class TextileLocation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
