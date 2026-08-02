<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use RuntimeException;

class TextileProcessingService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
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

        return $this->workflowService->createDocument([
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

    protected function findTenantDocument(int $documentId, string $documentType): TextileWorkflowDocument
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $document = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('document_type', $documentType)
            ->when($tenantId !== null, fn ($query) => $query->where('created_by', $tenantId))
            ->first();

        if ($document === null) {
            throw new RuntimeException('Document not found for tenant context.');
        }

        return $document;
    }
}
