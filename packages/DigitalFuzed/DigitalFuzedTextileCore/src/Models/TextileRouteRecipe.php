<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Model;

class TextileRouteRecipe extends Model
{
    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'is_active' => 'boolean',
    ];
}
