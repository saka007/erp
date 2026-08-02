<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileCommercialSourceMap;
use RuntimeException;

class TextileCommercialBoundaryService
{
    public function registerCanonical(string $sourceType, int $sourceId, string $canonicalType, int $canonicalId): TextileCommercialSourceMap
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : auth()->id();

        $existing = TextileCommercialSourceMap::query()
            ->where('created_by', $tenantId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing !== null && ((string) $existing->canonical_type !== $canonicalType || (int) $existing->canonical_id !== $canonicalId)) {
            throw new RuntimeException('Source is already mapped to a different canonical document.');
        }

        return TextileCommercialSourceMap::updateOrCreate(
            [
                'created_by' => $tenantId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'canonical_type' => $canonicalType,
                'canonical_id' => $canonicalId,
            ]
        );
    }

    public function resolveCanonical(string $sourceType, int $sourceId): ?TextileCommercialSourceMap
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : auth()->id();

        return TextileCommercialSourceMap::query()
            ->where('created_by', $tenantId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }
}
