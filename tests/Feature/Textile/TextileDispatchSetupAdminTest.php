<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;

class TextileDispatchSetupAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_dispatch_drivers_vehicles_and_routes_with_tenant_isolation(): void
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

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-drivers.store'), [
                'name' => 'Ramesh Driver',
                'driver_source' => 'vendor',
                'phone' => '9000000001',
                'license_number' => 'DL-001',
                'license_expiry_date' => now()->addYear()->toDateString(),
                'transport_vendor_id' => $transportVendor->id,
            ])
            ->assertSessionHasNoErrors();

        $driver = TextileDispatchDriver::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($driver);
        $this->assertSame('Ramesh Driver', $driver->name);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-vehicles.store'), [
                'vehicle_number' => 'GJ01-VH-7788',
                'vehicle_type' => 'truck',
                'capacity' => 1200,
                'capacity_unit' => 'kg',
                'ownership_type' => 'owned',
                'transport_vendor_id' => $transportVendor->id,
            ])
            ->assertSessionHasNoErrors();

        $vehicle = TextileDispatchVehicle::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($vehicle);
        $this->assertSame('GJ01-VH-7788', $vehicle->vehicle_number);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-routes.store'), [
                'route_name' => 'Surat to Ahmedabad',
                'route_code' => 'RTE-001',
                'origin_location' => 'Surat',
                'destination_location' => 'Ahmedabad',
                'distance_km' => 265,
                'transit_hours' => 6,
                'transport_vendor_id' => $transportVendor->id,
            ])
            ->assertSessionHasNoErrors();

        $route = TextileDispatchRoute::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($route);
        $this->assertSame('Surat to Ahmedabad', $route->route_name);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-drivers.update'), [
                'driver_id' => $driver->id,
                'name' => 'Ramesh Driver Updated',
                'driver_source' => 'vendor',
                'phone' => '9000000001',
                'license_number' => 'DL-001',
                'transport_vendor_id' => $transportVendor->id,
            ])
            ->assertSessionHasNoErrors();

        $driver->refresh();
        $this->assertSame('Ramesh Driver Updated', $driver->name);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-vehicles.update'), [
                'vehicle_id' => $vehicle->id,
                'vehicle_number' => 'GJ01-VH-7788',
                'vehicle_type' => 'truck',
                'capacity' => 1400,
                'capacity_unit' => 'kg',
                'ownership_type' => 'owned',
                'transport_vendor_id' => $transportVendor->id,
            ])
            ->assertSessionHasNoErrors();

        $vehicle->refresh();
        $this->assertSame('1400.00', (string) $vehicle->capacity);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-routes.update'), [
                'route_id' => $route->id,
                'route_name' => 'Surat to Vadodara',
                'route_code' => 'RTE-001',
                'origin_location' => 'Surat',
                'destination_location' => 'Vadodara',
                'distance_km' => 155,
                'transit_hours' => 4,
                'transport_vendor_id' => $transportVendor->id,
            ])
            ->assertSessionHasNoErrors();

        $route->refresh();
        $this->assertSame('Surat to Vadodara', $route->route_name);

        $this->actingAs($companyB)
            ->get(route('textile.dispatch-drivers.index'))
            ->assertOk()
            ->assertDontSee('Ramesh Driver Updated');

        $this->actingAs($companyB)
            ->get(route('textile.dispatch-vehicles.index'))
            ->assertOk()
            ->assertDontSee('GJ01-VH-7788');

        $this->actingAs($companyB)
            ->get(route('textile.dispatch-routes.index'))
            ->assertOk()
            ->assertDontSee('Surat to Vadodara');

        $this->actingAs($companyB)
            ->post(route('textile.dispatch-drivers.archive'), [
                'driver_id' => $driver->id,
            ])
            ->assertStatus(404);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-drivers.archive'), [
                'driver_id' => $driver->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyB)
            ->post(route('textile.dispatch-routes.archive'), [
                'route_id' => $route->id,
            ])
            ->assertStatus(404);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch-routes.archive'), [
                'route_id' => $route->id,
            ])
            ->assertSessionHasNoErrors();

        $driver->refresh();
        $this->assertFalse($driver->is_active);

        $route->refresh();
        $this->assertFalse($route->is_active);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Dispatch Setup Plan',
            'modules' => ['TextileCore'],
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
            'module' => 'TextileCore',
        ]);

        return $company;
    }
}
