<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileInventory\Services\TextileLotAutoCreationService;
use RuntimeException;

class TextileProcessingService
{
    public function __construct(
        protected TextileWorkflowService $workflowService,
        protected TextileLotAutoCreationService $lotAutoCreationService
    ) {
    }

    public function createJobWorkOutward(array $payload): TextileWorkflowDocument
    {
        return $this->workflowService->createDocument([
            'document_type' => 'job_work_outward',
            'source_reference_type' => $payload['source_reference_type'] ?? null,
            'source_reference_id' => $payload['source_reference_id'] ?? null,
            'source_action' => $payload['source_action'] ?? 'job_work_issue',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function releaseJobWorkOutward(int $outwardId): TextileWorkflowDocument
    {
        $outward = $this->findTenantDocument($outwardId, 'job_work_outward');

        if ($outward->status === 'draft') {
            $outward = $this->workflowService->transitionStatus($outward->id, 'approved');
        }

        if ($outward->status !== 'approved') {
            throw new RuntimeException('Only draft or approved outward can be released.');
        }

        return $this->workflowService->transitionStatus($outward->id, 'released');
    }

    public function createProcessingBatch(int $outwardId, array $payload = []): TextileWorkflowDocument
    {
        $outward = $this->findTenantDocument($outwardId, 'job_work_outward');
        if ($outward->status !== 'released') {
            throw new RuntimeException('Job-work outward must be released before processing batch creation.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'processing_batch',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $outward->id,
            'source_action' => 'processing_start',
            'party_name' => $payload['party_name'] ?? $outward->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $outward->lot_reference,
            'quantity' => $payload['quantity'] ?? $outward->quantity,
            'unit' => $payload['unit'] ?? $outward->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function releaseProcessingBatch(int $batchId): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'processing_batch');

        if ($batch->status === 'draft') {
            $batch = $this->workflowService->transitionStatus($batch->id, 'approved');
        }

        if ($batch->status !== 'approved') {
            throw new RuntimeException('Only draft or approved processing batch can be released.');
        }

        return $this->workflowService->transitionStatus($batch->id, 'released');
    }

    public function createJobWorkInward(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'processing_batch');
        if ($batch->status !== 'released') {
            throw new RuntimeException('Processing batch must be released before inward receipt.');
        }

        $inward = $this->workflowService->createDocument([
            'document_type' => 'job_work_inward',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'source_action' => 'job_work_receive',
            'party_name' => $payload['party_name'] ?? $batch->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $batch->lot_reference,
            'quantity' => $payload['quantity'] ?? $batch->quantity,
            'unit' => $payload['unit'] ?? $batch->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        // Auto-create finished fabric lot
        $this->lotAutoCreationService->createFromJobWorkInward($inward);

        return $inward;
    }

    public function finalizeJobWorkInward(int $inwardId, string $decision): TextileWorkflowDocument
    {
        $inward = $this->findTenantDocument($inwardId, 'job_work_inward');

        if (!in_array($decision, ['pass', 'fail'], true)) {
            throw new RuntimeException('Inward decision must be pass or fail.');
        }

        return $this->workflowService->transitionStatus($inward->id, $decision === 'pass' ? 'approved' : 'rejected');
    }

    public function reconcileJobWork(int $outwardId, int $inwardId, ?string $notes = null): TextileWorkflowDocument
    {
        $outward = $this->findTenantDocument($outwardId, 'job_work_outward');
        $inward = $this->findTenantDocument($inwardId, 'job_work_inward');

        if ($outward->status !== 'released') {
            throw new RuntimeException('Outward must be released before reconciliation.');
        }

        if ($inward->status !== 'approved') {
            throw new RuntimeException('Inward must be approved before reconciliation.');
        }

        if ((float) $inward->quantity > (float) $outward->quantity) {
            throw new RuntimeException('Inward quantity cannot exceed outward quantity.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'job_work_reconciliation',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $inward->id,
            'source_action' => 'job_work_reconcile',
            'party_name' => $inward->party_name,
            'lot_reference' => $inward->lot_reference,
            'quantity' => $inward->quantity,
            'unit' => $inward->unit,
            'status' => 'approved',
            'metadata' => [
                'outward_id' => $outward->id,
                'inward_id' => $inward->id,
                'notes' => $notes,
                'balance_quantity' => max(0, (float) $outward->quantity - (float) $inward->quantity),
            ],
        ]);
    }

    public function createInternalProcessing(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        return $this->createStageDocument($batchId, 'internal_processing', $payload, 'internal_processing');
    }

    public function createDyeing(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        return $this->createStageDocument($batchId, 'dyeing_process', $payload, 'dyeing');
    }

    public function createPrinting(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        return $this->createStageDocument($batchId, 'printing_process', $payload, 'printing');
    }

    public function createBleaching(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        return $this->createStageDocument($batchId, 'bleaching_process', $payload, 'bleaching');
    }

    public function createCalendaring(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        return $this->createStageDocument($batchId, 'calendaring_process', $payload, 'calendaring');
    }

    public function createCompacting(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        return $this->createStageDocument($batchId, 'compacting_process', $payload, 'compacting');
    }

    public function createFinishing(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        return $this->createStageDocument($batchId, 'finishing_process', $payload, 'finishing');
    }

    public function createShadeCard(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'processing_batch');
        if (!in_array($batch->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Processing batch must be completed before shade card creation.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'shade_card',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'source_action' => 'shade_card',
            'party_name' => $payload['party_name'] ?? $batch->party_name,
            'lot_reference' => $payload['shade_code'] ?? $batch->lot_reference,
            'quantity' => $payload['quantity'] ?? $batch->quantity,
            'unit' => $payload['unit'] ?? $batch->unit,
            'status' => 'approved',
            'metadata' => [
                'shade_code' => $payload['shade_code'] ?? null,
                'shade_family' => $payload['shade_family'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createProcessCost(int $batchId, array $payload = []): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'processing_batch');
        if (!in_array($batch->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Processing batch must be completed before process cost entry.');
        }

        $quantity = (float) ($payload['quantity'] ?? $batch->quantity ?? 0);
        $costAmount = (float) ($payload['cost_amount'] ?? 0);
        $costPerUnit = $quantity > 0 ? round($costAmount / $quantity, 4) : null;

        return $this->workflowService->createDocument([
            'document_type' => 'process_cost',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'source_action' => 'process_cost',
            'party_name' => $payload['party_name'] ?? $batch->party_name,
            'lot_reference' => $batch->lot_reference,
            'quantity' => $quantity,
            'unit' => $payload['unit'] ?? $batch->unit,
            'status' => 'approved',
            'metadata' => [
                'process_stage' => $payload['process_stage'] ?? null,
                'cost_amount' => $costAmount,
                'cost_per_unit' => $costPerUnit,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    private function createStageDocument(int $batchId, string $documentType, array $payload, string $stage): TextileWorkflowDocument
    {
        $batch = $this->findTenantDocument($batchId, 'processing_batch');
        if (!in_array($batch->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Processing batch must be completed before stage entry.');
        }

        return $this->workflowService->createDocument([
            'document_type' => $documentType,
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $batch->id,
            'source_action' => 'processing_stage',
            'party_name' => $payload['party_name'] ?? $batch->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $batch->lot_reference,
            'quantity' => $payload['quantity'] ?? $batch->quantity,
            'unit' => $payload['unit'] ?? $batch->unit,
            'status' => 'approved',
            'metadata' => [
                'process_stage' => $stage,
                'recipe_code' => $payload['recipe_code'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    protected function findTenantDocument(int $documentId, string $documentType): TextileWorkflowDocument
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $query = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('document_type', $documentType)
            ->when($tenantId !== null, fn ($query) => $query->where('created_by', $tenantId));

        TextileBranchScope::applyWorkflowScope($query);
        $document = $query->first();

        if ($document === null) {
            throw new RuntimeException('Document not found for tenant context.');
        }

        return $document;
    }
}
