<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Services\TextileConsumptionService;
use DigitalFuzed\TextileInventory\Services\TextileLedgerService;
use DigitalFuzed\TextileInventory\Services\TextileLotAutoCreationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TextileManufacturingService
{
    public function __construct(
        protected TextileWorkflowService $workflowService,
        protected TextileLotAutoCreationService $lotAutoCreationService,
        protected TextileOperatingPolicyService $policyService,
        protected TextileConsumptionService $consumptionService,
        protected TextileLedgerService $ledgerService,
        protected TextileNumberingService $numberingService
    ) {
    }

    private function tenantHasCapability(string $capability): bool
    {
        try {
            $this->policyService->assertCapability($capability);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function createBeam(array $payload): TextileWorkflowDocument
    {
        if (empty($payload['source_reference_type']) || empty($payload['source_reference_id'])) {
            throw new RuntimeException('Beam requires an upstream source reference.');
        }

        $beam = $this->workflowService->createDocument([
            'document_type' => 'beam',
            'source_reference_type' => $payload['source_reference_type'],
            'source_reference_id' => (int) $payload['source_reference_id'],
            'source_action' => $payload['source_action'] ?? 'beam_prepare',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        // Auto-create beam lot, tracing back to the source yarn lot when present.
        $sourceYarnLot = $this->resolveSourceYarnLot(
            (string) ($payload['source_reference_type'] ?? ''),
            (int) ($payload['source_reference_id'] ?? 0),
            $beam->created_by,
        );

        $this->lotAutoCreationService->createFromBeam(
            $beam,
            $sourceYarnLot?->lot_reference,
            TextileLot::TYPE_YARN,
        );

        // Consume the issued yarn from stock (fail-open).
        if ($sourceYarnLot !== null) {
            $this->consumptionService->issueYarnForBeam(
                $sourceYarnLot->lot_reference,
                (float) ($beam->quantity ?? 0),
                (string) ($beam->unit ?? 'kg'),
                'beam',
                $beam->id,
                $beam->created_by,
            );
        }

        // Ledger: beam received from sizing.
        $this->ledgerService->postBeamReceipt($beam, (string) ($beam->unit ?? ''));

        return $beam;
    }

    /**
     * Resolve a yarn lot by its source reference (textile_lot / inventory_lot).
     */
    private function resolveSourceYarnLot(string $sourceType, int $sourceId, ?int $tenantId): ?TextileLot
    {
        if (! in_array($sourceType, ['textile_lot', 'inventory_lot'], true) || $sourceId <= 0) {
            return null;
        }

        $lot = TextileLot::query()
            ->where('created_by', $tenantId)
            ->where('id', $sourceId)
            ->first();

        if ($lot !== null && (string) ($lot->material_type ?? '') === TextileLot::TYPE_YARN) {
            return $lot;
        }

        return null;
    }

    public function createBeamFromYarnAllocation(int $yarnAllocationId, array $payload = []): TextileWorkflowDocument
    {
        $yarnAllocation = $this->findTenantDocument($yarnAllocationId, 'yarn_allocation');
        if (! in_array($yarnAllocation->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Yarn issue must be completed before receiving a beam.');
        }

        $existingBeamQuery = TextileWorkflowDocument::query()
            ->where('created_by', $yarnAllocation->created_by)
            ->where('document_type', 'beam')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $yarnAllocation->id);
        TextileBranchScope::applyWorkflowScope($existingBeamQuery);

        if ($existingBeamQuery->exists()) {
            throw new RuntimeException('A beam has already been received for this yarn issue.');
        }

        $quantity = (float) ($payload['quantity'] ?? $yarnAllocation->quantity);
        if ($quantity <= 0 || $quantity > (float) $yarnAllocation->quantity) {
            throw new RuntimeException('Received beam quantity must not exceed the issued yarn quantity.');
        }

        $beam = $this->workflowService->createDocument([
            'document_type' => 'beam',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $yarnAllocation->id,
            'source_action' => 'vendor_beam_receipt',
            'party_name' => $yarnAllocation->party_name,
            'lot_reference' => sprintf('BEAM-%06d', $yarnAllocation->id),
            'quantity' => $quantity,
            'unit' => $yarnAllocation->unit,
            'status' => 'draft',
            'metadata' => [
                'yarn_allocation_number' => $yarnAllocation->document_number,
                'source_yarn_lot' => $yarnAllocation->lot_reference,
                'issued_quantity' => $yarnAllocation->quantity,
                'sizing_vendor' => $yarnAllocation->party_name,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        // Auto-create beam lot, tracing back to the source yarn lot.
        $sourceYarnLot = (string) ($yarnAllocation->lot_reference ?? '');
        $this->lotAutoCreationService->createFromBeam($beam, $sourceYarnLot !== '' ? $sourceYarnLot : null, TextileLot::TYPE_YARN);

        // Consume the issued yarn from stock (fail-open). The yarn was reserved
        // at allocation time, so the reservation is fulfilled — not double-decremented.
        // Sizing is outsourced here (vendor beam receipt), so the yarn is issued
        // to the sizing vendor rather than an in-house sizing unit.
        $this->consumptionService->issueYarnForBeam(
            $sourceYarnLot,
            $quantity,
            (string) ($yarnAllocation->unit ?? 'kg'),
            'beam',
            $beam->id,
            $yarnAllocation->created_by,
            'yarn_allocation',
            $yarnAllocation->id,
            'sizing-vendor',
        );

        // Ledger: beam received from sizing vendor (or in-house sizing).
        $this->ledgerService->postBeamReceipt($beam, (string) ($beam->unit ?? ''));

        return $beam;
    }

    public function createWarpPlan(array $payload): TextileWorkflowDocument
    {
        if (empty($payload['source_reference_type']) || empty($payload['source_reference_id'])) {
            throw new RuntimeException('Warp plan requires an upstream source reference.');
        }

        $this->assertYarnLotPassedIncomingQc($payload);

        return $this->workflowService->createDocument([
            'document_type' => 'warp_plan',
            'source_reference_type' => $payload['source_reference_type'],
            'source_reference_id' => (int) $payload['source_reference_id'],
            'source_action' => $payload['source_action'] ?? 'warp_plan',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    /**
     * Gate: yarn lots must pass incoming QC before being consumed in warp planning.
     * Skipped when the source is not a resolvable yarn lot or the tenant does not
     * operate incoming QC (fail-open for tenants without that capability).
     */
    private function assertYarnLotPassedIncomingQc(array $payload): void
    {
        $sourceType = $payload['source_reference_type'] ?? null;
        if (! in_array($sourceType, ['textile_lot', 'inventory_lot'], true)) {
            return;
        }

        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();
        $lot = TextileLot::query()
            ->where('created_by', $tenantId)
            ->where('lot_reference', $payload['lot_reference'] ?? null)
            ->where('material_type', TextileLot::TYPE_YARN)
            ->first();

        // Source lot is not a resolvable yarn lot (e.g. external reference) — nothing to gate against.
        if (! $lot) {
            return;
        }

        if (! ($this->tenantHasCapability('procurement_incoming_qc'))) {
            return;
        }

        if ($lot->source_document_type !== 'incoming_qc' || ! $lot->source_document_id) {
            throw new RuntimeException('Yarn lot must pass incoming QC before warp planning.');
        }

        $sourceQuery = TextileWorkflowDocument::query()
            ->where('id', $lot->source_document_id)
            ->where('created_by', $tenantId)
            ->where('document_type', 'incoming_qc');
        TextileBranchScope::applyWorkflowScope($sourceQuery);
        $source = $sourceQuery->first();

        if (! $source || ! in_array($source->status, ['approved', 'released'], true)) {
            throw new RuntimeException('Yarn lot must pass incoming QC before warp planning.');
        }
    }

    public function approveWarpPlan(int $warpPlanId): TextileWorkflowDocument
    {
        $warpPlan = $this->findTenantDocument($warpPlanId, 'warp_plan');

        return $this->workflowService->transitionStatus($warpPlan->id, 'approved');
    }

    public function createYarnAllocation(int $warpPlanId, array $payload = []): TextileWorkflowDocument
    {
        $warpPlan = $this->findTenantDocument($warpPlanId, 'warp_plan');
        if ($warpPlan->status !== 'approved') {
            throw new RuntimeException('Warp plan must be approved before yarn allocation.');
        }

        $this->assertNoDownstreamDocument($warpPlan, ['yarn_allocation'], 'Yarn already allocated for this warp plan.');

        // Take care of inventory: never allocate more yarn than the source lot
        // has available. The reserve call below is fail-open, so gate up front.
        $sourceYarnLot = $this->resolveWarpPlanYarnLot($warpPlan);
        $allocationQuantity = (float) ($payload['quantity'] ?? $warpPlan->quantity ?? 0);
        if ($sourceYarnLot !== null && $allocationQuantity > 0 && (float) $sourceYarnLot->available_quantity < $allocationQuantity) {
            throw new RuntimeException(sprintf(
                'Insufficient yarn stock for this warp plan (%.2f %s available, %.2f required).',
                (float) $sourceYarnLot->available_quantity,
                (string) ($sourceYarnLot->unit ?? $warpPlan->unit ?? 'kg'),
                $allocationQuantity
            ));
        }

        $allocation = $this->workflowService->createDocument([
            'document_type' => 'yarn_allocation',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $warpPlan->id,
            'source_action' => 'yarn_allocate',
            'party_name' => $payload['party_name'] ?? $warpPlan->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $warpPlan->lot_reference,
            'quantity' => $payload['quantity'] ?? $warpPlan->quantity,
            'unit' => $payload['unit'] ?? $warpPlan->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        // Reserve yarn from the source lot committed by this allocation (fail-open).
        $sourceYarnLot = $this->resolveWarpPlanYarnLot($warpPlan);
        if ($sourceYarnLot !== null) {
            $this->consumptionService->reserveYarnForAllocation(
                $sourceYarnLot->lot_reference,
                (float) ($allocation->quantity ?? 0),
                'yarn_allocation',
                $allocation->id,
                $warpPlan->created_by,
            );
        }

        return $allocation;
    }

    /**
     * Resolve the yarn lot a warp plan draws from. The warp plan's source is
     * either an inventory/textile lot directly, or its lot_reference maps to a
     * yarn lot created by the incoming-QC pass.
     */
    private function resolveWarpPlanYarnLot(TextileWorkflowDocument $warpPlan): ?TextileLot
    {
        $tenantId = $warpPlan->created_by;
        $sourceType = (string) ($warpPlan->source_reference_type ?? '');
        $sourceId = (int) ($warpPlan->source_reference_id ?? 0);

        if (in_array($sourceType, ['textile_lot', 'inventory_lot'], true) && $sourceId > 0) {
            $lot = TextileLot::query()
                ->where('created_by', $tenantId)
                ->where('id', $sourceId)
                ->first();

            if ($lot !== null && (string) ($lot->material_type ?? '') === TextileLot::TYPE_YARN) {
                return $lot;
            }
        }

        // Fall back to resolving by lot_reference (matches GRN/QC-pass yarn lots).
        $reference = (string) ($warpPlan->lot_reference ?? '');
        if ($reference !== '') {
            $lot = TextileLot::query()
                ->where('created_by', $tenantId)
                ->where('lot_reference', $reference)
                ->first();

            if ($lot !== null && (string) ($lot->material_type ?? '') === TextileLot::TYPE_YARN) {
                return $lot;
            }
        }

        return null;
    }

    public function createWarpSheet(int $yarnAllocationId, array $payload = []): TextileWorkflowDocument
    {
        $yarnAllocation = $this->findTenantDocument($yarnAllocationId, 'yarn_allocation');
        if (! in_array($yarnAllocation->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Yarn allocation must be completed before creating warp sheet.');
        }

        $this->assertNoDownstreamDocument($yarnAllocation, ['warp_sheet'], 'Warp sheet already created for this yarn allocation.');

        return $this->workflowService->createDocument([
            'document_type' => 'warp_sheet',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $yarnAllocation->id,
            'source_action' => 'warp_sheet_prepare',
            'party_name' => $payload['party_name'] ?? $yarnAllocation->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $yarnAllocation->lot_reference,
            'quantity' => $payload['quantity'] ?? $yarnAllocation->quantity,
            'unit' => $payload['unit'] ?? $yarnAllocation->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createWarpProduction(int $warpSheetId, array $payload = []): TextileWorkflowDocument
    {
        $warpSheet = $this->findTenantDocument($warpSheetId, 'warp_sheet');
        if (! in_array($warpSheet->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Warp sheet must be completed before creating warp production.');
        }

        $this->assertNoDownstreamDocument($warpSheet, ['warp_production'], 'Warp production already recorded for this warp sheet.');

        return $this->workflowService->createDocument([
            'document_type' => 'warp_production',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $warpSheet->id,
            'source_action' => 'warp_production',
            'party_name' => $payload['party_name'] ?? $warpSheet->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $warpSheet->lot_reference,
            'quantity' => $payload['quantity'] ?? $warpSheet->quantity,
            'unit' => $payload['unit'] ?? $warpSheet->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createSizingRecipe(int $warpProductionId, array $payload = []): TextileWorkflowDocument
    {
        $warpProduction = $this->findTenantDocument($warpProductionId, 'warp_production');
        if (! in_array($warpProduction->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Warp production must be completed before creating sizing recipe.');
        }

        $this->assertNoDownstreamDocument($warpProduction, ['sizing_recipe'], 'Sizing recipe already created for this warp production.');

        return $this->workflowService->createDocument([
            'document_type' => 'sizing_recipe',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $warpProduction->id,
            'source_action' => 'sizing_recipe',
            'party_name' => $payload['party_name'] ?? $warpProduction->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $warpProduction->lot_reference,
            'quantity' => $payload['quantity'] ?? $warpProduction->quantity,
            'unit' => $payload['unit'] ?? $warpProduction->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createChemicalConsumption(int $sizingRecipeId, array $payload = []): TextileWorkflowDocument
    {
        $sizingRecipe = $this->findTenantDocument($sizingRecipeId, 'sizing_recipe');
        if (! in_array($sizingRecipe->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Sizing recipe must be completed before recording chemical consumption.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'chemical_consumption',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $sizingRecipe->id,
            'party_name' => $payload['party_name'] ?? $sizingRecipe->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $sizingRecipe->lot_reference,
            'quantity' => $payload['consumption_quantity'] ?? $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? $sizingRecipe->unit,
            'status' => 'approved',
            'metadata' => [
                'chemical_type' => $payload['chemical_type'] ?? null,
                'composition_percent' => $payload['composition_percent'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createBeamIssue(int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $beam = $this->findTenantDocument($beamId, 'beam');
        if (! in_array($beam->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Beam must be approved before issuing.');
        }

        $this->assertNoDownstreamDocument($beam, ['beam_issue'], 'Beam already issued.');

        return $this->workflowService->createDocument([
            'document_type' => 'beam_issue',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $beam->id,
            'source_action' => 'beam_issue',
            'party_name' => $payload['party_name'] ?? $beam->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $beam->lot_reference,
            'quantity' => $payload['quantity'] ?? $beam->quantity,
            'unit' => $payload['unit'] ?? $beam->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createBeamReturn(int $beamIssueId, array $payload = []): TextileWorkflowDocument
    {
        $beamIssue = $this->findTenantDocument($beamIssueId, 'beam_issue');
        if (! in_array($beamIssue->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Beam issue must be completed before beam return.');
        }

        $this->assertNoDownstreamDocument($beamIssue, ['beam_return'], 'Beam return already recorded for this beam issue.');

        return $this->workflowService->createDocument([
            'document_type' => 'beam_return',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $beamIssue->id,
            'source_action' => 'beam_return',
            'party_name' => $payload['party_name'] ?? $beamIssue->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $beamIssue->lot_reference,
            'quantity' => $payload['quantity'] ?? $beamIssue->quantity,
            'unit' => $payload['unit'] ?? $beamIssue->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createBeamInspection(int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $beam = $this->findTenantDocument($beamId, 'beam');
        if (! in_array($beam->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Beam must be completed before beam inspection.');
        }

        $this->assertNoDownstreamDocument($beam, ['beam_inspection'], 'Beam inspection already recorded for this beam.');

        return $this->workflowService->createDocument([
            'document_type' => 'beam_inspection',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $beam->id,
            'source_action' => 'beam_inspection',
            'party_name' => $payload['party_name'] ?? $beam->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $beam->lot_reference,
            'quantity' => $payload['quantity'] ?? $beam->quantity,
            'unit' => $payload['unit'] ?? $beam->unit,
            'status' => 'approved',
            'metadata' => [
                'inspection_result' => $payload['inspection_result'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createBeamCost(int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $beam = $this->findTenantDocument($beamId, 'beam');
        if (! in_array($beam->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Beam must be completed before beam cost capture.');
        }

        $this->assertNoDownstreamDocument($beam, ['beam_cost'], 'Beam cost already captured for this beam.');

        $quantity = (float) ($payload['quantity'] ?? $beam->quantity ?? 0);
        $costAmount = (float) ($payload['cost_amount'] ?? 0);
        $costPerUnit = $quantity > 0 ? round($costAmount / $quantity, 4) : null;

        return $this->workflowService->createDocument([
            'document_type' => 'beam_cost',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $beam->id,
            'source_action' => 'beam_cost_capture',
            'party_name' => $payload['party_name'] ?? $beam->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $beam->lot_reference,
            'quantity' => $quantity,
            'unit' => $payload['unit'] ?? $beam->unit,
            'status' => 'approved',
            'metadata' => [
                'cost_type' => $payload['cost_type'] ?? null,
                'cost_amount' => $costAmount,
                'cost_per_unit' => $costPerUnit,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createLoomMaster(array $payload): TextileWorkflowDocument
    {
        if (empty($payload['source_reference_type']) || empty($payload['source_reference_id'])) {
            throw new RuntimeException('Loom master requires a source reference.');
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $metadata['shed_type'] = $payload['shed_type'] ?? ($metadata['shed_type'] ?? null);
        $metadata['width'] = $payload['width'] ?? ($metadata['width'] ?? null);
        $metadata['loom_status'] = $payload['loom_status'] ?? ($metadata['loom_status'] ?? null);
        $metadata['running_hours'] = $payload['running_hours'] ?? ($metadata['running_hours'] ?? null);
        $metadata['idle_hours'] = $payload['idle_hours'] ?? ($metadata['idle_hours'] ?? null);
        $metadata['operator_name'] = $payload['operator_name'] ?? ($metadata['operator_name'] ?? null);

        return $this->workflowService->createDocument([
            'document_type' => 'loom_master',
            'source_reference_type' => $payload['source_reference_type'],
            'source_reference_id' => (int) $payload['source_reference_id'],
            'source_action' => $payload['source_action'] ?? 'loom_register',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? 'rpm',
            'status' => 'approved',
            'metadata' => $metadata,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createLoomBreakdown(int $loomMasterId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');

        if (! in_array($loomMaster->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Loom master must be completed before recording breakdown.');
        }

        $this->assertNoDownstreamDocument($loomMaster, ['loom_breakdown'], 'Breakdown already recorded for this loom.');

        return $this->workflowService->createDocument([
            'document_type' => 'loom_breakdown',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'source_action' => 'loom_breakdown',
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $loomMaster->lot_reference,
            'quantity' => $payload['downtime_hours'] ?? 0,
            'unit' => $payload['unit'] ?? 'hour',
            'status' => 'approved',
            'metadata' => [
                'breakdown_reason' => $payload['breakdown_reason'] ?? null,
                'downtime_hours' => $payload['downtime_hours'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'operator_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? null),
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createLoomMaintenance(int $loomMasterId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');

        if (! in_array($loomMaster->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Loom master must be completed before recording maintenance.');
        }

        $this->assertNoDownstreamDocument($loomMaster, ['loom_maintenance'], 'Maintenance already recorded for this loom.');

        return $this->workflowService->createDocument([
            'document_type' => 'loom_maintenance',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'source_action' => 'loom_maintenance',
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $loomMaster->lot_reference,
            'quantity' => $payload['maintenance_hours'] ?? 0,
            'unit' => $payload['unit'] ?? 'hour',
            'status' => 'approved',
            'metadata' => [
                'maintenance_type' => $payload['maintenance_type'] ?? null,
                'maintenance_hours' => $payload['maintenance_hours'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'operator_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? null),
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createMachinePlan(int $loomMasterId, int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');
        $beam = $this->findTenantDocument($beamId, 'beam');

        if (! in_array($loomMaster->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Loom master must be completed before machine planning.');
        }

        if ($beam->status !== 'approved') {
            throw new RuntimeException('Beam must be approved before machine planning.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'machine_plan',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $beam->document_number,
            'quantity' => $payload['planned_quantity'] ?? $beam->quantity,
            'unit' => $payload['unit'] ?? $beam->unit,
            'status' => 'approved',
            'metadata' => [
                'beam_id' => $beam->id,
                'beam_number' => $beam->document_number,
                'planned_date' => $payload['planned_date'] ?? null,
                'planned_shift' => $payload['planned_shift'] ?? null,
                'operator_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? null),
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createProductionCalendar(array $payload = []): TextileWorkflowDocument
    {
        return $this->workflowService->createDocument([
            'document_type' => 'production_calendar',
            'party_name' => null,
            'lot_reference' => null,
            'quantity' => 0,
            'unit' => null,
            'status' => 'approved',
            'metadata' => [
                'plan_date' => $payload['plan_date'] ?? null,
                'day_type' => $payload['day_type'] ?? null,
                'planned_shift' => $payload['planned_shift'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createCapacityPlan(int $loomMasterId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');

        return $this->workflowService->createDocument([
            'document_type' => 'capacity_plan',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $loomMaster->document_number,
            'quantity' => $payload['capacity_quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'approved',
            'metadata' => [
                'plan_date' => $payload['plan_date'] ?? null,
                'available_hours' => $payload['available_hours'] ?? null,
                'efficiency_target' => $payload['efficiency_target'] ?? null,
                'operator_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? null),
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createShiftPlan(int $loomMasterId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');

        return $this->workflowService->createDocument([
            'document_type' => 'shift_plan',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $loomMaster->document_number,
            'quantity' => $payload['expected_hours'] ?? 0,
            'unit' => $payload['unit'] ?? 'hour',
            'status' => 'approved',
            'metadata' => [
                'plan_date' => $payload['plan_date'] ?? null,
                'planned_shift' => $payload['planned_shift'] ?? null,
                'expected_hours' => $payload['expected_hours'] ?? null,
                'operator_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? null),
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createMaterialPlan(int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $beam = $this->findTenantDocument($beamId, 'beam');

        return $this->workflowService->createDocument([
            'document_type' => 'material_plan',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $beam->id,
            'party_name' => $beam->party_name,
            'lot_reference' => $beam->document_number,
            'quantity' => $payload['required_quantity'] ?? $beam->quantity,
            'unit' => $payload['unit'] ?? $beam->unit,
            'status' => 'approved',
            'metadata' => [
                'plan_date' => $payload['plan_date'] ?? null,
                'beam_number' => $beam->document_number,
                'required_quantity' => $payload['required_quantity'] ?? $beam->quantity,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createProductionSchedule(int $loomMasterId, int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');
        $beam = $this->findTenantDocument($beamId, 'beam');

        return $this->workflowService->createDocument([
            'document_type' => 'production_schedule',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $beam->document_number,
            'quantity' => $payload['scheduled_quantity'] ?? $beam->quantity,
            'unit' => $payload['unit'] ?? $beam->unit,
            'status' => 'approved',
            'metadata' => [
                'beam_id' => $beam->id,
                'beam_number' => $beam->document_number,
                'scheduled_date' => $payload['scheduled_date'] ?? null,
                'scheduled_shift' => $payload['scheduled_shift'] ?? null,
                'operator_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? null),
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createBeamFromSizingRecipe(int $sizingRecipeId, array $payload = []): TextileWorkflowDocument
    {
        $sizingRecipe = $this->findTenantDocument($sizingRecipeId, 'sizing_recipe');
        if (! in_array($sizingRecipe->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Sizing recipe must be completed before beam creation.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'beam',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $sizingRecipe->id,
            'source_action' => 'beam_prepare',
            'party_name' => $payload['party_name'] ?? $sizingRecipe->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $sizingRecipe->lot_reference,
            'quantity' => $payload['quantity'] ?? $sizingRecipe->quantity,
            'unit' => $payload['unit'] ?? $sizingRecipe->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function approveBeam(int $beamId): TextileWorkflowDocument
    {
        $beam = $this->findTenantDocument($beamId, 'beam');

        return $this->workflowService->transitionStatus($beam->id, 'approved');
    }

    public function createProductionBatch(int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $beam = $this->findTenantDocument($beamId, 'beam');
        if ($beam->status !== 'approved') {
            throw new RuntimeException('Beam must be approved before creating production batch.');
        }

        $this->assertNoDownstreamDocument($beam, ['production_batch'], 'Production batch already created for this beam.');

        return $this->workflowService->createDocument([
            'document_type' => 'production_batch',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $beam->id,
            'source_action' => 'batch_start',
            'party_name' => $payload['party_name'] ?? $beam->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $beam->lot_reference,
            'quantity' => $payload['quantity'] ?? $beam->quantity,
            'unit' => $payload['unit'] ?? $beam->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function releaseProductionBatch(int $batchId): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'production_batch');

        if ($batch->status === 'draft') {
            $batch = $this->workflowService->transitionStatus($batch->id, 'approved');
        }

        if ($batch->status !== 'approved') {
            throw new RuntimeException('Only draft or approved production batch can be released.');
        }

        return $this->workflowService->transitionStatus($batch->id, 'released');
    }

    public function createProductionAssignment(int $batchId, array $payload): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'production_batch');
        if ($batch->status !== 'released') {
            throw new RuntimeException('Production batch must be released before beam assignment.');
        }

        $existingQuery = TextileWorkflowDocument::query()
            ->where('created_by', $batch->created_by)
            ->where('document_type', 'production_assignment')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $batch->id);
        TextileBranchScope::applyWorkflowScope($existingQuery);

        // Partial allotment: a batch (beam) can be assigned across multiple
        // production runs. Each assignment must stay within the remaining
        // unassigned quantity so part of the beam can be kept in stock.
        $alreadyAssigned = (float) $existingQuery->sum('quantity');
        $remaining = (float) $batch->quantity - $alreadyAssigned;

        $quantity = (float) ($payload['assigned_quantity'] ?? $remaining);
        if ($quantity <= 0 || $quantity > $remaining + 0.0001) {
            throw new RuntimeException('Assigned quantity must not exceed the remaining production batch quantity.');
        }

        $productionMode = $payload['production_mode'] ?? null;
        $partyName = $payload['powerloom_vendor_name'] ?? null;
        $loomAllocations = [];

        if ($productionMode === 'own_unit') {
            foreach ($payload['loom_allocations'] ?? [] as $allocation) {
                $loomMaster = $this->findTenantDocument((int) ($allocation['loom_master_id'] ?? 0), 'loom_master');
                $loomAllocations[] = [
                    'loom_master_id' => $loomMaster->id,
                    'loom_number' => $loomMaster->document_number,
                    'loom_name' => $loomMaster->party_name,
                    'quantity' => (float) ($allocation['quantity'] ?? 0),
                ];
            }

            $allocatedQuantity = array_sum(array_column($loomAllocations, 'quantity'));
            if ($loomAllocations === [] || abs($allocatedQuantity - $quantity) > 0.01) {
                throw new RuntimeException('Own-loom allocation quantities must equal the assigned quantity.');
            }

            $partyName = count($loomAllocations) === 1
                ? $loomAllocations[0]['loom_name']
                : sprintf('%d branch looms', count($loomAllocations));
        }

        // Omit source_action (NULL) so multiple partial assignments are allowed
        // for the same batch — the workflow dedup + DB unique index only apply
        // when source_action is set. Remaining-quantity is enforced above.
        $assignment = $this->workflowService->createDocument([
            'document_type' => 'production_assignment',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'party_name' => $partyName,
            'lot_reference' => $batch->lot_reference,
            'quantity' => $quantity,
            'unit' => $batch->unit,
            'status' => 'approved',
            'metadata' => [
                'production_mode' => $productionMode,
                'batch_id' => $batch->id,
                'batch_number' => $batch->document_number,
                'loom_allocations' => $loomAllocations,
                'powerloom_vendor_id' => $payload['powerloom_vendor_id'] ?? null,
                'assignment_date' => $payload['assignment_date'] ?? null,
                'expected_completion_date' => $payload['expected_completion_date'] ?? null,
                'planned_shift' => $payload['planned_shift'] ?? null,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
        ]);

        // Commit the assigned beam quantity from inventory: reserve it from the
        // source beam lot so the remaining (unassigned) beam stays in stock and
        // is visible in inventory. Fail-open when no beam lot is present.
        $beamLotReference = $this->resolveBatchBeamLotReference($batch);
        if ($beamLotReference !== null) {
            $this->consumptionService->reserveBeamForAssignment(
                $beamLotReference,
                $quantity,
                'production_assignment',
                $assignment->id,
                $batch->created_by,
            );
        }

        return $assignment;
    }

    public function createTakhaFromAssignment(int $assignmentId, array $payload): TextileWorkflowDocument
    {
        $assignment = $this->findTenantDocument($assignmentId, 'production_assignment');
        if (! in_array($assignment->status, ['approved', 'released'], true)) {
            throw new RuntimeException('Production assignment must be active before recording Takha.');
        }

        $recordedQuery = TextileWorkflowDocument::query()
            ->where('created_by', $assignment->created_by)
            ->where('document_type', 'takha_entry')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $assignment->id);
        TextileBranchScope::applyWorkflowScope($recordedQuery);

        $quantity = (float) ($payload['quantity'] ?? 0);
        $recordedQuantity = (float) $recordedQuery->sum('quantity');
        if ($quantity <= 0 || ($recordedQuantity + $quantity) > (float) $assignment->quantity) {
            throw new RuntimeException('Takha quantity exceeds the remaining assigned quantity.');
        }

        $takhaNumber = trim((string) ($payload['takha_number'] ?? ''));
        if ($takhaNumber === '') {
            // Standard takha numbering: TAKHA-000001, TAKHA-000002, ...
            $takhaNumber = $this->numberingService->next('takha');
        }
        $duplicateNumberQuery = TextileWorkflowDocument::query()
            ->where('created_by', $assignment->created_by)
            ->where('document_type', 'takha_entry')
            ->where('lot_reference', $takhaNumber);
        TextileBranchScope::applyWorkflowScope($duplicateNumberQuery);
        if ($duplicateNumberQuery->exists()) {
            throw new RuntimeException('Takha number already exists in this branch.');
        }

        $metadata = is_array($assignment->metadata) ? $assignment->metadata : [];
        $loomMasterId = isset($payload['loom_master_id']) ? (int) $payload['loom_master_id'] : null;
        if (($metadata['production_mode'] ?? null) === 'own_unit') {
            $loomAllocations = collect($metadata['loom_allocations'] ?? []);
            $loomAllocation = $loomAllocations->firstWhere('loom_master_id', $loomMasterId);
            if (! is_array($loomAllocation)) {
                throw new RuntimeException('Select a loom allocated to this production assignment.');
            }

            $loomRecordedQuantity = (float) (clone $recordedQuery)
                ->where('metadata->loom_master_id', $loomMasterId)
                ->sum('quantity');
            if (($loomRecordedQuantity + $quantity) > (float) ($loomAllocation['quantity'] ?? 0)) {
                throw new RuntimeException('Takha quantity exceeds the selected loom allocation.');
            }
        }

        $takha = $this->workflowService->createDocument([
            'document_type' => 'takha_entry',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $assignment->id,
            'party_name' => $assignment->party_name,
            'lot_reference' => $takhaNumber,
            'quantity' => $quantity,
            'unit' => $payload['unit'] ?? $assignment->unit,
            'status' => 'approved',
            'metadata' => [
                'takha_number' => $takhaNumber,
                'production_mode' => $metadata['production_mode'] ?? null,
                'batch_id' => $metadata['batch_id'] ?? null,
                'batch_number' => $metadata['batch_number'] ?? null,
                'loom_master_id' => $loomMasterId,
                'powerloom_vendor_id' => $metadata['powerloom_vendor_id'] ?? null,
                'production_date' => $payload['production_date'] ?? null,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
        ]);

        // Takha grey lot traces back to the batch's weaving-output lot (fallback: beam lot).
        $batch = $this->findBatchByIdForTenant((int) ($metadata['batch_id'] ?? 0), $assignment->created_by);
        $parentReference = null;
        $parentType = null;

        if ($batch !== null) {
            $weavingOutputLot = $this->resolveBatchWeavingOutputLot($batch);
            if ($weavingOutputLot !== null) {
                $parentReference = $weavingOutputLot->lot_reference;
                $parentType = TextileLot::TYPE_GREY_FABRIC;
            } else {
                $beamReference = $this->resolveBatchBeamLotReference($batch);
                $parentReference = $beamReference ?? (string) $batch->lot_reference;
                $parentType = TextileLot::TYPE_BEAM;
            }
        } else {
            $parentReference = (string) $assignment->lot_reference;
            $parentType = TextileLot::TYPE_BEAM;
        }

        $this->lotAutoCreationService->createFromWeavingOutput($takha, $parentReference, $parentType);

        // Consume the parent lot that fed this takha (fail-open).
        $outsourced = ($metadata['production_mode'] ?? null) === 'powerloom_vendor';
        if ($parentReference !== null && $parentType === TextileLot::TYPE_GREY_FABRIC) {
            $this->consumptionService->issueGreyForTakha(
                $parentReference,
                $quantity,
                (string) ($takha->unit ?? 'kg'),
                'takha_entry',
                $takha->id,
                $assignment->created_by,
                $outsourced,
            );
        } elseif ($parentReference !== null && $parentType === TextileLot::TYPE_BEAM) {
            // Fulfill this assignment's beam reservation (the assigned quantity
            // was already reserved at assignment time) to avoid double consumption.
            $this->consumptionService->issueBeamForWeaving(
                $parentReference,
                $quantity,
                (string) ($takha->unit ?? 'kg'),
                'takha_entry',
                $takha->id,
                $assignment->created_by,
                $outsourced,
                [$assignment->id],
            );
        }

        // Ledger: takha received from weaving (vendor-aware).
        $this->ledgerService->postTakhaReceipt($takha, (string) ($takha->unit ?? ''));

        return $takha;
    }

    public function createTakhasFromAssignment(int $assignmentId, array $payload)
    {
        return DB::transaction(function () use ($assignmentId, $payload) {
            return collect($payload['takhas'] ?? [])->map(function (array $takha) use ($assignmentId, $payload) {
                return $this->createTakhaFromAssignment($assignmentId, array_merge($payload, $takha));
            });
        });
    }

    public function createWeavingOutput(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'production_batch');
        if ($batch->status !== 'released') {
            throw new RuntimeException('Production batch must be released before weaving output.');
        }

        $this->assertNoDownstreamDocument($batch, ['weaving_output'], 'Weaving output already recorded for this batch.');

        $output = $this->workflowService->createDocument([
            'document_type' => 'weaving_output',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'source_action' => 'weaving_complete',
            'party_name' => $payload['party_name'] ?? $batch->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $batch->lot_reference,
            'quantity' => $payload['quantity'] ?? $batch->quantity,
            'unit' => $payload['unit'] ?? $batch->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        // Auto-create grey fabric lot, tracing back to the source beam lot.
        $beamLotReference = $this->resolveBatchBeamLotReference($batch);
        $this->lotAutoCreationService->createFromWeavingOutput(
            $output,
            $beamLotReference !== null ? $beamLotReference : (string) $batch->lot_reference,
            TextileLot::TYPE_BEAM,
        );

        // Consume the beam lot that fed this weaving output (fail-open).
        // Outsourced when the batch is assigned to a powerloom vendor.
        // The batch's production-assignment reservations are fulfilled so the
        // beam is not double-consumed (assigned quantity was already reserved).
        if ($beamLotReference !== null) {
            $outsourced = $this->isBatchWeavingOutsourced($batch);
            $this->consumptionService->issueBeamForWeaving(
                $beamLotReference,
                (float) ($output->quantity ?? 0),
                (string) ($output->unit ?? 'kg'),
                'weaving_output',
                $output->id,
                $output->created_by,
                $outsourced,
                $this->batchAssignmentIds($batch),
            );

            // Ledger: grey fabric received from weaving output.
            $this->ledgerService->postWeavingOutputReceipt($output, (string) ($output->unit ?? ''), $outsourced);
        }

        return $output;
    }

    /**
     * Ids of all production assignments created for a batch.
     */
    private function batchAssignmentIds(TextileWorkflowDocument $batch): array
    {
        $query = TextileWorkflowDocument::query()
            ->where('created_by', $batch->created_by)
            ->where('document_type', 'production_assignment')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $batch->id);

        TextileBranchScope::applyWorkflowScope($query);

        return $query->pluck('id')->all();
    }

    /**
     * Resolve the beam lot reference a production batch was built from.
     */
    private function resolveBatchBeamLotReference(TextileWorkflowDocument $batch): ?string
    {
        $sourceType = (string) ($batch->source_reference_type ?? '');
        $sourceId = (int) ($batch->source_reference_id ?? 0);

        if ($sourceType === 'textile_workflow_document' && $sourceId > 0) {
            $beam = TextileWorkflowDocument::query()
                ->where('created_by', $batch->created_by)
                ->where('document_type', 'beam')
                ->where('id', $sourceId)
                ->first();

            if ($beam !== null) {
                return (string) ($beam->lot_reference ?? '');
            }
        }

        return null;
    }

    /**
     * Whether a batch's weaving is outsourced to a powerloom vendor.
     * The production mode lives on the production assignment created for the
     * batch (own_unit = in-house, powerloom_vendor = 3rd party).
     */
    private function isBatchWeavingOutsourced(TextileWorkflowDocument $batch): bool
    {
        $assignment = TextileWorkflowDocument::query()
            ->where('created_by', $batch->created_by)
            ->where('document_type', 'production_assignment')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $batch->id)
            ->latest('id')
            ->first();

        if ($assignment === null) {
            return false;
        }

        $metadata = is_array($assignment->metadata) ? $assignment->metadata : [];

        return ($metadata['production_mode'] ?? null) === 'powerloom_vendor';
    }

    /**
     * Find a production batch for a tenant (fail-open: null when not found).
     */
    private function findBatchByIdForTenant(int $batchId, ?int $tenantId): ?TextileWorkflowDocument
    {
        if ($batchId <= 0) {
            return null;
        }

        return TextileWorkflowDocument::query()
            ->where('created_by', $tenantId)
            ->where('document_type', 'production_batch')
            ->where('id', $batchId)
            ->first();
    }

    /**
     * Resolve the grey fabric lot auto-created for a batch's weaving output.
     */
    private function resolveBatchWeavingOutputLot(TextileWorkflowDocument $batch): ?TextileLot
    {
        $output = TextileWorkflowDocument::query()
            ->where('created_by', $batch->created_by)
            ->where('document_type', 'weaving_output')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $batch->id)
            ->latest('id')
            ->first();

        if ($output === null) {
            return null;
        }

        return TextileLot::query()
            ->where('created_by', $batch->created_by)
            ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
            ->where('source_document_type', 'weaving_output')
            ->where('source_document_id', $output->id)
            ->first();
    }

    /**
     * Resolve the grey fabric lot auto-created for a weaving output document.
     */
    private function resolveWeavingOutputGreyLot(TextileWorkflowDocument $output): ?TextileLot
    {
        return TextileLot::query()
            ->where('created_by', $output->created_by)
            ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
            ->where('source_document_type', 'weaving_output')
            ->where('source_document_id', $output->id)
            ->first();
    }

    public function createShiftProduction(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'production_batch');
        if ($batch->status !== 'released') {
            throw new RuntimeException('Production batch must be released before shift production entry.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'shift_production',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'party_name' => $payload['operator_name'] ?? $batch->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $batch->lot_reference,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? $batch->unit,
            'status' => 'approved',
            'metadata' => [
                'planned_shift' => $payload['planned_shift'] ?? null,
                'operator_name' => $payload['operator_name'] ?? null,
                'loom_master_id' => $payload['loom_master_id'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createTakhaEntry(int $weavingOutputId, array $payload = []): TextileWorkflowDocument
    {
        $output = $this->findTenantDocument($weavingOutputId, 'weaving_output');
        if (! in_array($output->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Weaving output must be completed before takha entry.');
        }

        $takhaNumber = trim((string) ($payload['takha_number'] ?? ''));
        if ($takhaNumber === '') {
            // Standard takha numbering: TAKHA-000001, TAKHA-000002, ...
            $takhaNumber = $this->numberingService->next('takha');
        }

        $quantity = (float) ($payload['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new RuntimeException('Takha quantity must be greater than zero.');
        }

        $recordedQuery = TextileWorkflowDocument::query()
            ->where('created_by', $output->created_by)
            ->where('document_type', 'takha_entry')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $output->id);
        TextileBranchScope::applyWorkflowScope($recordedQuery);

        $recordedQuantity = (float) $recordedQuery->sum('quantity');
        if (($recordedQuantity + $quantity) > (float) $output->quantity) {
            throw new RuntimeException('Takha quantity exceeds the remaining weaving output quantity.');
        }

        $duplicateNumberQuery = TextileWorkflowDocument::query()
            ->where('created_by', $output->created_by)
            ->where('document_type', 'takha_entry')
            ->where('lot_reference', $takhaNumber);
        TextileBranchScope::applyWorkflowScope($duplicateNumberQuery);
        if ($duplicateNumberQuery->exists()) {
            throw new RuntimeException('Takha number already exists in this branch.');
        }

        $takha = $this->workflowService->createDocument([
            'document_type' => 'takha_entry',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $output->id,
            'party_name' => $payload['operator_name'] ?? $output->party_name,
            'lot_reference' => $takhaNumber,
            'quantity' => $quantity,
            'unit' => $payload['unit'] ?? $output->unit,
            'status' => 'approved',
            'metadata' => [
                'takha_number' => $takhaNumber,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        // Takha grey lot traces back to the weaving output's grey lot.
        $parentLot = $this->resolveWeavingOutputGreyLot($output);
        $this->lotAutoCreationService->createFromWeavingOutput(
            $takha,
            $parentLot?->lot_reference,
            TextileLot::TYPE_GREY_FABRIC,
        );

        // Consume the parent grey lot that fed this takha (fail-open).
        if ($parentLot !== null) {
            $this->consumptionService->issueGreyForTakha(
                $parentLot->lot_reference,
                $quantity,
                (string) ($takha->unit ?? 'kg'),
                'takha_entry',
                $takha->id,
                $output->created_by,
            );
        }

        // Ledger: takha received from weaving output.
        $this->ledgerService->postTakhaReceipt($takha, (string) ($takha->unit ?? ''));

        return $takha;
    }

    public function createLoomEfficiency(int $loomMasterId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');
        $plannedQuantity = (float) ($payload['planned_quantity'] ?? 0);
        $actualQuantity = (float) ($payload['actual_quantity'] ?? 0);
        $runtimeHours = (float) ($payload['runtime_hours'] ?? 0);
        $downtimeHours = (float) ($payload['downtime_hours'] ?? 0);

        $efficiencyPercent = $plannedQuantity > 0
            ? round(($actualQuantity / $plannedQuantity) * 100, 2)
            : (($runtimeHours + $downtimeHours) > 0 ? round(($runtimeHours / ($runtimeHours + $downtimeHours)) * 100, 2) : null);

        return $this->workflowService->createDocument([
            'document_type' => 'loom_efficiency',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $loomMaster->document_number,
            'quantity' => $actualQuantity,
            'unit' => $payload['unit'] ?? 'mtr',
            'status' => 'approved',
            'metadata' => [
                'planned_shift' => $payload['planned_shift'] ?? null,
                'planned_quantity' => $plannedQuantity,
                'actual_quantity' => $actualQuantity,
                'runtime_hours' => $runtimeHours,
                'downtime_hours' => $downtimeHours,
                'efficiency_percent' => $efficiencyPercent,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createOperatorEfficiency(array $payload = []): TextileWorkflowDocument
    {
        $plannedQuantity = (float) ($payload['planned_quantity'] ?? 0);
        $actualQuantity = (float) ($payload['actual_quantity'] ?? 0);
        $efficiencyPercent = $plannedQuantity > 0 ? round(($actualQuantity / $plannedQuantity) * 100, 2) : null;

        return $this->workflowService->createDocument([
            'document_type' => 'operator_efficiency',
            'party_name' => $payload['operator_name'] ?? null,
            'lot_reference' => $payload['planned_shift'] ?? null,
            'quantity' => $actualQuantity,
            'unit' => $payload['unit'] ?? 'mtr',
            'status' => 'approved',
            'metadata' => [
                'planned_shift' => $payload['planned_shift'] ?? null,
                'planned_quantity' => $plannedQuantity,
                'actual_quantity' => $actualQuantity,
                'efficiency_percent' => $efficiencyPercent,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createMachineDowntime(int $loomMasterId, array $payload = []): TextileWorkflowDocument
    {
        $loomMaster = $this->findTenantDocument($loomMasterId, 'loom_master');

        return $this->workflowService->createDocument([
            'document_type' => 'machine_downtime',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $loomMaster->id,
            'party_name' => $payload['operator_name'] ?? ($loomMaster->metadata['operator_name'] ?? $loomMaster->party_name),
            'lot_reference' => $loomMaster->document_number,
            'quantity' => $payload['downtime_hours'] ?? 0,
            'unit' => $payload['unit'] ?? 'hour',
            'status' => 'approved',
            'metadata' => [
                'planned_shift' => $payload['planned_shift'] ?? null,
                'downtime_reason' => $payload['downtime_reason'] ?? null,
                'downtime_hours' => $payload['downtime_hours'] ?? null,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createProductionCost(int $weavingOutputId, array $payload = []): TextileWorkflowDocument
    {
        $output = $this->findTenantDocument($weavingOutputId, 'weaving_output');
        $quantity = (float) ($payload['quantity'] ?? $output->quantity ?? 0);
        $costAmount = (float) ($payload['cost_amount'] ?? 0);
        $costPerUnit = $quantity > 0 ? round($costAmount / $quantity, 4) : null;

        return $this->workflowService->createDocument([
            'document_type' => 'production_cost',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $output->id,
            'party_name' => $payload['operator_name'] ?? $output->party_name,
            'lot_reference' => $output->lot_reference,
            'quantity' => $quantity,
            'unit' => $payload['unit'] ?? $output->unit,
            'status' => 'approved',
            'metadata' => [
                'cost_center_id' => $payload['cost_center_id'] ?? null,
                'cost_amount' => $costAmount,
                'cost_per_unit' => $costPerUnit,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createGreyFabricRoll(int $weavingOutputId, array $payload = []): TextileWorkflowDocument
    {
        $output = $this->findTenantDocument($weavingOutputId, 'weaving_output');

        $this->assertNoDownstreamDocument($output, ['grey_fabric_roll'], 'Grey fabric roll already generated for this weaving output.');

        $rollNumber = trim((string) ($payload['roll_number'] ?? ''));
        if ($rollNumber === '') {
            $rollNumber = sprintf('ROLL-%s-%s', date('Ymd'), substr((string) uniqid('', true), -6));
        }

        $barcode = trim((string) ($payload['roll_barcode'] ?? ''));
        if ($barcode === '') {
            $barcode = sprintf('BAR-%s', $rollNumber);
        }

        $qrCode = trim((string) ($payload['roll_qr_code'] ?? ''));
        if ($qrCode === '') {
            $qrCode = sprintf('ROLL:%s|BAR:%s|LOT:%s', $rollNumber, $barcode, (string) ($output->lot_reference ?? '-'));
        }

        $roll = $this->workflowService->createDocument([
            'document_type' => 'grey_fabric_roll',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $output->id,
            'source_action' => 'roll_generate',
            'party_name' => $payload['party_name'] ?? $output->party_name,
            'lot_reference' => $rollNumber,
            'quantity' => $payload['roll_length'] ?? $output->quantity,
            'unit' => $payload['unit'] ?? $output->unit,
            'status' => 'approved',
            'metadata' => [
                'roll_number' => $rollNumber,
                'roll_barcode' => $barcode,
                'roll_qr_code' => $qrCode,
                'roll_weight' => $payload['roll_weight'] ?? null,
                'roll_length' => $payload['roll_length'] ?? null,
                'gsm' => $payload['gsm'] ?? null,
                'width' => $payload['width'] ?? null,
                'defects' => $this->normalizeDefects($payload['defects'] ?? []),
                'grade' => $payload['grade'] ?? null,
                'warehouse' => $payload['warehouse'] ?? null,
                'operator_name' => $payload['operator_name'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        $this->createGreyRollHistory($roll, 'created', [
            'notes' => $payload['notes'] ?? null,
        ]);

        return $roll;
    }

    public function updateGreyFabricRoll(int $rollId, array $payload = []): TextileWorkflowDocument
    {
        $roll = $this->findTenantDocument($rollId, 'grey_fabric_roll');

        $metadata = is_array($roll->metadata) ? $roll->metadata : [];

        $updates = [
            'roll_weight' => $payload['roll_weight'] ?? ($metadata['roll_weight'] ?? null),
            'roll_length' => $payload['roll_length'] ?? ($metadata['roll_length'] ?? null),
            'gsm' => $payload['gsm'] ?? ($metadata['gsm'] ?? null),
            'width' => $payload['width'] ?? ($metadata['width'] ?? null),
            'defects' => array_key_exists('defects', $payload) ? $this->normalizeDefects($payload['defects']) : ($metadata['defects'] ?? []),
            'grade' => $payload['grade'] ?? ($metadata['grade'] ?? null),
            'warehouse' => $payload['warehouse'] ?? ($metadata['warehouse'] ?? null),
            'operator_name' => $payload['operator_name'] ?? ($metadata['operator_name'] ?? null),
            'notes' => $payload['notes'] ?? ($metadata['notes'] ?? null),
        ];

        $roll->metadata = array_merge($metadata, $updates);
        $roll->save();

        $this->createGreyRollHistory($roll, 'updated', [
            'notes' => $payload['notes'] ?? null,
        ]);

        return $roll;
    }

    public function createWaste(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'production_batch');
        if ($batch->status !== 'released') {
            throw new RuntimeException('Production batch must be released before recording waste.');
        }

        $this->assertNoDownstreamDocument($batch, ['waste'], 'Waste already recorded for this batch.');

        return $this->workflowService->createDocument([
            'document_type' => 'waste',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'source_action' => 'record_waste',
            'party_name' => $payload['party_name'] ?? $batch->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $batch->lot_reference,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? $batch->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createRework(int $weavingOutputId, array $payload = []): TextileWorkflowDocument
    {
        $output = $this->findTenantDocument($weavingOutputId, 'weaving_output');
        if ($output->status !== 'approved' && $output->status !== 'released' && $output->status !== 'closed') {
            throw new RuntimeException('Weaving output must be completed before recording rework.');
        }

        $this->assertNoDownstreamDocument($output, ['rework'], 'Rework already recorded for this weaving output.');

        return $this->workflowService->createDocument([
            'document_type' => 'rework',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $output->id,
            'source_action' => 'record_rework',
            'party_name' => $payload['party_name'] ?? $output->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $output->lot_reference,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? $output->unit,
            'status' => 'approved',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    protected function findTenantDocument(int $documentId, string $documentType): TextileWorkflowDocument
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $query = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('document_type', $documentType)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId));

        TextileBranchScope::applyWorkflowScope($query);
        $document = $query->first();

        if ($document === null) {
            throw new RuntimeException('Document not found for tenant context.');
        }

        return $document;
    }

    /**
     * Guards a "create next step" flow against re-using a source document that
     * has already been converted downstream (e.g. a second yarn allocation from
     * the same warp plan). Mirrors the frontend disabled-option behaviour and
     * protects the API against direct/bypassed submissions.
     */
    protected function assertNoDownstreamDocument(TextileWorkflowDocument $source, array $documentTypes, string $message): void
    {
        $hasDownstream = TextileWorkflowDocument::query()
            ->where('created_by', $source->created_by)
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $source->id)
            ->whereIn('document_type', $documentTypes)
            ->exists();

        if ($hasDownstream) {
            throw new RuntimeException($message);
        }
    }

    private function createGreyRollHistory(TextileWorkflowDocument $roll, string $event, array $extra = []): TextileWorkflowDocument
    {
        $metadata = is_array($roll->metadata) ? $roll->metadata : [];

        return $this->workflowService->createDocument([
            'document_type' => 'grey_roll_history',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $roll->id,
            'source_action' => sprintf('history_%s_%s', $event, str_replace('.', '', (string) microtime(true))),
            'party_name' => $roll->party_name,
            'lot_reference' => (string) ($metadata['roll_number'] ?? $roll->lot_reference),
            'quantity' => $roll->quantity,
            'unit' => $roll->unit,
            'status' => 'approved',
            'metadata' => array_merge($metadata, [
                'history_event' => $event,
                'history_notes' => $extra['notes'] ?? null,
            ]),
        ]);
    }

    private function normalizeDefects(mixed $defects): array
    {
        if (!is_array($defects)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => is_string($value) ? trim($value) : '',
            $defects
        ))));
    }
}
