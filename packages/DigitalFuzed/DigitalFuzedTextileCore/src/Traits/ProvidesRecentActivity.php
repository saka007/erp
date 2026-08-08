<?php

namespace DigitalFuzed\TextileCore\Traits;

use App\Models\User;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;

trait ProvidesRecentActivity
{
    /** Latest tenant-scoped audit log entries with resolved actor names, for workspace info panels. */
    protected function recentActivity(int $limit = 10): array
    {
        $logs = TextileAuditLog::query()
            ->where('created_by', creatorId())
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'event_type', 'payload', 'created_at']);

        $actorIds = $logs->pluck('payload.actor_id')->filter()->unique();
        $actorNames = User::query()->whereIn('id', $actorIds)->pluck('name', 'id');

        return $logs
            ->map(function (TextileAuditLog $log) use ($actorNames) {
                $payload = $log->payload;

                return [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'document_type' => $payload['document_type'] ?? null,
                    'document_number' => $payload['document_number'] ?? null,
                    'action' => $payload['action'] ?? null,
                    'from' => $payload['from'] ?? null,
                    'to' => $payload['to'] ?? null,
                    'actor_name' => $actorNames->get($payload['actor_id'] ?? null) ?? 'System',
                    'created_at' => $log->created_at,
                ];
            })
            ->all();
    }
}
