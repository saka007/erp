<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileQualityAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_inspection_and_hold_release_with_tenant_isolation(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        AddOn::create([
            'module' => 'TextileInventory',
            'name' => 'Textile Inventory',
            'package_name' => 'textile-inventory',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $companyA = $this->company();
        $companyB = $this->company();

        TextileLot::create([
            'lot_reference' => 'LOT-QA-1',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'LOT-QB-1',
            'received_quantity' => 50,
            'available_quantity' => 50,
            'status' => 'active',
            'created_by' => $companyB->id,
            'creator_id' => $companyB->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.store'), [
                'source_reference_type' => 'grn',
                'source_reference_id' => 1,
                'source_action' => 'incoming_inspection',
                'party_name' => 'Alpha Fibers',
                'lot_reference' => 'LOT-QA-1',
                'quantity' => 80,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $inspection = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'inspection')
            ->latest('id')
            ->first();

        $this->assertNotNull($inspection);
        $this->assertSame('draft', $inspection->status);

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.finalize'), [
                'inspection_id' => $inspection->id,
                'decision' => 'pass',
            ])
            ->assertSessionHasNoErrors();

        $inspection->refresh();
        $this->assertSame('approved', $inspection->status);

        $this->actingAs($companyA)
            ->post(route('textile.quality.lots.hold'), [
                'lot_reference' => 'LOT-QA-1',
                'reason' => 'Shade variance',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('textile_lots', [
            'lot_reference' => 'LOT-QA-1',
            'created_by' => $companyA->id,
            'status' => 'hold',
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.quality.lots.release'), [
                'lot_reference' => 'LOT-QA-1',
                'reason' => 'Re-inspected and approved',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('textile_lots', [
            'lot_reference' => 'LOT-QA-1',
            'created_by' => $companyA->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('textile_workflow_documents', [
            'document_type' => 'hold_release',
            'source_action' => 'hold',
            'lot_reference' => 'LOT-QA-1',
            'created_by' => $companyA->id,
        ]);

        $this->assertDatabaseHas('textile_workflow_documents', [
            'document_type' => 'hold_release',
            'source_action' => 'release',
            'lot_reference' => 'LOT-QA-1',
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyB)
            ->get(route('textile.quality.index'))
            ->assertOk()
            ->assertDontSee('LOT-QA-1')
            ->assertDontSee('Alpha Fibers');

        $this->actingAs($companyA)
            ->get(route('textile.quality.index'))
            ->assertOk()
            ->assertSee('LOT-QA-1')
            ->assertSee('Alpha Fibers')
            ->assertDontSee('LOT-QB-1');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Quality Plan',
            'modules' => ['TextileCore', 'TextileInventory'],
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

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileInventory',
        ]);

        return $company;
    }
}
