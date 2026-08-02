<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use RuntimeException;

class TextileQualityService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function createInspection(array $payload): TextileWorkflowDocument
    {
        return $this->workflowService->createDocument([
            'document_type' => 'inspection',
            'source_reference_type' => $payload['source_reference_type'] ?? null,
            'source_reference_id' => $payload['source_reference_id'] ?? null,
            'source_action' => $payload['source_action'] ?? null,
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function finalizeInspection(int $inspectionId, string $decision): TextileWorkflowDocument
    {
        $inspection = $this->findTenantDocument($inspectionId, 'inspection');

        if (!in_array($decision, ['pass', 'fail'], true)) {
            throw new RuntimeException('Inspection decision must be pass or fail.');
        }

        return $this->workflowService->transitionStatus($inspection->id, $decision === 'pass' ? 'approved' : 'rejected');
    }

    public function holdLot(string $lotReference, string $reason): TextileWorkflowDocument
    {
        $lot = $this->findTenantLot($lotReference);
        $lot->status = 'hold';
        $lot->save();

        return $this->workflowService->createDocument([
            'document_type' => 'hold_release',
            'source_reference_type' => 'textile_lot',
            'source_reference_id' => $lot->id,
            'source_action' => 'hold',
            'lot_reference' => $lotReference,
            'quantity' => 0,
            'status' => 'approved',
            'metadata' => ['reason' => $reason],
        ]);
    }

    public function releaseLot(string $lotReference, string $reason): TextileWorkflowDocument
    {
        $lot = $this->findTenantLot($lotReference);
        $lot->status = 'active';
        $lot->save();

        return $this->workflowService->createDocument([
            'document_type' => 'hold_release',
            'source_reference_type' => 'textile_lot',
            'source_reference_id' => $lot->id,
            'source_action' => 'release',
            'lot_reference' => $lotReference,
            'quantity' => 0,
            'status' => 'approved',
            'metadata' => ['reason' => $reason],
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

    protected function findTenantLot(string $lotReference): TextileLot
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $lot = TextileLot::query()
            ->where('lot_reference', $lotReference)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->first();

        if ($lot === null) {
            throw new RuntimeException('Lot not found for tenant context.');
        }

        return $lot;
    }
}
