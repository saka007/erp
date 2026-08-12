<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileManufacturingService;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Hrm\Models\Branch;

class TextileManufacturingConsumedSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_yarn_allocation_cannot_be_created_twice_from_same_warp_plan(): void
    {
        $service = $this->manufacturingService();

        $warpPlan = $this->approvedWarpPlan('Consumed Allocation Lot', 500, 100);
        $service->createYarnAllocation((int) $warpPlan->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Yarn already allocated for this warp plan.');

        $service->createYarnAllocation((int) $warpPlan->id);
    }

    public function test_yarn_allocation_rejected_when_stock_is_insufficient(): void
    {
        $service = $this->manufacturingService();

        $warpPlan = $this->approvedWarpPlan('Low Stock Lot', 50, 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient yarn stock for this warp plan');

        $service->createYarnAllocation((int) $warpPlan->id);
    }

    public function test_yarn_allocation_allowed_when_stock_is_sufficient(): void
    {
        $service = $this->manufacturingService();

        $warpPlan = $this->approvedWarpPlan('Sufficient Stock Lot', 500, 100);
        $allocation = $service->createYarnAllocation((int) $warpPlan->id);

        $this->assertSame('yarn_allocation', $allocation->document_type);
        $this->assertSame((int) $warpPlan->id, (int) $allocation->source_reference_id);
        $this->assertSame('approved', $allocation->status);
    }

    public function test_warp_sheet_cannot_be_created_twice_from_same_yarn_allocation(): void
    {
        $service = $this->manufacturingService();

        $yarnAllocation = $this->completedYarnAllocation('Consumed Warp Sheet Lot');
        $service->createWarpSheet((int) $yarnAllocation->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Warp sheet already created for this yarn allocation.');

        $service->createWarpSheet((int) $yarnAllocation->id);
    }

    public function test_warp_production_cannot_be_created_twice_from_same_warp_sheet(): void
    {
        $service = $this->manufacturingService();

        $warpSheet = $this->completedWarpSheet('Consumed Warp Production Lot');
        $service->createWarpProduction((int) $warpSheet->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Warp production already recorded for this warp sheet.');

        $service->createWarpProduction((int) $warpSheet->id);
    }

    public function test_sizing_recipe_cannot_be_created_twice_from_same_warp_production(): void
    {
        $service = $this->manufacturingService();

        $warpProduction = $this->completedWarpProduction('Consumed Sizing Recipe Lot');
        $service->createSizingRecipe((int) $warpProduction->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sizing recipe already created for this warp production.');

        $service->createSizingRecipe((int) $warpProduction->id);
    }

    public function test_beam_issue_cannot_be_created_twice_from_same_beam(): void
    {
        $service = $this->manufacturingService();

        $beam = $this->approvedBeam();
        $service->createBeamIssue((int) $beam->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Beam already issued.');

        $service->createBeamIssue((int) $beam->id);
    }

    public function test_beam_return_cannot_be_created_twice_from_same_beam_issue(): void
    {
        $service = $this->manufacturingService();

        $beamIssue = $this->createdBeamIssue();
        $service->createBeamReturn((int) $beamIssue->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Beam return already recorded for this beam issue.');

        $service->createBeamReturn((int) $beamIssue->id);
    }

    public function test_beam_inspection_cannot_be_created_twice_from_same_beam(): void
    {
        $service = $this->manufacturingService();

        $beam = $this->approvedBeam();
        $service->createBeamInspection((int) $beam->id, ['inspection_result' => 'pass']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Beam inspection already recorded for this beam.');

        $service->createBeamInspection((int) $beam->id, ['inspection_result' => 'pass']);
    }

    public function test_beam_cost_cannot_be_created_twice_from_same_beam(): void
    {
        $service = $this->manufacturingService();

        $beam = $this->approvedBeam();
        $service->createBeamCost((int) $beam->id, ['cost_type' => 'sizing', 'cost_amount' => 500]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Beam cost already captured for this beam.');

        $service->createBeamCost((int) $beam->id, ['cost_type' => 'sizing', 'cost_amount' => 500]);
    }

    public function test_loom_breakdown_cannot_be_created_twice_from_same_loom(): void
    {
        $service = $this->manufacturingService();

        $loomMaster = $this->loomMaster();
        $service->createLoomBreakdown((int) $loomMaster->id, ['breakdown_reason' => 'yarn_break']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Breakdown already recorded for this loom.');

        $service->createLoomBreakdown((int) $loomMaster->id, ['breakdown_reason' => 'yarn_break']);
    }

    public function test_loom_maintenance_cannot_be_created_twice_from_same_loom(): void
    {
        $service = $this->manufacturingService();

        $loomMaster = $this->loomMaster();
        $service->createLoomMaintenance((int) $loomMaster->id, ['maintenance_type' => 'preventive']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maintenance already recorded for this loom.');

        $service->createLoomMaintenance((int) $loomMaster->id, ['maintenance_type' => 'preventive']);
    }

    public function test_production_batch_cannot_be_created_twice_from_same_beam(): void
    {
        $service = $this->manufacturingService();

        $beam = $this->approvedBeam();
        $service->createProductionBatch((int) $beam->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Production batch already created for this beam.');

        $service->createProductionBatch((int) $beam->id);
    }

    public function test_weaving_output_cannot_be_created_twice_from_same_batch(): void
    {
        $service = $this->manufacturingService();

        $batch = $this->releasedBatch();
        $service->createWeavingOutput((int) $batch->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Weaving output already recorded for this batch.');

        $service->createWeavingOutput((int) $batch->id);
    }

    public function test_waste_cannot_be_created_twice_from_same_batch(): void
    {
        $service = $this->manufacturingService();

        $batch = $this->releasedBatch();
        $service->createWaste((int) $batch->id, ['quantity' => 5, 'unit' => 'kg']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Waste already recorded for this batch.');

        $service->createWaste((int) $batch->id, ['quantity' => 5, 'unit' => 'kg']);
    }

    public function test_grey_fabric_roll_cannot_be_created_twice_from_same_weaving_output(): void
    {
        $service = $this->manufacturingService();

        $output = $this->completedWeavingOutput();
        $service->createGreyFabricRoll((int) $output->id, ['roll_weight' => 20, 'roll_length' => 100]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grey fabric roll already generated for this weaving output.');

        $service->createGreyFabricRoll((int) $output->id, ['roll_weight' => 20, 'roll_length' => 100]);
    }

    public function test_rework_cannot_be_created_twice_from_same_weaving_output(): void
    {
        $service = $this->manufacturingService();

        $output = $this->completedWeavingOutput();
        $service->createRework((int) $output->id, ['quantity' => 2, 'unit' => 'mtr']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rework already recorded for this weaving output.');

        $service->createRework((int) $output->id, ['quantity' => 2, 'unit' => 'mtr']);
    }

    public function test_distinct_sources_can_each_create_their_own_downstream_documents(): void
    {
        $service = $this->manufacturingService();

        $first = $this->approvedWarpPlan('Independent Allocation Lot A', 500, 100);
        $second = $this->approvedWarpPlan('Independent Allocation Lot B', 500, 100);

        $service->createYarnAllocation((int) $first->id);
        $service->createYarnAllocation((int) $second->id);

        $this->assertSame(2, TextileWorkflowDocument::query()
            ->where('document_type', 'yarn_allocation')
            ->count());
    }

    // ── Helpers ──

    private function manufacturingService(): TextileManufacturingService
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Consumed Source Manufacturing Branch', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        return app(TextileManufacturingService::class);
    }

    private function yarnLot(string $lotReference, float $availableQuantity): TextileLot
    {
        // Simulate an incoming-QC pass (approved) so the warp-plan gate accepts the lot.
        $incomingQc = TextileWorkflowDocument::create([
            'document_type' => 'incoming_qc',
            'document_number' => 'IQC-' . strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $lotReference), -6)),
            'lot_reference' => $lotReference,
            'quantity' => $availableQuantity,
            'unit' => 'kg',
            'status' => 'approved',
            'creator_id' => creatorId(),
            'created_by' => creatorId(),
            'branch_id' => session('active_branch_id'),
        ]);

        return TextileLot::create([
            'lot_reference' => $lotReference,
            'received_quantity' => $availableQuantity,
            'available_quantity' => $availableQuantity,
            'status' => 'active',
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'source_document_type' => 'incoming_qc',
            'source_document_id' => $incomingQc->id,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => creatorId(),
        ]);
    }

    private function approvedWarpPlan(string $lotReference, float $availableQuantity, float $planQuantity): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $yarnLot = $this->yarnLot($lotReference, $availableQuantity);

        $warpPlan = $service->createWarpPlan([
            'source_reference_type' => 'textile_lot',
            'source_reference_id' => $yarnLot->id,
            'source_action' => 'warp_plan',
            'party_name' => 'Warp Unit Test',
            'lot_reference' => $lotReference,
            'quantity' => $planQuantity,
            'unit' => 'kg',
        ]);
        $service->approveWarpPlan((int) $warpPlan->id);

        return $warpPlan->refresh();
    }

    private function completedYarnAllocation(string $lotReference): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $warpPlan = $this->approvedWarpPlan($lotReference, 500, 100);
        $allocation = $service->createYarnAllocation((int) $warpPlan->id);

        return $allocation->refresh();
    }

    private function completedWarpSheet(string $lotReference): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $yarnAllocation = $this->completedYarnAllocation($lotReference);
        $warpSheet = $service->createWarpSheet((int) $yarnAllocation->id);

        return $warpSheet->refresh();
    }

    private function completedWarpProduction(string $lotReference): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $warpSheet = $this->completedWarpSheet($lotReference);
        $warpProduction = $service->createWarpProduction((int) $warpSheet->id);

        return $warpProduction->refresh();
    }

    private function approvedBeam(): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $beam = $service->createBeam([
            'source_reference_type' => 'sales_order',
            'source_reference_id' => 8001,
            'source_action' => 'beam_prepare',
            'party_name' => 'Mill Unit A',
            'lot_reference' => 'LOT-M-CONSUMED',
            'quantity' => 250,
            'unit' => 'mtr',
        ]);
        $service->approveBeam((int) $beam->id);

        return $beam->refresh();
    }

    private function createdBeamIssue(): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $beam = $this->approvedBeam();
        $beamIssue = $service->createBeamIssue((int) $beam->id);

        return $beamIssue->refresh();
    }

    private function loomMaster(): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);

        return $service->createLoomMaster([
            'source_reference_type' => 'factory',
            'source_reference_id' => 9001,
            'source_action' => 'loom_register',
            'party_name' => 'Loom-Consumed',
            'lot_reference' => 'Rapier',
            'quantity' => 540,
            'unit' => 'rpm',
        ]);
    }

    private function releasedBatch(): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $beam = $this->approvedBeam();
        $batch = $service->createProductionBatch((int) $beam->id);
        $service->releaseProductionBatch((int) $batch->id);

        return $batch->refresh();
    }

    private function completedWeavingOutput(): TextileWorkflowDocument
    {
        $service = app(TextileManufacturingService::class);
        $batch = $this->releasedBatch();
        $output = $service->createWeavingOutput((int) $batch->id);

        return $output->refresh();
    }

    private function enableTextileModule(): User
    {
        AddOn::firstOrCreate(
            ['module' => 'TextileCore'],
            ['name' => 'Textile Core', 'package_name' => 'textile-core', 'is_enable' => true, 'monthly_price' => 0, 'yearly_price' => 0]
        );

        $company = $this->company();

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        return $company;
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Manufacturing Guard Plan',
            'modules' => ['TextileCore', 'Account'],
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

        return $company;
    }
}
