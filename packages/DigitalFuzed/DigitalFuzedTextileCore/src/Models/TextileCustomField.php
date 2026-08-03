<?php

namespace DigitalFuzed\TextileCore\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TextileCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_key',
        'sub_module_key',
        'field_key',
        'label',
        'field_type',
        'options',
        'is_required',
        'sort_order',
        'help_text',
        'is_active',
        'created_by',
        'creator_id',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
