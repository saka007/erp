<?php

namespace DigitalFuzed\TextileInventory\Services;

use DigitalFuzed\TextileInventory\Models\TextileMovement;

class TextileMovementService
{
    public function createMovement(array $attributes): TextileMovement
    {
        $safeAttributes = [
            'movement_type' => $attributes['movement_type'] ?? null,
            'reference_type' => $attributes['reference_type'] ?? null,
            'reference_id' => $attributes['reference_id'] ?? null,
            'lot_reference' => $attributes['lot_reference'] ?? null,
            'location_from' => $attributes['location_from'] ?? null,
            'location_to' => $attributes['location_to'] ?? null,
            'quantity' => $attributes['quantity'] ?? 0,
            'unit' => $attributes['unit'] ?? null,
            'status' => $attributes['status'] ?? 'posted',
            'notes' => $attributes['notes'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ];

        $defaults = [
            'creator_id' => auth()->id(),
            'created_by' => (auth()->check() && function_exists('creatorId')) ? creatorId() : auth()->id(),
        ];

        $movement = TextileMovement::create(array_merge($safeAttributes, $defaults));

        return $movement;
    }
}
