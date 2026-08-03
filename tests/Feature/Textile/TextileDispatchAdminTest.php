<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

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
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'challan_id' => $challanA->id,
                'dispatch_mode' => 'truck',
                'truck_number' => 'GJ01-TR-5555',
                'driver_name' => 'Ramesh Driver',
                'vehicle_number' => 'GJ01-VH-7788',
                'lr_number' => 'LR-1001',
                'eway_bill_number' => 'EWB-9001',
                'freight_amount' => 8500,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.dispatch.plans.store'), [
                'source_reference_type' => 'dispatch_plan',
                'source_action' => 'vehicle_assign',
                'challan_id' => $challanA->id,
                'dispatch_mode' => 'container',
                'container_number' => 'CONT-4455',
                'driver_name' => 'Suresh Driver',
                'vehicle_number' => 'MH12-VH-9988',
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
                'vehicle_number' => 'GJ01-VH-7788',
                'driver_name' => 'Ramesh Driver',
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
}
