<?php

namespace Tests\Feature\Textile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use DigitalFuzed\TextileInventory\Models\TextileMovement;

class TextileInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_textile_movement(): void
    {
        $movement = TextileMovement::create([
            'movement_type' => 'receipt',
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'lot_reference' => 'LOT-100',
            'location_from' => 'supplier',
            'location_to' => 'warehouse',
            'quantity' => 120.5,
            'unit' => 'kg',
            'status' => 'posted',
            'notes' => 'Initial receipt',
        ]);

        $this->assertDatabaseHas('textile_movements', [
            'id' => $movement->id,
            'movement_type' => 'receipt',
            'lot_reference' => 'LOT-100',
        ]);
    }
}
