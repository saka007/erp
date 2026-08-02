<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileAuditLog;

class TextileAuditService
{
    public function record(string $eventType, array $payload = []): TextileAuditLog
    {
        return TextileAuditLog::create([
            'event_type' => $eventType,
            'payload' => $payload,
            'creator_id' => auth()->id(),
            'created_by' => auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id(),
        ]);
    }
}
