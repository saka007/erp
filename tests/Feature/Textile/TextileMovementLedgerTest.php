<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Hrm\Models\Branch;

/**
 * Phase 3C — Slice B: Automatic movement ledger.
 *
 * The ledger is the single source of truth for the manufacturing pipeline:
 *
 *   Yarn PO (receipt) → Yarn Issued to Sizing (issue) → Beam Received (receipt)
 *   → Beam Issued to Manufacturing → Takha Produced (receipt) → Takha Sold (issue)
 *
 * Verifies:
 * - Beam receipt posts a receipt movement for the beam lot (in-house sizing).
 * - Outsourced sizing posts vendor-aware movements (location = sizing-vendor).
 * - Takha receipt posts a receipt movement for the takha lot.
 * - Dispatch release posts an issue movement for the sold lot.
 * - Movements inherit the branch of the source document (branch-scoped ledger).
 */
class TextileMovementLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_beam_receipt_posts_movement_with_branch(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Ledger Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        TextileLot::create([
            'lot_reference' => 'YARN-LEDGER-1',
            'received_quantity' => 200,
            'available_quantity' => 200,
            'status' => 'active',
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => TextileLot::query()->where('lot_reference', 'YARN-LEDGER-1')->value('id'),
                'source_action' => 'beam_prepare',
                'party_name' => 'In-House Sizing',
                'lot_reference' => 'BEAM-LEDGER-1',
                'quantity' => 160,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->latest('id')
            ->first();

        $this->assertNotNull($beam);

        // Yarn issue movement (in-house: warehouse → sizing).
        $issueMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'issue')
            ->where('lot_reference', 'YARN-LEDGER-1')
            ->where('reference_type', 'beam')
            ->where('reference_id', $beam->id)
            ->first();

        $this->assertNotNull($issueMovement);
        $this->assertSame('sizing', $issueMovement->location_to);
        $this->assertEquals($branchA->id, (int) $issueMovement->branch_id);

        // Beam receipt movement (sizing → warehouse).
        $receiptMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'receipt')
            ->where('lot_reference', 'BEAM-LEDGER-1')
            ->where('reference_type', 'beam')
            ->where('reference_id', $beam->id)
            ->first();

        $this->assertNotNull($receiptMovement);
        $this->assertSame('sizing', $receiptMovement->location_from);
        $this->assertSame('warehouse', $receiptMovement->location_to);
        $this->assertEquals(160, (float) $receiptMovement->quantity);
        $this->assertEquals($branchA->id, (int) $receiptMovement->branch_id);
    }

    public function test_outsourced_sizing_posts_vendor_aware_movements(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Vendor Sizing Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        // Incoming-QC pass so the warp-plan gate accepts the lot.
        $incomingQc = TextileWorkflowDocument::create([
            'document_type' => 'incoming_qc',
            'document_number' => 'IQC-LEDGER-V',
            'lot_reference' => 'YARN-LEDGER-V',
            'quantity' => 300,
            'unit' => 'kg',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'YARN-LEDGER-V',
            'received_quantity' => 300,
            'available_quantity' => 300,
            'status' => 'active',
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'source_document_type' => 'incoming_qc',
            'source_document_id' => $incomingQc->id,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Warp plan → approve → yarn allocation to the sizing vendor.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => TextileLot::query()->where('lot_reference', 'YARN-LEDGER-V')->value('id'),
                'source_action' => 'warp_plan',
                'party_name' => 'Vendor Sizing Co',
                'lot_reference' => 'YARN-LEDGER-V',
                'quantity' => 200,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();

        $warpPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'warp_plan')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.approve'), [
                'warp_plan_id' => $warpPlan->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.yarn-allocations.store'), [
                'warp_plan_id' => $warpPlan->id,
                'party_name' => 'Vendor Sizing Co',
            ])
            ->assertSessionHasNoErrors();

        $yarnAllocation = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'yarn_allocation')
            ->latest('id')
            ->first();

        $this->assertNotNull($yarnAllocation);
        $this->assertSame('Vendor Sizing Co', $yarnAllocation->party_name);

        // Beam received from the sizing vendor (outsourced path).
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.from-yarn-allocation'), [
                'yarn_allocation_id' => $yarnAllocation->id,
                'quantity' => 180,
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->latest('id')
            ->first();

        $this->assertNotNull($beam);
        $this->assertSame('Vendor Sizing Co', $beam->metadata['sizing_vendor'] ?? null);

        // Yarn issued to the sizing vendor (not in-house sizing).
        $issueMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'issue')
            ->where('lot_reference', 'YARN-LEDGER-V')
            ->where('reference_id', $beam->id)
            ->first();

        $this->assertNotNull($issueMovement);
        $this->assertSame('sizing-vendor', $issueMovement->location_to);

        // Beam receipt from the sizing vendor.
        $receiptMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'receipt')
            ->where('lot_reference', $beam->lot_reference)
            ->where('reference_id', $beam->id)
            ->first();

        $this->assertNotNull($receiptMovement);
        $this->assertSame('sizing-vendor', $receiptMovement->location_from);
        $this->assertEquals($branchA->id, (int) $receiptMovement->branch_id);
    }

    public function test_takha_receipt_posts_movement_for_takha_lot(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Takha Ledger Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        TextileLot::create([
            'lot_reference' => 'YARN-LEDGER-T',
            'received_quantity' => 200,
            'available_quantity' => 200,
            'status' => 'active',
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => TextileLot::query()->where('lot_reference', 'YARN-LEDGER-T')->value('id'),
                'source_action' => 'beam_prepare',
                'party_name' => 'In-House Sizing',
                'lot_reference' => 'BEAM-LEDGER-T',
                'quantity' => 180,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.approve'), ['beam_id' => $beam->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.store'), ['beam_id' => $beam->id])
            ->assertSessionHasNoErrors();

        $batch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_batch')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.release'), ['batch_id' => $batch->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.weaving-output.store'), [
                'batch_id' => $batch->id,
                'quantity' => 160,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $output = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'weaving_output')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'weaving_output_id' => $output->id,
                'takha_number' => 'TAKHA-LEDGER-01',
                'quantity' => 70,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $takha = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'takha_entry')
            ->latest('id')
            ->first();

        $this->assertNotNull($takha);

        // Takha receipt movement (loom-floor → warehouse).
        $takhaReceipt = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'receipt')
            ->where('lot_reference', 'TAKHA-LEDGER-01')
            ->where('reference_type', 'takha_entry')
            ->where('reference_id', $takha->id)
            ->first();

        $this->assertNotNull($takhaReceipt);
        $this->assertSame('loom-floor', $takhaReceipt->location_from);
        $this->assertSame('warehouse', $takhaReceipt->location_to);
        $this->assertEquals(70, (float) $takhaReceipt->quantity);
        $this->assertEquals($branchA->id, (int) $takhaReceipt->branch_id);
    }

    public function test_dispatch_release_posts_issue_movement(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Dispatch Ledger Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        $customer = Customer::create([
            'company_name' => 'Ledger Buyer',
            'contact_person_name' => 'Buyer',
            'contact_person_email' => 'buyer@ledger.test',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $takhaSource = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-LEDGER-S',
            'lot_reference' => 'TAKHA-LEDGER-S-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'TAKHA-LEDGER-S-1',
            'received_quantity' => 120,
            'available_quantity' => 120,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $takhaSource->id,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        // Approved inspection so the sales-order gate accepts the lot.
        TextileWorkflowDocument::create([
            'document_type' => 'inspection',
            'document_number' => 'INSP-LEDGER-S',
            'lot_reference' => 'TAKHA-LEDGER-S-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => ['qc_stage' => 'final_qc', 'inspection_result' => 'pass', 'final_decision' => 'pass'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.sales.orders.store'), [
                'customer_id' => $customer->id,
                'lot_selections' => [
                    ['lot_reference' => 'TAKHA-LEDGER-S-1', 'quantity' => 100],
                ],
                'rate' => 80,
                'required_delivery_date' => now()->addDays(7)->toDateString(),
                'warehouse' => 'Main Warehouse',
            ])
            ->assertSessionHasNoErrors();

        $salesOrder = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'sales_order')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.sales.orders.approve'), ['sales_order_id' => $salesOrder->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.sales.allocations.store'), ['sales_order_id' => $salesOrder->id])
            ->assertSessionHasNoErrors();

        $allocation = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'allocation')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.sales.allocations.release'), ['allocation_id' => $allocation->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.sales.dispatches.store'), ['allocation_id' => $allocation->id])
            ->assertSessionHasNoErrors();

        $dispatch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'dispatch')
            ->latest('id')
            ->first();

        $this->assertNotNull($dispatch);

        $this->actingAs($companyA)
            ->post(route('textile.sales.dispatches.release'), ['dispatch_id' => $dispatch->id])
            ->assertSessionHasNoErrors();

        // Dispatch issue movement (warehouse → customer).
        $issueMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'issue')
            ->where('lot_reference', 'TAKHA-LEDGER-S-1')
            ->where('reference_type', 'dispatch')
            ->where('reference_id', $dispatch->id)
            ->first();

        $this->assertNotNull($issueMovement);
        $this->assertSame('warehouse', $issueMovement->location_from);
        $this->assertSame('customer', $issueMovement->location_to);
        $this->assertEquals($branchA->id, (int) $issueMovement->branch_id);
    }

    public function test_inspection_pass_posts_qc_transfer_movement(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'QC Ledger Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        // Seed a takha lot + pending inspection document.
        $takhaSource = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-LEDGER-QC',
            'lot_reference' => 'TAKHA-LEDGER-QC-1',
            'quantity' => 90,
            'unit' => 'mtr',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'TAKHA-LEDGER-QC-1',
            'received_quantity' => 90,
            'available_quantity' => 90,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $takhaSource->id,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $inspection = TextileWorkflowDocument::create([
            'document_type' => 'inspection',
            'document_number' => 'INSP-LEDGER-QC',
            'lot_reference' => 'TAKHA-LEDGER-QC-1',
            'quantity' => 90,
            'unit' => 'mtr',
            'status' => 'draft',
            'metadata' => ['qc_stage' => 'final_qc', 'inspection_result' => 'pass'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.finalize'), [
                'inspection_id' => $inspection->id,
                'decision' => 'pass',
            ])
            ->assertSessionHasNoErrors();

        // Inspection pass transfer movement (weaving → quality-approved).
        $qcMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'transfer')
            ->where('lot_reference', 'TAKHA-LEDGER-QC-1')
            ->where('reference_type', 'inspection')
            ->where('reference_id', $inspection->id)
            ->first();

        $this->assertNotNull($qcMovement);
        $this->assertSame('quality-approved', $qcMovement->location_to);
        $this->assertEquals($branchA->id, (int) $qcMovement->branch_id);

        // Inspection fail should NOT post a movement.
        $inspectionFail = TextileWorkflowDocument::create([
            'document_type' => 'inspection',
            'document_number' => 'INSP-LEDGER-FAIL',
            'lot_reference' => 'TAKHA-LEDGER-QC-1',
            'quantity' => 90,
            'unit' => 'mtr',
            'status' => 'draft',
            'metadata' => ['qc_stage' => 'final_qc', 'inspection_result' => 'fail'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.finalize'), [
                'inspection_id' => $inspectionFail->id,
                'decision' => 'fail',
            ])
            ->assertSessionHasNoErrors();

        $failMovementCount = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('reference_id', $inspectionFail->id)
            ->count();

        $this->assertSame(0, $failMovementCount);
    }

    public function test_per_takha_qc_granularity_one_takha_passes_another_rejected(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Takha QC Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        // One weaving output grey lot that produced TWO takha lots.
        $output = TextileWorkflowDocument::create([
            'document_type' => 'weaving_output',
            'document_number' => 'WO-TAKHA-QC',
            'lot_reference' => 'GREY-TAKHA-QC',
            'quantity' => 200,
            'unit' => 'mtr',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        $greyLot = TextileLot::create([
            'lot_reference' => 'GREY-TAKHA-QC',
            'received_quantity' => 200,
            'available_quantity' => 200,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'weaving_output',
            'source_document_id' => $output->id,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        // Takha 1 — good piece.
        $takha1 = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-QC-1',
            'lot_reference' => 'TAKHA-QC-1',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $output->id,
            'quantity' => 100,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => ['takha_number' => 'TAKHA-QC-1'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'TAKHA-QC-1',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $takha1->id,
            'parent_lot_reference' => $greyLot->lot_reference,
            'parent_lot_type' => TextileLot::TYPE_GREY_FABRIC,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        // Takha 2 — defective piece.
        $takha2 = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-QC-2',
            'lot_reference' => 'TAKHA-QC-2',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $output->id,
            'quantity' => 100,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => ['takha_number' => 'TAKHA-QC-2'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'TAKHA-QC-2',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $takha2->id,
            'parent_lot_reference' => $greyLot->lot_reference,
            'parent_lot_type' => TextileLot::TYPE_GREY_FABRIC,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        // Inspect takha 1 → pass.
        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.store'), [
                'qc_stage' => 'final_qc',
                'inspection_result' => 'pass',
                'lot_reference' => 'TAKHA-QC-1',
                'quantity' => 100,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $inspection1 = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'inspection')
            ->where('lot_reference', 'TAKHA-QC-1')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.finalize'), [
                'inspection_id' => $inspection1->id,
                'decision' => 'pass',
            ])
            ->assertSessionHasNoErrors();

        // Inspect takha 2 → fail.
        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.store'), [
                'qc_stage' => 'final_qc',
                'inspection_result' => 'fail',
                'lot_reference' => 'TAKHA-QC-2',
                'quantity' => 100,
                'unit' => 'mtr',
                'defects' => ['shade_variance'],
            ])
            ->assertSessionHasNoErrors();

        $inspection2 = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'inspection')
            ->where('lot_reference', 'TAKHA-QC-2')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.quality.inspections.finalize'), [
                'inspection_id' => $inspection2->id,
                'decision' => 'fail',
            ])
            ->assertSessionHasNoErrors();

        // Ledger: only the passed takha got a QC transfer movement.
        $passedMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('reference_type', 'inspection')
            ->where('reference_id', $inspection1->id)
            ->first();
        $this->assertNotNull($passedMovement);
        $this->assertSame('quality-approved', $passedMovement->location_to);

        $failedMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('reference_type', 'inspection')
            ->where('reference_id', $inspection2->id)
            ->first();
        $this->assertNull($failedMovement);

        // The passed takha remains sellable; the rejected one must be blocked at the sales gate.
        $approvedForTakha1 = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'inspection')
            ->where('lot_reference', 'TAKHA-QC-1')
            ->whereIn('status', ['approved', 'released'])
            ->exists();
        $this->assertTrue($approvedForTakha1);

        $approvedForTakha2 = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'inspection')
            ->where('lot_reference', 'TAKHA-QC-2')
            ->whereIn('status', ['approved', 'released'])
            ->exists();
        $this->assertFalse($approvedForTakha2);
    }

    public function test_ledger_posting_is_fail_open_for_missing_quantity(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'FailOpen Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        // A dispatch document with no lot reference / quantity should not throw.
        $dispatch = TextileWorkflowDocument::create([
            'document_type' => 'dispatch',
            'document_number' => 'DISPATCH-EMPTY',
            'lot_reference' => '',
            'quantity' => 0,
            'unit' => 'mtr',
            'status' => 'released',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        $service = app(\DigitalFuzed\TextileInventory\Services\TextileLedgerService::class);

        // Should return null (skip), not throw.
        $this->assertNull($service->postDispatchIssue($dispatch, 'mtr'));
        $this->assertSame(0, TextileMovement::query()->where('reference_id', $dispatch->id)->count());
    }

    private function company(): User
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $plan = Plan::create([
            'name' => 'Textile Movement Ledger Plan',
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
