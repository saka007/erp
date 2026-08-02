<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileSequence;
use Illuminate\Support\Facades\DB;

class TextileNumberingService
{
    public function next(string $sequenceKey): string
    {
        return DB::transaction(function () use ($sequenceKey) {
            $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

            $sequence = TextileSequence::query()->lockForUpdate()->firstOrCreate(
                ['created_by' => $tenantId, 'sequence_key' => $sequenceKey],
                ['prefix' => strtoupper($sequenceKey), 'last_number' => 0]
            );

            $sequence->last_number = (int) $sequence->last_number + 1;
            $sequence->save();

            return sprintf('%s-%06d', $sequence->prefix, $sequence->last_number);
        });
    }
}
