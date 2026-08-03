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
                'source_reference_type' => 'incoming_qc',
                'source_reference_id' => 1,
                'source_action' => 'incoming_qc',
                'party_name' => 'Alpha Fibers',
                'lot_reference' => 'LOT-QA-1',
                'quantity' => 80,
                'unit' => 'mtr',
                'qc_stage' => 'in_process_qc',
                'inspection_result' => 'pass',
                'defects' => ['shade_variance'],
                'notes' => 'Process QC check',
            ])
            ->assertSessionHasNoErrors();

        $inspection = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'inspection')
            ->latest('id')
            ->first();

        $this->assertNotNull($inspection);
        $this->assertSame('draft', $inspection->status);
        $this->assertSame('in_process_qc', $inspection->metadata['qc_stage'] ?? null);
        $this->assertSame('pass', $inspection->metadata['inspection_result'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.store'), [
                'source_reference_type' => 'shade_matching',
                'source_reference_id' => 2,
                'source_action' => 'shade_matching',
                'party_name' => 'Alpha Fibers',
                'lot_reference' => 'LOT-QA-1',
                'quantity' => 20,
                'unit' => 'mtr',
                'qc_stage' => 'shade_matching',
                'inspection_result' => 'rework',
                'shade_reference' => 'SC-100',
                'defects' => ['stain'],
            ])
            ->assertSessionHasNoErrors();

        $shadeInspection = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'inspection')
            ->where('source_reference_type', 'shade_matching')
            ->latest('id')
            ->first();

        $this->assertNotNull($shadeInspection);
        $this->assertSame('shade_matching', $shadeInspection->metadata['qc_stage'] ?? null);
        $this->assertSame('SC-100', $shadeInspection->metadata['shade_reference'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.finalize'), [
                'inspection_id' => $inspection->id,
                'decision' => 'pass',
            ])
            ->assertSessionHasNoErrors();

        $inspection->refresh();
        $this->assertSame('approved', $inspection->status);

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.finalize'), [
                'inspection_id' => $shadeInspection->id,
                'decision' => 'fail',
            ])
            ->assertSessionHasNoErrors();

        $shadeInspection->refresh();
        $this->assertSame('rejected', $shadeInspection->status);
        $this->assertSame('fail', $shadeInspection->metadata['final_decision'] ?? null);

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

        $this->actingAs($companyA)
            ->post(route('textile.quality.certificates.store'), [
                'source_reference_type' => 'quality_certificate',
                'source_action' => 'quality_certificate',
                'inspection_id' => $inspection->id,
                'lot_reference' => 'LOT-QA-1',
                'certificate_number' => 'QC-CERT-001',
                'notes' => 'Issued after process QC',
            ])
            ->assertSessionHasNoErrors();

        $certificate = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'quality_certificate')
            ->latest('id')
            ->first();

        $this->assertNotNull($certificate);
        $this->assertSame('draft', $certificate->status);
        $this->assertSame('QC-CERT-001', $certificate->metadata['certificate_number'] ?? null);

        $this->actingAs($companyA)
            ->post(route('textile.quality.certificates.issue'), [
                'certificate_id' => $certificate->id,
            ])
            ->assertSessionHasNoErrors();

        $certificate->refresh();
        $this->assertSame('approved', $certificate->status);

        $this->actingAs($companyB)
            ->get(route('textile.quality.index'))
            ->assertOk()
            ->assertDontSee('LOT-QA-1')
            ->assertDontSee('Alpha Fibers')
            ->assertDontSee('QC-CERT-001');

        $this->actingAs($companyA)
            ->get(route('textile.quality.index'))
            ->assertOk()
            ->assertSee('LOT-QA-1')
            ->assertSee('Alpha Fibers')
            ->assertSee('QC-CERT-001')
            ->assertDontSee('LOT-QB-1');

        $this->actingAs($companyB)
            ->post(route('textile.quality.certificates.issue'), [
                'certificate_id' => $certificate->id,
            ])
            ->assertSessionHasErrors('certificate_id');
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
