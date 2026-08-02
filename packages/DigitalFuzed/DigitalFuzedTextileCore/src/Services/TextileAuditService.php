<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileAuditLog;

class TextileAuditService
{
    public function record(string $eventType, array $payload = []): TextileAuditLog
    {
        $actorId = auth()->id();
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : $actorId;

        return TextileAuditLog::create([
            'event_type' => $eventType,
            'payload' => array_merge($payload, [
                'actor_id' => $actorId,
                'tenant_id' => $tenantId,
            ]),
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
    }
}
