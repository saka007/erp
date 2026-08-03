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

class TextileManufacturingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_run_manufacturing_lifecycle_and_tenant_data_is_isolated(): void
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

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.store'), [
                'source_reference_type' => 'sales_order',
                'source_reference_id' => 8001,
                'source_action' => 'beam_prepare',
                'party_name' => 'Mill Unit A',
                'lot_reference' => 'LOT-M-1',
                'quantity' => 250,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->latest('id')
            ->first();

        $this->assertNotNull($beam);
        $this->assertSame('draft', $beam->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.approve'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $beam->refresh();
        $this->assertSame('approved', $beam->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beam-issues.store'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $beamIssue = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam_issue')
            ->latest('id')
            ->first();

        $this->assertNotNull($beamIssue);
        $this->assertSame('approved', $beamIssue->status);
        $this->assertSame($beam->id, $beamIssue->source_reference_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beam-returns.store'), [
                'beam_issue_id' => $beamIssue->id,
            ])
            ->assertSessionHasNoErrors();

        $beamReturn = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam_return')
            ->latest('id')
            ->first();

        $this->assertNotNull($beamReturn);
        $this->assertSame('approved', $beamReturn->status);
        $this->assertSame($beamIssue->id, $beamReturn->source_reference_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.loom-masters.store'), [
                'source_reference_type' => 'factory',
                'source_reference_id' => 9001,
                'source_action' => 'loom_register',
                'party_name' => 'Loom-A1',
                'lot_reference' => 'Rapier',
                'quantity' => 540,
                'unit' => 'rpm',
                'shed_type' => 'dobby',
                'width' => 110,
                'loom_status' => 'running',
                'running_hours' => 7.5,
                'idle_hours' => 0.5,
                'operator_name' => 'Operator A',
            ])
            ->assertSessionHasNoErrors();

        $loomMaster = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'loom_master')
            ->latest('id')
            ->first();

        $this->assertNotNull($loomMaster);
        $this->assertSame('approved', $loomMaster->status);
        $this->assertSame('dobby', $loomMaster->metadata['shed_type']);
        $this->assertSame(110.0, (float) $loomMaster->metadata['width']);
        $this->assertSame('running', $loomMaster->metadata['loom_status']);
        $this->assertSame(7.5, (float) $loomMaster->metadata['running_hours']);
        $this->assertSame(0.5, (float) $loomMaster->metadata['idle_hours']);
        $this->assertSame('Operator A', $loomMaster->metadata['operator_name']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.loom-breakdowns.store'), [
                'loom_master_id' => $loomMaster->id,
                'breakdown_reason' => 'mechanical',
                'downtime_hours' => 1.75,
                'unit' => 'hour',
                'operator_name' => 'Operator A',
                'notes' => 'Picking arm jammed and corrected',
            ])
            ->assertSessionHasNoErrors();

        $loomBreakdown = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'loom_breakdown')
            ->latest('id')
            ->first();

        $this->assertNotNull($loomBreakdown);
        $this->assertSame('approved', $loomBreakdown->status);
        $this->assertSame($loomMaster->id, $loomBreakdown->source_reference_id);
        $this->assertSame('mechanical', $loomBreakdown->metadata['breakdown_reason']);
        $this->assertSame(1.75, (float) $loomBreakdown->metadata['downtime_hours']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.loom-maintenances.store'), [
                'loom_master_id' => $loomMaster->id,
                'maintenance_type' => 'preventive',
                'maintenance_hours' => 2.0,
                'unit' => 'hour',
                'operator_name' => 'Operator A',
                'notes' => 'Lubrication and alignment check completed',
            ])
            ->assertSessionHasNoErrors();

        $loomMaintenance = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'loom_maintenance')
            ->latest('id')
            ->first();

        $this->assertNotNull($loomMaintenance);
        $this->assertSame('approved', $loomMaintenance->status);
        $this->assertSame($loomMaster->id, $loomMaintenance->source_reference_id);
        $this->assertSame('preventive', $loomMaintenance->metadata['maintenance_type']);
        $this->assertSame(2.0, (float) $loomMaintenance->metadata['maintenance_hours']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.machine-plans.store'), [
                'loom_master_id' => $loomMaster->id,
                'beam_id' => $beam->id,
                'planned_date' => '2026-08-04',
                'planned_shift' => 'day',
                'planned_quantity' => 220,
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'Plan for day shift grey production',
            ])
            ->assertSessionHasNoErrors();

        $machinePlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'machine_plan')
            ->latest('id')
            ->first();

        $this->assertNotNull($machinePlan);
        $this->assertSame('approved', $machinePlan->status);
        $this->assertSame($loomMaster->id, $machinePlan->source_reference_id);
        $this->assertSame($beam->id, $machinePlan->metadata['beam_id']);
        $this->assertSame('day', $machinePlan->metadata['planned_shift']);
        $this->assertSame('2026-08-04', $machinePlan->metadata['planned_date']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.store'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $batch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_batch')
            ->latest('id')
            ->first();

        $this->assertNotNull($batch);
        $this->assertSame('draft', $batch->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.release'), [
                'batch_id' => $batch->id,
            ])
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('released', $batch->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.weaving-output.store'), [
                'batch_id' => $batch->id,
                'quantity' => 240,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $weavingOutput = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'weaving_output')
            ->latest('id')
            ->first();

        $this->assertNotNull($weavingOutput);
        $this->assertSame('approved', $weavingOutput->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.waste.store'), [
                'batch_id' => $batch->id,
                'quantity' => 8,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $waste = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'waste')
            ->latest('id')
            ->first();

        $this->assertNotNull($waste);
        $this->assertSame('approved', $waste->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.rework.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'quantity' => 2,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $rework = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'rework')
            ->latest('id')
            ->first();

        $this->assertNotNull($rework);
        $this->assertSame('approved', $rework->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => 7001,
                'source_action' => 'warp_plan',
                'party_name' => 'Warp Unit A',
                'lot_reference' => 'WARP-LOT-1',
                'quantity' => 120,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();

        $warpPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'warp_plan')
            ->latest('id')
            ->first();

        $this->assertNotNull($warpPlan);
        $this->assertSame('draft', $warpPlan->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.approve'), [
                'warp_plan_id' => $warpPlan->id,
            ])
            ->assertSessionHasNoErrors();

        $warpPlan->refresh();
        $this->assertSame('approved', $warpPlan->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.yarn-allocations.store'), [
                'warp_plan_id' => $warpPlan->id,
            ])
            ->assertSessionHasNoErrors();

        $yarnAllocation = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'yarn_allocation')
            ->latest('id')
            ->first();

        $this->assertNotNull($yarnAllocation);
        $this->assertSame('approved', $yarnAllocation->status);
        $this->assertSame($warpPlan->id, $yarnAllocation->source_reference_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-sheets.store'), [
                'yarn_allocation_id' => $yarnAllocation->id,
            ])
            ->assertSessionHasNoErrors();

        $warpSheet = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'warp_sheet')
            ->latest('id')
            ->first();

        $this->assertNotNull($warpSheet);
        $this->assertSame('approved', $warpSheet->status);
        $this->assertSame($yarnAllocation->id, $warpSheet->source_reference_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-productions.store'), [
                'warp_sheet_id' => $warpSheet->id,
            ])
            ->assertSessionHasNoErrors();

        $warpProduction = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'warp_production')
            ->latest('id')
            ->first();

        $this->assertNotNull($warpProduction);
        $this->assertSame('approved', $warpProduction->status);
        $this->assertSame($warpSheet->id, $warpProduction->source_reference_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.sizing-recipes.store'), [
                'warp_production_id' => $warpProduction->id,
            ])
            ->assertSessionHasNoErrors();

        $sizingRecipe = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'sizing_recipe')
            ->latest('id')
            ->first();

        $this->assertNotNull($sizingRecipe);
        $this->assertSame('approved', $sizingRecipe->status);
        $this->assertSame($warpProduction->id, $sizingRecipe->source_reference_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.chemical-consumptions.store'), [
                'sizing_recipe_id' => $sizingRecipe->id,
                'chemical_type' => 'Starch',
                'composition_percent' => 62.5,
                'consumption_quantity' => 15,
                'unit' => 'kg',
                'notes' => 'Standard sizing blend',
            ])
            ->assertSessionHasNoErrors();

        $chemicalConsumption = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'chemical_consumption')
            ->latest('id')
            ->first();

        $this->assertNotNull($chemicalConsumption);
        $this->assertSame('approved', $chemicalConsumption->status);
        $this->assertSame($sizingRecipe->id, $chemicalConsumption->source_reference_id);
        $this->assertSame('Starch', $chemicalConsumption->metadata['chemical_type']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.from-sizing-recipe'), [
                'sizing_recipe_id' => $sizingRecipe->id,
            ])
            ->assertSessionHasNoErrors();

        $beamFromSizing = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->where('source_reference_id', $sizingRecipe->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($beamFromSizing);
        $this->assertSame('draft', $beamFromSizing->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beam-inspections.store'), [
                'beam_id' => $beam->id,
                'inspection_result' => 'pass',
                'remarks' => 'Beam quality verified for downstream use',
            ])
            ->assertSessionHasNoErrors();

        $beamInspection = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam_inspection')
            ->latest('id')
            ->first();

        $this->assertNotNull($beamInspection);
        $this->assertSame('approved', $beamInspection->status);
        $this->assertSame($beam->id, $beamInspection->source_reference_id);
        $this->assertSame('pass', $beamInspection->metadata['inspection_result']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beam-costs.store'), [
                'beam_id' => $beam->id,
                'cost_type' => 'sizing_overhead',
                'cost_amount' => 3250,
                'quantity' => 250,
                'unit' => 'mtr',
                'notes' => 'Sizing and preparation overhead for beam run',
            ])
            ->assertSessionHasNoErrors();

        $beamCost = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam_cost')
            ->latest('id')
            ->first();

        $this->assertNotNull($beamCost);
        $this->assertSame('approved', $beamCost->status);
        $this->assertSame($beam->id, $beamCost->source_reference_id);
        $this->assertSame('sizing_overhead', $beamCost->metadata['cost_type']);
        $this->assertSame(3250.0, (float) $beamCost->metadata['cost_amount']);

        $this->actingAs($companyB)
            ->get(route('textile.manufacturing.index'))
            ->assertOk()
            ->assertDontSee('LOT-M-1')
            ->assertDontSee('Mill Unit A')
            ->assertDontSee('WARP-LOT-1')
            ->assertDontSee('Warp Unit A')
            ->assertDontSee('Loom-A1')
            ->assertDontSee($beamIssue->document_number)
            ->assertDontSee($beamReturn->document_number)
            ->assertDontSee($loomMaster->document_number)
            ->assertDontSee($loomBreakdown->document_number)
            ->assertDontSee($loomMaintenance->document_number)
            ->assertDontSee($machinePlan->document_number)
            ->assertDontSee($beamFromSizing->document_number)
            ->assertDontSee($warpSheet->document_number)
            ->assertDontSee($warpProduction->document_number)
            ->assertDontSee($sizingRecipe->document_number)
            ->assertDontSee($chemicalConsumption->document_number)
            ->assertDontSee($beamInspection->document_number)
            ->assertDontSee($beamCost->document_number);

        $this->actingAs($companyA)
            ->get(route('textile.manufacturing.index'))
            ->assertOk()
            ->assertSee('LOT-M-1')
            ->assertSee('Mill Unit A')
            ->assertSee('WARP-LOT-1')
            ->assertSee('Warp Unit A')
            ->assertSee('Loom-A1')
            ->assertSee($beamIssue->document_number)
            ->assertSee($beamReturn->document_number)
                ->assertSee($beamFromSizing->document_number)
                ->assertSee($loomMaster->document_number)
                ->assertSee($loomBreakdown->document_number)
                ->assertSee($loomMaintenance->document_number)
                ->assertSee($machinePlan->document_number)
                ->assertSee($warpSheet->document_number)
                ->assertSee($warpProduction->document_number)
                ->assertSee($sizingRecipe->document_number)
                ->assertSee($chemicalConsumption->document_number)
                ->assertSee($beamInspection->document_number)
                ->assertSee($beamCost->document_number);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Manufacturing Plan',
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
