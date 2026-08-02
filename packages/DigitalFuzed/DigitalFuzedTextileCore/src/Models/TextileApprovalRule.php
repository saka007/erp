<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileApprovalRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_quantity' => 'decimal:2',
        'max_quantity' => 'decimal:2',
        'required_approvals' => 'integer',
        'is_active' => 'boolean',
        'conditions' => 'array',
    ];
}
