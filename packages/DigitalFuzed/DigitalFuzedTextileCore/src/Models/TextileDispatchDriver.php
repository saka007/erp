<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileDispatchDriver extends Model
{
    protected $guarded = [];

    protected $casts = [
        'license_expiry_date' => 'date',
        'is_active' => 'boolean',
    ];
}
