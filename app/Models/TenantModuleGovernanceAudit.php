<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModuleGovernanceAudit extends Model
{
    protected $fillable = [
        'tenant_id',
        'action',
        'module_key',
        'old_payload',
        'new_payload',
        'changed_by',
        'change_reason',
        'changed_at',
    ];

    protected $casts = [
        'old_payload' => 'array',
        'new_payload' => 'array',
        'changed_at' => 'datetime',
    ];
}
