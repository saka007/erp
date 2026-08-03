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
        $metadata = [
            'qc_stage' => $payload['qc_stage'] ?? null,
            'inspection_result' => $payload['inspection_result'] ?? null,
            'shade_reference' => $payload['shade_reference'] ?? null,
            'defects' => array_values(array_filter($payload['defects'] ?? [], fn ($value) => is_string($value) && trim($value) !== '')),
            'notes' => $payload['notes'] ?? null,
        ];

        return $this->workflowService->createDocument([
            'document_type' => 'inspection',
            'source_reference_type' => $payload['source_reference_type'] ?? ($payload['qc_stage'] ?? null),
            'source_reference_id' => $payload['source_reference_id'] ?? null,
            'source_action' => $payload['source_action'] ?? 'inspection',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $metadata,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function finalizeInspection(int $inspectionId, string $decision): TextileWorkflowDocument
    {
        $inspection = $this->findTenantDocument($inspectionId, 'inspection');

        if (!in_array($decision, ['pass', 'fail', 'rework'], true)) {
            throw new RuntimeException('Inspection decision must be pass, fail, or rework.');
        }

        $inspection->metadata = array_merge($inspection->metadata ?? [], [
            'final_decision' => $decision,
        ]);
        $inspection->save();

        return $this->workflowService->transitionStatus($inspection->id, $decision === 'pass' ? 'approved' : 'rejected');
    }

    public function createCertificate(array $payload): TextileWorkflowDocument
    {
        if (!empty($payload['inspection_id'])) {
            $inspection = $this->findTenantDocument((int) $payload['inspection_id'], 'inspection');

            if (!in_array($inspection->status, ['approved', 'released'], true)) {
                throw new RuntimeException('Inspection must be approved before certificate creation.');
            }
        }

        return $this->workflowService->createDocument([
            'document_type' => 'quality_certificate',
            'source_reference_type' => $payload['source_reference_type'] ?? 'quality_inspection',
            'source_reference_id' => $payload['inspection_id'] ?? null,
            'source_action' => $payload['source_action'] ?? 'quality_certificate',
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => 0,
            'status' => 'draft',
            'metadata' => [
                'certificate_number' => $payload['certificate_number'] ?? null,
                'inspection_id' => $payload['inspection_id'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
        ]);
    }

    public function issueCertificate(int $certificateId): TextileWorkflowDocument
    {
        $certificate = $this->findTenantDocument($certificateId, 'quality_certificate');

        return $this->workflowService->transitionStatus($certificate->id, 'approved');
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
