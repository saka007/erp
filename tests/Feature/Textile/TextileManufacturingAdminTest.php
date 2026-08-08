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
use Workdo\Hrm\Models\Branch;

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
        $branchA = Branch::create([
            'branch_name' => 'Main Production Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

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
            ->post(route('textile.manufacturing.loom-masters.store'), [
                'source_reference_type' => 'factory',
                'source_reference_id' => 9002,
                'source_action' => 'loom_register',
                'party_name' => 'Loom-A2',
                'lot_reference' => 'Rapier',
                'quantity' => 520,
                'unit' => 'rpm',
                'shed_type' => 'dobby',
                'width' => 110,
                'loom_status' => 'running',
                'running_hours' => 7,
                'idle_hours' => 1,
                'operator_name' => 'Operator B',
            ])
            ->assertSessionHasNoErrors();

        $secondLoomMaster = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'loom_master')
            ->where('source_reference_id', 9002)
            ->firstOrFail();

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
            ->post(route('textile.manufacturing.production-calendars.store'), [
                'plan_date' => '2026-08-04',
                'day_type' => 'working',
                'planned_shift' => 'day',
                'notes' => 'Standard working day',
            ])
            ->assertSessionHasNoErrors();

        $productionCalendar = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_calendar')
            ->latest('id')
            ->first();

        $this->assertNotNull($productionCalendar);
        $this->assertSame('working', $productionCalendar->metadata['day_type']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.capacity-plans.store'), [
                'loom_master_id' => $loomMaster->id,
                'plan_date' => '2026-08-04',
                'available_hours' => 12,
                'capacity_quantity' => 260,
                'unit' => 'mtr',
                'efficiency_target' => 88,
                'operator_name' => 'Operator A',
                'notes' => 'Capacity for day run',
            ])
            ->assertSessionHasNoErrors();

        $capacityPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'capacity_plan')
            ->latest('id')
            ->first();

        $this->assertNotNull($capacityPlan);
        $this->assertSame($loomMaster->id, $capacityPlan->source_reference_id);
        $this->assertSame(88.0, (float) $capacityPlan->metadata['efficiency_target']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.shift-plans.store'), [
                'loom_master_id' => $loomMaster->id,
                'plan_date' => '2026-08-04',
                'planned_shift' => 'night',
                'expected_hours' => 8,
                'unit' => 'hour',
                'operator_name' => 'Operator A',
                'notes' => 'Night shift plan',
            ])
            ->assertSessionHasNoErrors();

        $shiftPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'shift_plan')
            ->latest('id')
            ->first();

        $this->assertNotNull($shiftPlan);
        $this->assertSame('night', $shiftPlan->metadata['planned_shift']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.material-plans.store'), [
                'beam_id' => $beam->id,
                'plan_date' => '2026-08-04',
                'required_quantity' => 230,
                'unit' => 'mtr',
                'notes' => 'Reserve beam quantity for run',
            ])
            ->assertSessionHasNoErrors();

        $materialPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'material_plan')
            ->latest('id')
            ->first();

        $this->assertNotNull($materialPlan);
        $this->assertSame($beam->id, $materialPlan->source_reference_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.production-schedules.store'), [
                'loom_master_id' => $loomMaster->id,
                'beam_id' => $beam->id,
                'scheduled_date' => '2026-08-05',
                'scheduled_shift' => 'day',
                'scheduled_quantity' => 210,
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'Scheduled production commitment',
            ])
            ->assertSessionHasNoErrors();

        $productionSchedule = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_schedule')
            ->latest('id')
            ->first();

        $this->assertNotNull($productionSchedule);
        $this->assertSame($loomMaster->id, $productionSchedule->source_reference_id);
        $this->assertSame($beam->id, $productionSchedule->metadata['beam_id']);

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
            ->post(route('textile.manufacturing.production-assignments.store'), [
                'batch_id' => $batch->id,
                'production_mode' => 'own_unit',
                'assigned_quantity' => 200,
                'assignment_date' => now()->toDateString(),
                'expected_completion_date' => now()->addDays(2)->toDateString(),
                'loom_allocations' => [
                    ['loom_master_id' => $loomMaster->id, 'quantity' => 125],
                    ['loom_master_id' => $secondLoomMaster->id, 'quantity' => 75],
                ],
                'planned_shift' => 'day',
                'operator_name' => 'Operator A',
                'notes' => 'Own-unit beam assignment',
            ])
            ->assertSessionHasNoErrors();

        $productionAssignment = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_assignment')
            ->latest('id')
            ->first();

        $this->assertNotNull($productionAssignment);
        $this->assertSame($batch->id, $productionAssignment->source_reference_id);
        $this->assertSame('own_unit', $productionAssignment->metadata['production_mode']);
        $this->assertCount(2, $productionAssignment->metadata['loom_allocations']);
        $this->assertSame($loomMaster->id, $productionAssignment->metadata['loom_allocations'][0]['loom_master_id']);
        $this->assertSame($secondLoomMaster->id, $productionAssignment->metadata['loom_allocations'][1]['loom_master_id']);
        $this->assertSame($branchA->id, $productionAssignment->branch_id);
        $this->assertSame($batch->branch_id, $productionAssignment->branch_id);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'production_assignment_id' => $productionAssignment->id,
                'takhas' => [
                    ['takha_number' => 'TAKHA-ASSIGN-001', 'quantity' => 60],
                    ['takha_number' => 'TAKHA-ASSIGN-002', 'quantity' => 65],
                ],
                'unit' => 'mtr',
                'production_date' => now()->toDateString(),
                'loom_master_id' => $loomMaster->id,
                'operator_name' => 'Operator A',
            ])
            ->assertSessionHasNoErrors();

        $assignmentTakha = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'takha_entry')
            ->where('source_reference_id', $productionAssignment->id)
            ->where('lot_reference', 'TAKHA-ASSIGN-001')
            ->first();

        $this->assertNotNull($assignmentTakha);
        $this->assertSame('TAKHA-ASSIGN-001', $assignmentTakha->lot_reference);
        $this->assertSame('own_unit', $assignmentTakha->metadata['production_mode']);
        $this->assertSame($productionAssignment->branch_id, $assignmentTakha->branch_id);
        $this->assertSame(2, TextileWorkflowDocument::query()->where('document_type', 'takha_entry')->where('source_reference_id', $productionAssignment->id)->count());
        $this->assertDatabaseHas('textile_lots', ['created_by' => $companyA->id, 'lot_reference' => 'TAKHA-ASSIGN-001', 'material_type' => TextileLot::TYPE_GREY_FABRIC]);
        $this->assertDatabaseHas('textile_lots', ['created_by' => $companyA->id, 'lot_reference' => 'TAKHA-ASSIGN-002', 'material_type' => TextileLot::TYPE_GREY_FABRIC]);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'production_assignment_id' => $productionAssignment->id,
                'takha_number' => 'TAKHA-ASSIGN-OVER',
                'quantity' => 1,
                'unit' => 'mtr',
                'production_date' => now()->toDateString(),
                'loom_master_id' => $loomMaster->id,
            ])
            ->assertSessionHasErrors('production_assignment_id');

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
            ->post(route('textile.manufacturing.shift-productions.store'), [
                'batch_id' => $batch->id,
                'loom_master_id' => $loomMaster->id,
                'planned_shift' => 'day',
                'quantity' => 120,
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'Day shift weaving production',
            ])
            ->assertSessionHasNoErrors();

        $shiftProduction = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'shift_production')
            ->latest('id')
            ->first();

        $this->assertNotNull($shiftProduction);
        $this->assertSame('day', $shiftProduction->metadata['planned_shift']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'takha_number' => 'TAKHA-001',
                'quantity' => 60,
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'First takha from loom run',
            ])
            ->assertSessionHasNoErrors();

        $takhaEntry = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'takha_entry')
            ->latest('id')
            ->first();

        $this->assertNotNull($takhaEntry);
        $this->assertSame('TAKHA-001', $takhaEntry->metadata['takha_number']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.loom-efficiencies.store'), [
                'loom_master_id' => $loomMaster->id,
                'planned_shift' => 'day',
                'planned_quantity' => 140,
                'actual_quantity' => 120,
                'runtime_hours' => 7,
                'downtime_hours' => 1,
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'Efficiency snapshot for day shift',
            ])
            ->assertSessionHasNoErrors();

        $loomEfficiency = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'loom_efficiency')
            ->latest('id')
            ->first();

        $this->assertNotNull($loomEfficiency);
        $this->assertSame(85.71, (float) $loomEfficiency->metadata['efficiency_percent']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.operator-efficiencies.store'), [
                'planned_shift' => 'day',
                'planned_quantity' => 130,
                'actual_quantity' => 120,
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'Operator performance for day shift',
            ])
            ->assertSessionHasNoErrors();

        $operatorEfficiency = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'operator_efficiency')
            ->latest('id')
            ->first();

        $this->assertNotNull($operatorEfficiency);
        $this->assertSame(92.31, (float) $operatorEfficiency->metadata['efficiency_percent']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.machine-downtimes.store'), [
                'loom_master_id' => $loomMaster->id,
                'planned_shift' => 'day',
                'downtime_reason' => 'mechanical',
                'downtime_hours' => 1.25,
                'unit' => 'hour',
                'operator_name' => 'Operator A',
                'notes' => 'Stopped for mechanical adjustment',
            ])
            ->assertSessionHasNoErrors();

        $machineDowntime = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'machine_downtime')
            ->latest('id')
            ->first();

        $this->assertNotNull($machineDowntime);
        $this->assertSame('mechanical', $machineDowntime->metadata['downtime_reason']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.production-costs.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'cost_amount' => 4800,
                'quantity' => 240,
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'Direct weaving production cost',
            ])
            ->assertSessionHasNoErrors();

        $productionCost = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_cost')
            ->latest('id')
            ->first();

        $this->assertNotNull($productionCost);
        $this->assertSame(20.0, (float) $productionCost->metadata['cost_per_unit']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.grey-fabric-rolls.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'roll_number' => 'ROLL-0001',
                'roll_barcode' => 'BAR-0001',
                'roll_qr_code' => 'QR-0001',
                'roll_weight' => 48.5,
                'roll_length' => 240,
                'gsm' => 120,
                'width' => 58,
                'defects' => ['slub'],
                'grade' => 'A',
                'warehouse' => 'grey_store',
                'unit' => 'mtr',
                'operator_name' => 'Operator A',
                'notes' => 'Initial grey roll generation',
            ])
            ->assertSessionHasNoErrors();

        $greyRoll = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'grey_fabric_roll')
            ->latest('id')
            ->first();

        $this->assertNotNull($greyRoll);
        $this->assertSame('ROLL-0001', $greyRoll->metadata['roll_number']);
        $this->assertSame(48.5, (float) $greyRoll->metadata['roll_weight']);
        $this->assertSame('A', $greyRoll->metadata['grade']);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.grey-fabric-rolls.update'), [
                'grey_roll_id' => $greyRoll->id,
                'roll_weight' => 49.0,
                'roll_length' => 238,
                'gsm' => 118,
                'width' => 57,
                'defects' => ['slub', 'stain'],
                'grade' => 'B',
                'warehouse' => 'dispatch_store',
                'operator_name' => 'Operator A',
                'notes' => 'Post-inspection update',
            ])
            ->assertSessionHasNoErrors();

        $greyRoll->refresh();
        $this->assertSame(49.0, (float) $greyRoll->metadata['roll_weight']);
        $this->assertSame(238.0, (float) $greyRoll->metadata['roll_length']);
        $this->assertSame(118.0, (float) $greyRoll->metadata['gsm']);
        $this->assertSame(57.0, (float) $greyRoll->metadata['width']);
        $this->assertSame('B', $greyRoll->metadata['grade']);
        $this->assertSame('dispatch_store', $greyRoll->metadata['warehouse']);

        $greyRollHistory = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'grey_roll_history')
            ->latest('id')
            ->first();

        $this->assertNotNull($greyRollHistory);
        $this->assertSame($greyRoll->id, $greyRollHistory->source_reference_id);
        $this->assertSame('updated', $greyRollHistory->metadata['history_event']);

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
            ->post(route('textile.manufacturing.beams.from-yarn-allocation'), [
                'yarn_allocation_id' => $yarnAllocation->id,
                'quantity' => 121,
            ])
            ->assertSessionHasErrors('yarn_allocation_id');

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.from-yarn-allocation'), [
                'yarn_allocation_id' => $yarnAllocation->id,
                'quantity' => 110,
            ])
            ->assertSessionHasNoErrors();

        $vendorBeam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->where('source_reference_id', $yarnAllocation->id)
            ->first();

        $this->assertNotNull($vendorBeam);
        $this->assertSame('draft', $vendorBeam->status);
        $this->assertSame('vendor_beam_receipt', $vendorBeam->source_action);
        $this->assertSame($yarnAllocation->party_name, $vendorBeam->party_name);
        $this->assertSame('BEAM-' . str_pad((string) $yarnAllocation->id, 6, '0', STR_PAD_LEFT), $vendorBeam->lot_reference);
        $this->assertSame($yarnAllocation->lot_reference, $vendorBeam->metadata['source_yarn_lot']);
        $this->assertEquals(110, (float) $vendorBeam->quantity);

        $vendorBeamLot = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('lot_reference', $vendorBeam->lot_reference)
            ->first();

        $this->assertNotNull($vendorBeamLot);
        $this->assertSame(TextileLot::TYPE_BEAM, $vendorBeamLot->material_type);
        $this->assertEquals(110, (float) $vendorBeamLot->available_quantity);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.from-yarn-allocation'), [
                'yarn_allocation_id' => $yarnAllocation->id,
                'quantity' => 100,
            ])
            ->assertSessionHasErrors('yarn_allocation_id');

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
            ->assertDontSee($productionCalendar->document_number)
            ->assertDontSee($capacityPlan->document_number)
            ->assertDontSee($shiftPlan->document_number)
            ->assertDontSee($materialPlan->document_number)
            ->assertDontSee($productionSchedule->document_number)
            ->assertDontSee($shiftProduction->document_number)
            ->assertDontSee($takhaEntry->document_number)
            ->assertDontSee($loomEfficiency->document_number)
            ->assertDontSee($operatorEfficiency->document_number)
            ->assertDontSee($machineDowntime->document_number)
            ->assertDontSee($productionCost->document_number)
            ->assertDontSee($greyRoll->document_number)
            ->assertDontSee($greyRollHistory->document_number)
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
                ->assertSee($productionCalendar->document_number)
                ->assertSee($capacityPlan->document_number)
                ->assertSee($shiftPlan->document_number)
                ->assertSee($materialPlan->document_number)
                ->assertSee($productionSchedule->document_number)
                ->assertSee($shiftProduction->document_number)
                ->assertSee($takhaEntry->document_number)
                ->assertSee($loomEfficiency->document_number)
                ->assertSee($operatorEfficiency->document_number)
                ->assertSee($machineDowntime->document_number)
                ->assertSee($productionCost->document_number)
                ->assertSee($greyRoll->document_number)
                ->assertSee($greyRollHistory->document_number)
                ->assertSee($warpSheet->document_number)
                ->assertSee($warpProduction->document_number)
                ->assertSee($sizingRecipe->document_number)
                ->assertSee($chemicalConsumption->document_number)
                ->assertSee($beamInspection->document_number)
                ->assertSee($beamCost->document_number);

        // With no branch selected in session, the platform auto-defaults to the
        // tenant's first branch, so creation succeeds and lands in that branch.
        $this->withSession(['active_branch_id' => null])
            ->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.store'), [
                'source_reference_type' => 'sales_order',
                'source_reference_id' => 99001,
                'source_action' => 'beam_prepare',
                'party_name' => 'Auto Branch Beam',
                'lot_reference' => 'BRANCH-DEFAULTED',
                'quantity' => 10,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $defaultedBeam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->where('lot_reference', 'BRANCH-DEFAULTED')
            ->latest('id')
            ->first();

        $this->assertNotNull($defaultedBeam);
        $this->assertSame((int) $branchA->id, (int) $defaultedBeam->branch_id);
    }

    public function test_takha_entry_requires_unique_number_and_stays_within_output_quantity(): void
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
        $branchA = Branch::create([
            'branch_name' => 'Takha Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        $weavingOutput = TextileWorkflowDocument::create([
            'document_type' => 'weaving_output',
            'document_number' => 'WO-GATE-001',
            'lot_reference' => 'WO-LOT-GATE-1',
            'quantity' => 100,
            'unit' => 'mtr',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        // Valid takha within output quantity.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'takha_number' => 'TAKHA-GATE-1',
                'quantity' => 60,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        // Duplicate takha number in the same branch is rejected.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'takha_number' => 'TAKHA-GATE-1',
                'quantity' => 10,
                'unit' => 'mtr',
            ])
            ->assertSessionHasErrors('production_assignment_id');

        // Sum of takhas exceeding the weaving output quantity is rejected.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'takha_number' => 'TAKHA-GATE-2',
                'quantity' => 50,
                'unit' => 'mtr',
            ])
            ->assertSessionHasErrors('production_assignment_id');

        // Missing takha number is rejected.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'quantity' => 10,
                'unit' => 'mtr',
            ])
            ->assertSessionHasErrors('takha_number');
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

    public function test_warp_plan_requires_yarn_lot_to_have_passed_incoming_qc(): void
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
        $branchA = Branch::create([
            'branch_name' => 'Warp Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        // Yarn lot born without incoming QC (e.g. manual entry) must be rejected.
        TextileLot::create([
            'lot_reference' => 'YARN-NO-QC',
            'received_quantity' => 500,
            'available_quantity' => 500,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'source_document_type' => 'manual',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => 7001,
                'source_action' => 'warp_plan',
                'party_name' => 'Warp Unit A',
                'lot_reference' => 'YARN-NO-QC',
                'quantity' => 120,
                'unit' => 'kg',
            ])
            ->assertSessionHasErrors('source_reference_id');

        // Yarn lot that passed incoming QC is accepted.
        $incomingQc = TextileWorkflowDocument::create([
            'document_type' => 'incoming_qc',
            'document_number' => 'IQC-WARP-001',
            'lot_reference' => 'YARN-QC-OK',
            'quantity' => 500,
            'unit' => 'kg',
            'status' => 'approved',
            'metadata' => ['inspection_result' => 'pass', 'final_decision' => 'pass'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);
        TextileLot::create([
            'lot_reference' => 'YARN-QC-OK',
            'received_quantity' => 500,
            'available_quantity' => 500,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'source_document_type' => 'incoming_qc',
            'source_document_id' => $incomingQc->id,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => 7002,
                'source_action' => 'warp_plan',
                'party_name' => 'Warp Unit A',
                'lot_reference' => 'YARN-QC-OK',
                'quantity' => 120,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();
    }
}
