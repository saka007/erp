<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModuleEntitlement extends Model
{
    protected $fillable = [
        'tenant_id',
        'module_key',
        'is_entitled',
        'requires_approval',
        'set_by',
        'set_at',
    ];

    protected $casts = [
        'is_entitled' => 'boolean',
        'requires_approval' => 'boolean',
        'set_at' => 'datetime',
    ];
}
