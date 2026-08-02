<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileLocation;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileInventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_its_lots_and_movements_with_tenant_scope(): void
    {
        AddOn::create([
            'module' => 'TextileInventory',
            'name' => 'Textile Inventory',
            'package_name' => 'textile-inventory',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();
        $otherCompany = $this->company();

        TextileLot::create([
            'lot_reference' => 'OTHER-LOT-01',
            'received_quantity' => 50,
            'available_quantity' => 50,
            'status' => 'active',
            'created_by' => $otherCompany->id,
            'creator_id' => $otherCompany->id,
        ]);
        TextileMovement::create([
            'movement_type' => 'receipt',
            'lot_reference' => 'OTHER-LOT-01',
            'location_to' => 'other-warehouse',
            'quantity' => 50,
            'status' => 'posted',
            'created_by' => $otherCompany->id,
            'creator_id' => $otherCompany->id,
        ]);
        TextileLocation::create([
            'name' => 'other-warehouse',
            'code' => 'OTH-WH',
            'location_type' => 'warehouse',
            'created_by' => $otherCompany->id,
            'creator_id' => $otherCompany->id,
            'is_active' => true,
        ]);
        TextileReservation::create([
            'lot_reference' => 'OTHER-LOT-01',
            'reference_type' => 'sales_order',
            'reference_id' => 404,
            'reserved_quantity' => 10,
            'status' => 'reserved',
            'is_active' => true,
            'created_by' => $otherCompany->id,
            'creator_id' => $otherCompany->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.inventory.locations.store'), [
                'name' => 'warehouse-a',
                'code' => 'WH-A',
                'location_type' => 'warehouse',
            ])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.inventory.lots.store'), [
                'lot_reference' => 'LOT-001',
                'received_quantity' => '120',
                'available_quantity' => '120',
                'status' => 'active',
            ])
            ->assertRedirect();

        $lotId = TextileLot::where('created_by', $company->id)
            ->where('lot_reference', 'LOT-001')
            ->value('id');

        $this->actingAs($company)
            ->post(route('textile.inventory.lots.update'), [
                'lot_id' => $lotId,
                'status' => 'hold',
            ])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.inventory.movements.store'), [
                'movement_type' => 'issue',
                'lot_reference' => 'LOT-001',
                'location_from' => 'warehouse-a',
                'location_to' => 'loom-floor',
                'quantity' => '25',
                'unit' => 'mtr',
                'status' => 'posted',
            ])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.inventory.movements.store'), [
                'movement_type' => 'transfer',
                'lot_reference' => 'LOT-001',
                'location_from' => 'warehouse-a',
                'location_to' => 'warehouse-a',
                'quantity' => '5',
                'unit' => 'mtr',
                'status' => 'pending',
            ])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.inventory.reservations.store'), [
                'lot_reference' => 'LOT-001',
                'quantity' => '30',
                'reference_type' => 'sales_order',
                'reference_id' => 101,
            ])
            ->assertRedirect();

        $reservationId = TextileReservation::where('created_by', $company->id)
            ->where('lot_reference', 'LOT-001')
            ->value('id');

        $this->actingAs($company)
            ->post(route('textile.inventory.reservations.allocate'), [
                'reservation_id' => $reservationId,
                'allocation_reference_id' => 9001,
                'allocation_reference_type' => 'allocation',
            ])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.inventory.reservations.release'), [
                'reservation_id' => $reservationId,
            ])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.inventory.reservations.store'), [
                'lot_reference' => 'LOT-001',
                'quantity' => '10',
                'reference_type' => 'sales_order',
                'reference_id' => 102,
            ])
            ->assertRedirect();

        $releaseReservationId = TextileReservation::where('created_by', $company->id)
            ->where('lot_reference', 'LOT-001')
            ->where('reference_id', 102)
            ->value('id');

        $this->actingAs($company)
            ->post(route('textile.inventory.reservations.release'), [
                'reservation_id' => $releaseReservationId,
            ])
            ->assertRedirect();

        $locationId = TextileLocation::where('created_by', $company->id)
            ->where('name', 'warehouse-a')
            ->value('id');

        $this->actingAs($company)
            ->post(route('textile.inventory.locations.archive'), [
                'location_id' => $locationId,
            ])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.inventory.lots.archive'), [
                'lot_id' => $lotId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('textile_lots', [
            'lot_reference' => 'LOT-001',
            'created_by' => $company->id,
            'available_quantity' => 95.00,
            'status' => 'inactive',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('textile_movements', [
            'movement_type' => 'issue',
            'lot_reference' => 'LOT-001',
            'created_by' => $company->id,
        ]);

        $this->assertDatabaseHas('textile_locations', [
            'name' => 'warehouse-a',
            'created_by' => $company->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('textile_reservations', [
            'lot_reference' => 'LOT-001',
            'reference_type' => 'allocation',
            'reference_id' => 9001,
            'reserved_quantity' => 30.00,
            'created_by' => $company->id,
            'status' => 'released',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('textile_reservations', [
            'lot_reference' => 'LOT-001',
            'reference_type' => 'sales_order',
            'reference_id' => 102,
            'reserved_quantity' => 10.00,
            'created_by' => $company->id,
            'status' => 'released',
            'is_active' => false,
        ]);

        $this->actingAs($company)
            ->get(route('textile.inventory.index'))
            ->assertOk()
            ->assertSee('LOT-001')
            ->assertSee('warehouse-a')
            ->assertDontSee('OTHER-LOT-01')
            ->assertDontSee('other-warehouse')
            ->assertDontSee('404')
            ->assertDontSee('WH-A');

        $this->actingAs($company)
            ->get(route('textile.inventory.index', ['status' => 'pending', 'location' => 'warehouse-a']))
            ->assertOk()
            ->assertSee('transfer')
            ->assertDontSee('other-warehouse');

        $this->actingAs($company)
            ->get(route('textile.inventory.lots.show', $lotId))
            ->assertOk()
            ->assertSee('LOT-001')
            ->assertSee('allocation')
            ->assertDontSee('OTHER-LOT-01');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Inventory Plan',
            'modules' => ['TextileInventory'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate([
            'name' => 'company',
            'guard_name' => 'web',
        ], [
            'label' => 'Company',
            'created_by' => $company->id,
        ]);

        $company->assignRole($role);

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileInventory',
        ]);

        return $company;
    }
}
