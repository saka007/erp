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

class TextilePackingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_packing_and_labels_with_tenant_isolation(): void
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
            'document_number' => 'CHL-A-001',
            'party_name' => 'Metro Textiles',
            'lot_reference' => 'LOT-P-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'released',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        TextileWorkflowDocument::create([
            'document_type' => 'challan',
            'document_number' => 'CHL-B-001',
            'party_name' => 'Other Textile Buyer',
            'lot_reference' => 'LOT-P-2',
            'quantity' => 75,
            'unit' => 'mtr',
            'status' => 'released',
            'creator_id' => $companyB->id,
            'created_by' => $companyB->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.packing.rolls.store'), [
                'source_reference_type' => 'challan',
                'challan_id' => $challanA->id,
                'lot_reference' => 'LOT-P-1',
                'quantity' => 120,
                'unit' => 'mtr',
                'packing_material' => 'poly_wrap',
                'weight' => 45,
                'notes' => 'Roll-wise packing done',
            ])
            ->assertSessionHasNoErrors();

        $rollPacking = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'roll_packing')
            ->latest('id')
            ->first();

        $this->assertNotNull($rollPacking);
        $this->assertSame('draft', $rollPacking->status);
        $this->assertSame('poly_wrap', $rollPacking->metadata['packing_material'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.packing.bundles.store'), [
                'source_reference_type' => 'challan',
                'challan_id' => $challanA->id,
                'lot_reference' => 'LOT-P-1',
                'quantity' => 60,
                'unit' => 'mtr',
                'packing_material' => 'carton_box',
                'weight' => 24,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.packing.bales.store'), [
                'source_reference_type' => 'challan',
                'challan_id' => $challanA->id,
                'lot_reference' => 'LOT-P-1',
                'quantity' => 60,
                'unit' => 'mtr',
                'packing_material' => 'jute_bale',
                'weight' => 21,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('textile_workflow_documents', [
            'document_type' => 'bundle_packing',
            'created_by' => $companyA->id,
            'lot_reference' => 'LOT-P-1',
        ]);

        $this->assertDatabaseHas('textile_workflow_documents', [
            'document_type' => 'bale_packing',
            'created_by' => $companyA->id,
            'lot_reference' => 'LOT-P-1',
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.packing.labels.store'), [
                'source_reference_type' => 'challan',
                'challan_id' => $challanA->id,
                'lot_reference' => 'LOT-P-1',
                'quantity' => 120,
                'unit' => 'mtr',
                'packing_material' => 'poly_wrap',
                'label_type' => 'barcode',
                'label_code' => 'LBL-0001',
                'weight' => 45,
                'notes' => 'Barcode label generated',
            ])
            ->assertSessionHasNoErrors();

        $label = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'packing_label')
            ->latest('id')
            ->first();

        $this->assertNotNull($label);
        $this->assertSame('draft', $label->status);
        $this->assertSame('barcode', $label->metadata['label_type'] ?? null);
        $this->assertSame('LBL-0001', $label->metadata['label_code'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.packing.labels.issue'), [
                'label_id' => $label->id,
            ])
            ->assertSessionHasNoErrors();

        $label->refresh();
        $this->assertSame('approved', $label->status);

        $this->actingAs($companyB)
            ->get(route('textile.packing.index'))
            ->assertOk()
            ->assertDontSee('LOT-P-1')
            ->assertDontSee('Metro Textiles')
            ->assertDontSee('LBL-0001');

        $this->actingAs($companyA)
            ->get(route('textile.packing.index'))
            ->assertOk()
            ->assertSee('LOT-P-1')
            ->assertSee('Metro Textiles')
            ->assertSee('LBL-0001')
            ->assertDontSee('LOT-P-2');

        $this->actingAs($companyB)
            ->post(route('textile.packing.labels.issue'), [
                'label_id' => $label->id,
            ])
            ->assertSessionHasErrors('label_id');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Packing Plan',
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
