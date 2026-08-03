<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use DigitalFuzed\TextileCore\Models\TextileFuelEntry;
use DigitalFuzed\TextileCore\Models\TextileFreightCost;
use DigitalFuzed\TextileCore\Models\TextileVehicleMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;

class TextileTransportAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_log_fuel_freight_and_maintenance_with_tenant_isolation(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $companyA = $this->company();
        $companyB = $this->company();

        $transportVendor = Vendor::create([
            'company_name' => 'Metro Transport Co',
            'contact_person_name' => 'Manish',
            'supplier_type' => 'transport',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $vehicle = TextileDispatchVehicle::create([
            'vehicle_number' => 'GJ01-VH-7788',
            'vehicle_type' => 'truck',
            'capacity' => 1200,
            'capacity_unit' => 'kg',
            'ownership_type' => 'owned',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $driver = TextileDispatchDriver::create([
            'name' => 'Ramesh Driver',
            'driver_source' => 'own',
            'phone' => '9000000001',
            'license_number' => 'DL-001',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $route = TextileDispatchRoute::create([
            'route_name' => 'Surat to Ahmedabad',
            'route_code' => 'RTE-001',
            'origin_location' => 'Surat',
            'destination_location' => 'Ahmedabad',
            'distance_km' => 265,
            'transit_hours' => 6,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Fuel entry
        $this->actingAs($companyA)
            ->post(route('textile.transport.fuel-entries.store'), [
                'entry_code' => 'FUEL-001',
                'fuel_date' => now()->toDateString(),
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'route_id' => $route->id,
                'fuel_quantity_liters' => 50,
                'fuel_rate_per_liter' => 90,
                'odometer_km' => 45000,
                'fuel_type' => 'diesel',
                'notes' => 'Tank fill before trip',
            ])
            ->assertSessionHasNoErrors();

        $fuel = TextileFuelEntry::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($fuel);
        $this->assertSame('4500.00', (string) $fuel->fuel_total_cost);
        $this->assertSame($vehicle->vehicle_number, $fuel->vehicle_name);
        $this->assertSame('Ramesh Driver', $fuel->driver_name);
        $this->assertSame('Surat to Ahmedabad', $fuel->route_name);

        // Freight cost
        $this->actingAs($companyA)
            ->post(route('textile.transport.freight-costs.store'), [
                'cost_code' => 'FRT-001',
                'freight_date' => now()->toDateString(),
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'route_id' => $route->id,
                'transport_vendor_id' => $transportVendor->id,
                'freight_type' => 'per_trip',
                'amount' => 8500,
                'notes' => 'Surat to Ahmedabad trip',
            ])
            ->assertSessionHasNoErrors();

        $freight = TextileFreightCost::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($freight);
        $this->assertSame('8500.00', (string) $freight->amount);
        $this->assertSame('Metro Transport Co', $freight->transport_vendor_name);

        // Vehicle maintenance
        $this->actingAs($companyA)
            ->post(route('textile.transport.vehicle-maintenances.store'), [
                'maintenance_code' => 'MNT-001',
                'maintenance_date' => now()->toDateString(),
                'next_due_date' => now()->addMonth()->toDateString(),
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => 'oil_change',
                'description' => 'Engine oil change',
                'cost' => 3500,
                'service_provider' => 'Auto Care Garage',
            ])
            ->assertSessionHasNoErrors();

        $maintenance = TextileVehicleMaintenance::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($maintenance);
        $this->assertSame('3500.00', (string) $maintenance->cost);
        $this->assertSame($vehicle->vehicle_number, $maintenance->vehicle_name);

        // Tenant isolation: companyB sees nothing
        $this->actingAs($companyB)
            ->get(route('textile.transport.index'))
            ->assertOk()
            ->assertDontSee('FUEL-001')
            ->assertDontSee('FRT-001')
            ->assertDontSee('MNT-001');

        $this->assertSame(
            1,
            TextileFuelEntry::where('created_by', $companyA->id)->count()
        );
        $this->assertSame(
            0,
            TextileFuelEntry::where('created_by', $companyB->id)->count()
        );
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Transport Plan',
            'modules' => ['TextileCore'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web'], ['label' => 'Company', 'created_by' => $company->id]);
        $company->assignRole($role);

        UserActiveModule::create(['user_id' => $company->id, 'module' => 'TextileCore']);

        return $company;
    }
}
