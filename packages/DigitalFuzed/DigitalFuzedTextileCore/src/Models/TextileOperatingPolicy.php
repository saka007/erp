<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileOperatingPolicy extends Model
{
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
    ];
}
