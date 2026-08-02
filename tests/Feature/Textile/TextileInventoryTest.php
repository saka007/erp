<?php

namespace Tests\Feature\Textile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use DigitalFuzed\TextileInventory\Services\TextileMovementService;

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

    public function test_it_can_record_a_textile_movement_through_the_service(): void
    {
        $service = new TextileMovementService();

        $movement = $service->createMovement([
            'movement_type' => 'issue',
            'reference_type' => 'production',
            'reference_id' => 7,
            'lot_reference' => 'LOT-200',
            'location_from' => 'warehouse',
            'location_to' => 'production',
            'quantity' => 42,
            'unit' => 'kg',
            'notes' => 'Issued to production',
        ]);

        $this->assertInstanceOf(TextileMovement::class, $movement);
        $this->assertEquals('posted', $movement->status);
        $this->assertDatabaseHas('textile_movements', [
            'id' => $movement->id,
            'movement_type' => 'issue',
            'lot_reference' => 'LOT-200',
        ]);
    }

    public function test_it_ignores_spoofed_ownership_fields(): void
    {
        $service = new TextileMovementService();

        $movement = $service->createMovement([
            'movement_type' => 'transfer',
            'reference_type' => 'internal',
            'reference_id' => 9,
            'lot_reference' => 'LOT-300',
            'location_from' => 'warehouse-a',
            'location_to' => 'warehouse-b',
            'quantity' => 10,
            'unit' => 'kg',
            'created_by' => 999,
            'creator_id' => 999,
        ]);

        $this->assertNull($movement->created_by);
        $this->assertNull($movement->creator_id);
        $this->assertDatabaseHas('textile_movements', [
            'id' => $movement->id,
            'created_by' => null,
            'creator_id' => null,
        ]);
    }
}
