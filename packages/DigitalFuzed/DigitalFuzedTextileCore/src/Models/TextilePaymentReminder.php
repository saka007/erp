<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextilePaymentReminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'due_date' => 'date',
        'reminded_at' => 'datetime',
    ];
}
