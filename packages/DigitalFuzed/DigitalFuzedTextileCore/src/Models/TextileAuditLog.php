<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileAuditLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
