<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use DigitalFuzed\TextileCore\Models\TextileOperatingPolicy;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;

class TextileDispatchAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_dispatch_planning_and_tracking_with_tenant_isolation(): void
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

        $this->seedDispatchMasters($companyA->id);

        $transportVendor = Vendor::create([
            'company_name' => 'Metro Transport Co',
            'contact_person_name' => 'Manish',
            'supplier_type' => 'transport',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $driverA = TextileDispatchDriver::create([
            'name' => 'Ramesh Driver',
            'driver_source' => 'vendor',
            'phone' => '9000000001',
            'license_number' => 'DL-001',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $driverB = TextileDispatchDriver::create([
            'name' => 'Suresh Driver',
            'driver_source' => 'vendor',
            'phone' => '9000000002',
            'license_number' => 'DL-002',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $vehicleA = TextileDispatchVehicle::create([
            'vehicle_number' => 'GJ01-VH-7788',
            'vehicle_type' => 'truck',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $vehicleB = TextileDispatchVehicle::create([
            'vehicle_number' => 'MH12-VH-9988',
            'vehicle_type' => 'container',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $routeA = TextileDispatchRoute::create([
            'route_name' => 'Surat to Ahmedabad',
            'origin_location' => 'Surat',
            'destination_location' => 'Ahmedabad',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $challanA = TextileWorkflowDocument::create([
            'document_type' => 'challan',
            'document_number' => 'CHL-D-001',
            'party_name' => 'Metro Textiles',
            'lot_reference' => 'LOT-D-1',
            'quantity' => 140,
            'unit' => 'mtr',
            'status' => 'released',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        TextileWorkflowDocument::create([
            'document_type' => 'challan',
            'document_number' => 'CHL-D-002',
            'party_name' => 'Other Buyer',
            'lot_reference' => 'LOT-D-2',
            'quantity' => 80,
            'unit' => 'mtr',
            'status' => 'released',
            'creator_id' => $companyB->id,
            'created_by' => $companyB->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.store'), [
                'source_type' => 'challan',
                'source_id' => $challanA->id,
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'dispatch_mode' => 'truck',
                'truck_number' => 'GJ01-TR-5555',
                'driver_id' => $driverA->id,
                'vehicle_id' => $vehicleA->id,
                'route_id' => $routeA->id,
                'transport_vendor_id' => $transportVendor->id,
                'lr_number' => 'LR-1001',
                'eway_bill_number' => 'EWB-9001',
                'freight_amount' => 8500,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.store'), [
                'source_type' => 'challan',
                'source_id' => $challanA->id,
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'dispatch_mode' => 'container',
                'container_number' => 'CONT-4455',
                'driver_id' => $driverB->id,
                'vehicle_id' => $vehicleB->id,
                'route_id' => $routeA->id,
                'transport_vendor_id' => $transportVendor->id,
                'lr_number' => 'LR-1002',
                'eway_bill_number' => 'EWB-9002',
                'freight_amount' => 9200,
            ])
            ->assertSessionHasNoErrors();

        $truckPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'dispatch_plan')
            ->whereJsonContains('metadata->dispatch_mode', 'truck')
            ->latest('id')
            ->first();

        $this->assertNotNull($truckPlan);
        $this->assertSame('draft', $truckPlan->status);
        $this->assertSame('GJ01-TR-5555', $truckPlan->metadata['truck_number'] ?? null);
        $this->assertSame($routeA->id, $truckPlan->metadata['route_id'] ?? null);
        $this->assertSame('Surat to Ahmedabad', $truckPlan->metadata['route_name'] ?? null);
        $this->assertSame($transportVendor->id, $truckPlan->metadata['transport_vendor_id'] ?? null);
        $this->assertSame('Metro Transport Co', $truckPlan->metadata['transport_vendor_name'] ?? null);
        $this->assertSame('LR-1001', $truckPlan->metadata['lr_number'] ?? null);
        $this->assertSame('EWB-9001', $truckPlan->metadata['eway_bill_number'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.approve'), [
                'dispatch_plan_id' => $truckPlan->id,
            ])
            ->assertSessionHasNoErrors();

        $truckPlan->refresh();
        $this->assertSame('approved', $truckPlan->status);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.trackings.store'), [
                'dispatch_plan_id' => $truckPlan->id,
                'source_action' => 'tracking_update',
                'tracking_status' => 'in_transit',
                'current_location' => 'Surat Hub',
                'vehicle_id' => $vehicleA->id,
                'driver_id' => $driverA->id,
                'route_id' => $routeA->id,
                'transport_vendor_id' => $transportVendor->id,
                'lr_number' => 'LR-1001',
                'eway_bill_number' => 'EWB-9001',
            ])
            ->assertSessionHasNoErrors();

        $tracking = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'dispatch_tracking')
            ->latest('id')
            ->first();

        $this->assertNotNull($tracking);
        $this->assertSame('draft', $tracking->status);
        $this->assertSame('in_transit', $tracking->metadata['tracking_status'] ?? null);
        $this->assertSame('Surat Hub', $tracking->metadata['current_location'] ?? null);
        $this->assertSame($routeA->id, $tracking->metadata['route_id'] ?? null);
        $this->assertSame('Surat to Ahmedabad', $tracking->metadata['route_name'] ?? null);
        $this->assertSame($transportVendor->id, $tracking->metadata['transport_vendor_id'] ?? null);
        $this->assertSame('Metro Transport Co', $tracking->metadata['transport_vendor_name'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.trackings.finalize'), [
                'tracking_id' => $tracking->id,
            ])
            ->assertSessionHasNoErrors();

        $tracking->refresh();
        $this->assertSame('approved', $tracking->status);

        $this->actingAs($companyB)
            ->get(route('textile.dispatch.index'))
            ->assertOk()
            ->assertDontSee('LOT-D-1')
            ->assertDontSee('Metro Textiles')
            ->assertDontSee('LR-1001');

        $this->actingAs($companyA)
            ->get(route('textile.dispatch.index'))
            ->assertOk()
            ->assertSee('LOT-D-1')
            ->assertSee('Metro Textiles')
            ->assertSee('LR-1001')
            ->assertDontSee('LOT-D-2');

        $this->actingAs($companyB)
            ->post(route('textile.dispatch.trackings.finalize'), [
                'tracking_id' => $tracking->id,
            ])
            ->assertSessionHasErrors('tracking_id');
    }

    public function test_company_can_create_dispatch_plans_from_job_work_outward_and_yarn_dispatch_sources(): void
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

        $this->seedDispatchMasters($companyA->id);

        // Tenant A uses vendor sizing (no in-house sizing) so the yarn dispatch
        // source is available while manufacturing stays enabled.
        TextileOperatingPolicy::create([
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'operating_model' => TextileOperatingPolicyService::MODEL_FULL_PACKAGE,
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'settings' => [
                TextileOperatingPolicyService::SETTING_HAS_SIZING => false,
            ],
        ]);

        $driver = TextileDispatchDriver::create([
            'name' => 'Ramesh Driver',
            'driver_source' => 'vendor',
            'phone' => '9000000001',
            'license_number' => 'DL-001',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $vehicle = TextileDispatchVehicle::create([
            'vehicle_number' => 'GJ01-VH-7788',
            'vehicle_type' => 'truck',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $route = TextileDispatchRoute::create([
            'route_name' => 'Surat to Ahmedabad',
            'origin_location' => 'Surat',
            'destination_location' => 'Ahmedabad',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $jobWorkOutward = TextileWorkflowDocument::create([
            'document_type' => 'job_work_outward',
            'document_number' => 'JW-OUT-001',
            'party_name' => 'Prem Processing House',
            'lot_reference' => 'LOT-JW-1',
            'quantity' => 220,
            'unit' => 'kg',
            'status' => 'released',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $draftJobWorkOutward = TextileWorkflowDocument::create([
            'document_type' => 'job_work_outward',
            'document_number' => 'JW-OUT-002',
            'party_name' => 'Draft Processing House',
            'lot_reference' => 'LOT-JW-2',
            'quantity' => 90,
            'unit' => 'kg',
            'status' => 'draft',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $yarnDispatch = TextileWorkflowDocument::create([
            'document_type' => 'warp_plan',
            'document_number' => 'WP-YD-001',
            'party_name' => 'Surya Sizing Works',
            'lot_reference' => 'LOT-YD-1',
            'quantity' => 500,
            'unit' => 'kg',
            'source_action' => 'yarn_dispatch',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $inHouseWarpPlan = TextileWorkflowDocument::create([
            'document_type' => 'warp_plan',
            'document_number' => 'WP-IN-001',
            'party_name' => 'In-House Warping',
            'lot_reference' => 'LOT-IN-1',
            'quantity' => 300,
            'unit' => 'kg',
            'source_action' => 'warp_plan',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.store'), [
                'source_type' => 'job_work_outward',
                'source_id' => $jobWorkOutward->id,
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'dispatch_mode' => 'truck',
                'truck_number' => 'GJ01-TR-5555',
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'route_id' => $route->id,
                'lr_number' => 'LR-1001',
                'eway_bill_number' => 'EWB-9001',
                'freight_amount' => 8500,
            ])
            ->assertSessionHasNoErrors();

        $jobWorkPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'dispatch_plan')
            ->whereJsonContains('metadata->source_type', 'job_work_outward')
            ->latest('id')
            ->first();
        $this->assertNotNull($jobWorkPlan);
        $this->assertSame('draft', $jobWorkPlan->status);
        $this->assertSame('Prem Processing House', $jobWorkPlan->party_name);
        $this->assertSame('LOT-JW-1', $jobWorkPlan->lot_reference);
        $this->assertSame('job_work_outward', $jobWorkPlan->metadata['source_type'] ?? null);
        $this->assertSame($jobWorkOutward->id, $jobWorkPlan->metadata['source_document_id'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.store'), [
                'source_type' => 'yarn_dispatch',
                'source_id' => $yarnDispatch->id,
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'dispatch_mode' => 'truck',
                'truck_number' => 'GJ01-TR-5555',
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'route_id' => $route->id,
                'lr_number' => 'LR-1002',
                'eway_bill_number' => 'EWB-9002',
                'freight_amount' => 9200,
            ])
            ->assertSessionHasNoErrors();

        $yarnPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'dispatch_plan')
            ->whereJsonContains('metadata->source_type', 'yarn_dispatch')
            ->latest('id')
            ->first();
        $this->assertNotNull($yarnPlan);
        $this->assertSame('Surya Sizing Works', $yarnPlan->party_name);
        $this->assertSame('LOT-YD-1', $yarnPlan->lot_reference);
        $this->assertSame('yarn_dispatch', $yarnPlan->metadata['source_type'] ?? null);
        $this->assertSame($yarnDispatch->id, $yarnPlan->metadata['source_document_id'] ?? null);

        // Draft job-work outward must be rejected as a dispatch source.
        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.store'), [
                'source_type' => 'job_work_outward',
                'source_id' => $draftJobWorkOutward->id,
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'dispatch_mode' => 'truck',
                'truck_number' => 'GJ01-TR-5555',
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'route_id' => $route->id,
            ])
            ->assertSessionHasErrors('source_id');

        // In-house warp plan must not be accepted as a yarn dispatch source.
        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.store'), [
                'source_type' => 'yarn_dispatch',
                'source_id' => $inHouseWarpPlan->id,
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'dispatch_mode' => 'truck',
                'truck_number' => 'GJ01-TR-5555',
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'route_id' => $route->id,
            ])
            ->assertSessionHasErrors('source_id');

        // Tenant isolation: company B cannot use company A's job-work outward.
        $this->actingAs($companyB)
            ->post(route('textile.dispatch.plans.store'), [
                'source_type' => 'job_work_outward',
                'source_id' => $jobWorkOutward->id,
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'dispatch_mode' => 'truck',
            ])
            ->assertSessionHasErrors('source_id');

        $this->actingAs($companyB)
            ->get(route('textile.dispatch.index'))
            ->assertOk()
            ->assertDontSee('LOT-JW-1')
            ->assertDontSee('Surya Sizing Works');

        $this->actingAs($companyA)
            ->get(route('textile.dispatch.index'))
            ->assertOk()
            ->assertSee('LOT-JW-1')
            ->assertSee('Surya Sizing Works')
            ->assertSee('LOT-YD-1');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Dispatch Plan',
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

    private function seedDispatchMasters(int $companyId): void
    {
        $records = [
            ['master_type' => 'source_type', 'name' => 'dispatch_plan'],
            ['master_type' => 'source_action', 'name' => 'vehicle_assign'],
            ['master_type' => 'source_action', 'name' => 'tracking_update'],
            ['master_type' => 'dispatch_truck_number', 'name' => 'GJ01-TR-5555'],
            ['master_type' => 'dispatch_container_number', 'name' => 'CONT-4455'],
            ['master_type' => 'dispatch_driver', 'name' => 'Ramesh Driver'],
            ['master_type' => 'dispatch_driver', 'name' => 'Suresh Driver'],
            ['master_type' => 'dispatch_vehicle', 'name' => 'GJ01-VH-7788'],
            ['master_type' => 'dispatch_vehicle', 'name' => 'MH12-VH-9988'],
            ['master_type' => 'dispatch_lr_number', 'name' => 'LR-1001'],
            ['master_type' => 'dispatch_lr_number', 'name' => 'LR-1002'],
            ['master_type' => 'dispatch_eway_bill', 'name' => 'EWB-9001'],
            ['master_type' => 'dispatch_eway_bill', 'name' => 'EWB-9002'],
        ];

        foreach ($records as $record) {
            TextileReferenceMaster::create([
                'master_type' => $record['master_type'],
                'name' => $record['name'],
                'code' => null,
                'description' => null,
                'is_active' => true,
                'master_domain' => 'dispatch',
                'created_by' => $companyId,
                'creator_id' => $companyId,
            ]);
        }
    }
}
