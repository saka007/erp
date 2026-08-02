<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileIdempotencyKey;

class TextileIdempotencyService
{
    public function findResourceId(string $idempotencyKey, string $resourceType): ?int
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        return TextileIdempotencyKey::query()
            ->where('created_by', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->where('resource_type', $resourceType)
            ->value('resource_id');
    }

    public function remember(string $idempotencyKey, string $resourceType, int $resourceId): void
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        TextileIdempotencyKey::updateOrCreate(
            [
                'created_by' => $tenantId,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ]
        );
    }
}
