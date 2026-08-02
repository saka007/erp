<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use RuntimeException;

class TextileManufacturingService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function createBeam(array $payload): TextileWorkflowDocument
    {
        if (empty($payload['source_reference_type']) || empty($payload['source_reference_id'])) {
            throw new RuntimeException('Beam requires an upstream source reference.');
        }

        return $this->workflowService->createDocument([
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
    }

    public function createWarpPlan(array $payload): TextileWorkflowDocument
    {
        if (empty($payload['source_reference_type']) || empty($payload['source_reference_id'])) {
            throw new RuntimeException('Warp plan requires an upstream source reference.');
        }

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

        return $this->workflowService->createDocument([
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
    }

    public function createWarpSheet(int $yarnAllocationId, array $payload = []): TextileWorkflowDocument
    {
        $yarnAllocation = $this->findTenantDocument($yarnAllocationId, 'yarn_allocation');
        if (! in_array($yarnAllocation->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Yarn allocation must be completed before creating warp sheet.');
        }

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

    public function createBeamIssue(int $beamId, array $payload = []): TextileWorkflowDocument
    {
        $beam = $this->findTenantDocument($beamId, 'beam');
        if (! in_array($beam->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Beam must be approved before issuing.');
        }

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

    public function createLoomMaster(array $payload): TextileWorkflowDocument
    {
        if (empty($payload['source_reference_type']) || empty($payload['source_reference_id'])) {
            throw new RuntimeException('Loom master requires a source reference.');
        }

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
            'metadata' => $payload['metadata'] ?? null,
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

    public function createWeavingOutput(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'production_batch');
        if ($batch->status !== 'released') {
            throw new RuntimeException('Production batch must be released before weaving output.');
        }

        return $this->workflowService->createDocument([
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
    }

    public function createWaste(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'production_batch');
        if ($batch->status !== 'released') {
            throw new RuntimeException('Production batch must be released before recording waste.');
        }

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

        $document = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('document_type', $documentType)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->first();

        if ($document === null) {
            throw new RuntimeException('Document not found for tenant context.');
        }

        return $document;
    }
}
