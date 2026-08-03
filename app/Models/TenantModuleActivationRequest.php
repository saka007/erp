<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModuleActivationRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'module_key',
        'status',
        'request_note',
        'review_note',
        'requested_by',
        'reviewed_by',
        'requested_at',
        'reviewed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];
}
