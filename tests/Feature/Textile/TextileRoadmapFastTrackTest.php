<?php

namespace Tests\Feature\Textile;

use DigitalFuzed\TextileCore\Models\TextileQualityProfile;
use DigitalFuzed\TextileCore\Models\TextileRouteRecipe;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileCore\Services\TextileAuditService;
use DigitalFuzed\TextileCore\Services\TextileCommercialBoundaryService;
use DigitalFuzed\TextileCore\Services\TextileManufacturingService;
use DigitalFuzed\TextileCore\Services\TextileNumberingService;
use DigitalFuzed\TextileCore\Services\TextileProcurementService;
use DigitalFuzed\TextileCore\Services\TextileSalesService;
use DigitalFuzed\TextileCore\Services\TextileWorkflowService;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use DigitalFuzed\TextileInventory\Services\TextileAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TextileRoadmapFastTrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_quality_profile_route_recipe_and_unit_conversion(): void
    {
        $profile = TextileQualityProfile::create([
            'name' => 'A-Grade Cotton',
            'code' => 'Q-A',
            'grade' => 'A',
            'parameters' => 'shrinkage<3%',
        ]);

        $recipe = TextileRouteRecipe::create([
            'name' => 'Cotton Finish Route',
            'code' => 'RR-001',
            'steps' => ['bleach', 'dye', 'finish'],
        ]);

        $conversion = TextileUnitConversion::create([
            'from_unit' => 'kg',
            'to_unit' => 'meter',
            'factor' => 2.500000,
        ]);

        $this->assertDatabaseHas('textile_quality_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('textile_route_recipes', ['id' => $recipe->id]);
        $this->assertDatabaseHas('textile_unit_conversions', ['id' => $conversion->id]);
    }

    public function test_it_can_generate_numbers_and_audit_entries(): void
    {
        $numbering = new TextileNumberingService();
        $audit = new TextileAuditService();

        $first = $numbering->next('grn');
        $second = $numbering->next('grn');

        $this->assertSame('GRN-000001', $first);
        $this->assertSame('GRN-000002', $second);

        $auditLog = $audit->record('textile.test', ['ok' => true]);

        $this->assertDatabaseHas('textile_audit_logs', ['id' => $auditLog->id, 'event_type' => 'textile.test']);
    }

    public function test_it_can_create_workflow_documents_for_business_flows(): void
    {
        $service = app(TextileWorkflowService::class);

        $types = [
            'purchase_requisition',
            'purchase_order',
            'grn',
            'incoming_qc',
            'sales_order',
            'dispatch',
            'challan',
            'pod',
            'beam',
            'production_batch',
            'weaving_output',
            'waste',
            'rework',
            'inspection',
            'hold_release',
            'job_work',
            'costing_entry',
        ];

        foreach ($types as $index => $type) {
            $service->createDocument([
                'document_type' => $type,
                'party_name' => 'Party '.$index,
                'quantity' => 10 + $index,
                'unit' => 'kg',
            ]);
        }

        foreach ($types as $type) {
            $this->assertDatabaseHas('textile_workflow_documents', ['document_type' => $type]);
        }

        $summary = $service->summary();
        $this->assertSame(count($types), $summary['total_documents']);
    }

    public function test_workflow_is_idempotent_by_key_and_source_reference(): void
    {
        $service = app(TextileWorkflowService::class);

        $first = $service->createDocument([
            'document_type' => 'sales_order',
            'idempotency_key' => 'idem-sales-1',
            'source_reference_type' => 'quotation',
            'source_reference_id' => 101,
            'source_action' => 'convert',
            'quantity' => 12,
        ]);

        $second = $service->createDocument([
            'document_type' => 'sales_order',
            'idempotency_key' => 'idem-sales-1',
            'source_reference_type' => 'quotation',
            'source_reference_id' => 101,
            'source_action' => 'convert',
            'quantity' => 99,
        ]);

        $third = $service->createDocument([
            'document_type' => 'sales_order',
            'source_reference_type' => 'quotation',
            'source_reference_id' => 101,
            'source_action' => 'convert',
            'quantity' => 50,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertDatabaseCount('textile_workflow_documents', 1);
    }

    public function test_workflow_status_transition_rules_are_enforced(): void
    {
        $service = app(TextileWorkflowService::class);

        $document = $service->createDocument([
            'document_type' => 'dispatch',
            'quantity' => 5,
        ]);

        $service->transitionStatus($document->id, 'approved');
        $service->transitionStatus($document->id, 'released');
        $closed = $service->transitionStatus($document->id, 'closed');

        $this->assertSame('closed', $closed->status);

        $this->expectException(RuntimeException::class);
        $service->transitionStatus($document->id, 'approved');
    }

    public function test_legacy_sales_proposal_source_is_blocked_without_canonical_mapping(): void
    {
        $service = app(TextileWorkflowService::class);

        $this->expectException(RuntimeException::class);
        $service->createDocument([
            'document_type' => 'sales_order',
            'source_reference_type' => 'sales_proposal',
            'source_reference_id' => 1001,
            'source_action' => 'convert',
            'quantity' => 3,
        ]);
    }

    public function test_legacy_sales_proposal_can_flow_after_mapping_to_canonical_quotation(): void
    {
        $mapping = app(TextileCommercialBoundaryService::class);
        $service = app(TextileWorkflowService::class);

        $mapping->registerCanonical('sales_proposal', 2001, 'sales_quotation', 5001);

        $document = $service->createDocument([
            'document_type' => 'sales_order',
            'source_reference_type' => 'sales_proposal',
            'source_reference_id' => 2001,
            'source_action' => 'convert',
            'quantity' => 6,
        ]);

        $this->assertSame('sales_quotation', $document->source_reference_type);
        $this->assertSame(5001, (int) $document->source_reference_id);
    }

    public function test_it_can_reserve_and_calculate_availability(): void
    {
        TextileLot::create([
            'lot_reference' => 'LOT-500',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
        ]);

        $availabilityService = new TextileAvailabilityService();
        $availabilityService->reserve('LOT-500', 25, 'sales_order', 11);

        $availability = $availabilityService->getAvailability('LOT-500');

        $this->assertSame(75.0, (float) $availability['available_quantity']);
        $this->assertSame(25.0, (float) $availability['reserved_quantity']);
        $this->assertDatabaseHas('textile_reservations', ['lot_reference' => 'LOT-500']);
    }

    public function test_it_blocks_over_reservation(): void
    {
        TextileLot::create([
            'lot_reference' => 'LOT-501',
            'received_quantity' => 50,
            'available_quantity' => 10,
            'status' => 'active',
        ]);

        $availabilityService = new TextileAvailabilityService();

        $this->expectException(RuntimeException::class);
        $availabilityService->reserve('LOT-501', 20, 'sales_order', 99);
    }

    public function test_procurement_flow_enforces_requisition_po_grn_incoming_qc_sequence(): void
    {
        $service = app(TextileProcurementService::class);

        $requisition = $service->createRequisition([
            'party_name' => 'Apex Yarn Mills',
            'lot_reference' => 'LOT-PRQ-100',
            'quantity' => 120,
            'unit' => 'kg',
        ]);

        $this->expectException(RuntimeException::class);
        $service->createPurchaseOrder($requisition->id);
    }

    public function test_procurement_flow_can_post_inventory_on_incoming_qc_pass(): void
    {
        $service = app(TextileProcurementService::class);

        $requisition = $service->createRequisition([
            'party_name' => 'Apex Yarn Mills',
            'lot_reference' => 'LOT-PRQ-200',
            'quantity' => 50,
            'unit' => 'kg',
        ]);

        $service->approveRequisition($requisition->id);
        $purchaseOrder = $service->createPurchaseOrder($requisition->id);
        $service->approvePurchaseOrder($purchaseOrder->id);
        $grn = $service->createGrn($purchaseOrder->id);
        $service->releaseGrn($grn->id);
        $incomingQc = $service->createIncomingQc($grn->id);

        $qcResult = $service->finalizeIncomingQc($incomingQc->id, 'pass');

        $this->assertSame('approved', $qcResult->status);
        $this->assertDatabaseHas('textile_lots', [
            'lot_reference' => 'LOT-PRQ-200',
            'received_quantity' => 50,
            'available_quantity' => 50,
        ]);

        $this->assertDatabaseHas('textile_movements', [
            'movement_type' => 'receipt',
            'reference_type' => 'incoming_qc',
            'reference_id' => $incomingQc->id,
            'lot_reference' => 'LOT-PRQ-200',
            'quantity' => 50,
        ]);
    }

    public function test_procurement_flow_does_not_post_inventory_on_incoming_qc_fail(): void
    {
        $service = app(TextileProcurementService::class);

        $requisition = $service->createRequisition([
            'party_name' => 'Nexa Fibers',
            'lot_reference' => 'LOT-PRQ-300',
            'quantity' => 30,
            'unit' => 'kg',
        ]);

        $service->approveRequisition($requisition->id);
        $purchaseOrder = $service->createPurchaseOrder($requisition->id);
        $service->approvePurchaseOrder($purchaseOrder->id);
        $grn = $service->createGrn($purchaseOrder->id);
        $service->releaseGrn($grn->id);
        $incomingQc = $service->createIncomingQc($grn->id);

        $qcResult = $service->finalizeIncomingQc($incomingQc->id, 'fail');

        $this->assertSame('rejected', $qcResult->status);
        $this->assertDatabaseMissing('textile_lots', ['lot_reference' => 'LOT-PRQ-300']);
        $this->assertSame(0, TextileMovement::query()->where('lot_reference', 'LOT-PRQ-300')->count());
    }

    public function test_sales_flow_requires_approved_order_before_allocation(): void
    {
        $service = app(TextileSalesService::class);

        $salesOrder = $service->createSalesOrder([
            'source_reference_type' => 'sales_quotation',
            'source_reference_id' => 7001,
            'party_name' => 'Urban Looms',
            'quantity' => 200,
            'unit' => 'meter',
        ]);

        $this->expectException(RuntimeException::class);
        $service->createAllocation($salesOrder->id);
    }

    public function test_sales_flow_creates_dispatch_challan_and_pod_with_invoice_ready_flag(): void
    {
        $service = app(TextileSalesService::class);

        $salesOrder = $service->createSalesOrder([
            'source_reference_type' => 'sales_quotation',
            'source_reference_id' => 7002,
            'party_name' => 'Metro Textiles',
            'lot_reference' => 'LOT-SALES-200',
            'quantity' => 120,
            'unit' => 'meter',
        ]);

        $service->approveSalesOrder($salesOrder->id);
        $allocation = $service->createAllocation($salesOrder->id);
        $service->releaseAllocation($allocation->id);
        $dispatch = $service->createDispatch($allocation->id);
        $service->releaseDispatch($dispatch->id);
        $challan = $service->createChallan($dispatch->id);
        $pod = $service->markPod($challan->id);

        $this->assertSame('pod', $pod->document_type);
        $this->assertSame('approved', $pod->status);
        $this->assertTrue((bool) ($pod->metadata['invoice_ready'] ?? false));

        $this->assertDatabaseHas('textile_workflow_documents', [
            'id' => $challan->id,
            'document_type' => 'challan',
            'status' => 'closed',
        ]);
    }

    public function test_sales_flow_maps_legacy_sales_proposal_to_canonical_quotation(): void
    {
        $mapping = app(TextileCommercialBoundaryService::class);
        $service = app(TextileSalesService::class);

        $mapping->registerCanonical('sales_proposal', 3001, 'sales_quotation', 9001);

        $salesOrder = $service->createSalesOrder([
            'source_reference_type' => 'sales_proposal',
            'source_reference_id' => 3001,
            'party_name' => 'Aster Fabrics',
            'quantity' => 40,
            'unit' => 'meter',
        ]);

        $this->assertSame('sales_quotation', $salesOrder->source_reference_type);
        $this->assertSame(9001, (int) $salesOrder->source_reference_id);
    }

    public function test_manufacturing_flow_requires_approved_beam_before_batch(): void
    {
        $service = app(TextileManufacturingService::class);

        $beam = $service->createBeam([
            'source_reference_type' => 'sales_order',
            'source_reference_id' => 8001,
            'lot_reference' => 'LOT-MFG-100',
            'quantity' => 300,
            'unit' => 'meter',
        ]);

        $this->expectException(RuntimeException::class);
        $service->createProductionBatch($beam->id);
    }

    public function test_manufacturing_flow_creates_weaving_output_waste_and_rework(): void
    {
        $service = app(TextileManufacturingService::class);

        $beam = $service->createBeam([
            'source_reference_type' => 'sales_order',
            'source_reference_id' => 8002,
            'party_name' => 'Mill Unit A',
            'lot_reference' => 'LOT-MFG-200',
            'quantity' => 250,
            'unit' => 'meter',
        ]);

        $service->approveBeam($beam->id);
        $batch = $service->createProductionBatch($beam->id);
        $service->releaseProductionBatch($batch->id);

        $output = $service->createWeavingOutput($batch->id, [
            'quantity' => 240,
            'unit' => 'meter',
        ]);

        $waste = $service->createWaste($batch->id, [
            'quantity' => 8,
            'unit' => 'meter',
        ]);

        $rework = $service->createRework($output->id, [
            'quantity' => 2,
            'unit' => 'meter',
        ]);

        $this->assertSame('approved', $output->status);
        $this->assertSame('approved', $waste->status);
        $this->assertSame('approved', $rework->status);

        $this->assertDatabaseHas('textile_workflow_documents', [
            'id' => $output->id,
            'document_type' => 'weaving_output',
            'source_reference_id' => $batch->id,
        ]);

        $this->assertDatabaseHas('textile_workflow_documents', [
            'id' => $waste->id,
            'document_type' => 'waste',
            'source_reference_id' => $batch->id,
        ]);

        $this->assertDatabaseHas('textile_workflow_documents', [
            'id' => $rework->id,
            'document_type' => 'rework',
            'source_reference_id' => $output->id,
        ]);
    }
}
