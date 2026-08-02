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
