<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Services\TextileLedgerService;
use RuntimeException;

class TextileQualityService
{
    public function __construct(
        protected TextileWorkflowService $workflowService,
        protected TextileLedgerService $ledgerService
    ) {
    }

    public function createInspection(array $payload): TextileWorkflowDocument
    {
        $this->assertNoDuplicateInspectionForLot($payload['lot_reference'] ?? null);

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

        $finalized = $this->workflowService->transitionStatus($inspection->id, $decision === 'pass' ? 'approved' : 'rejected');

        // Ledger: inspection pass is a real flow gate (takha cannot be sold until
        // approved) — record the QC transfer so the ledger shows the full chain.
        if ($decision === 'pass') {
            $this->ledgerService->postInspectionPass($finalized, (string) ($finalized->unit ?? ''));

            // Mark the inspected lot quality-approved so the inventory table shows
            // the QC stage (fail-open when the lot is missing).
            if ((string) ($finalized->lot_reference ?? '') !== '') {
                $this->markLotQualityApproved((string) $finalized->lot_reference);
            }
        }

        return $finalized;
    }

    /**
     * Flag a lot as quality-approved after a pass inspection (fail-open).
     */
    protected function markLotQualityApproved(string $lotReference): void
    {
        try {
            $lot = $this->findTenantLot($lotReference);
            $lot->production_stage = TextileLot::STAGE_QUALITY_APPROVED;
            $lot->save();
        } catch (\RuntimeException) {
            // Fail-open: lot missing for this tenant — nothing to update.
        }
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

    /**
     * Enforce that a takha lot can be inspected only once. Any existing
     * inspection (draft, approved, rejected, etc.) for the same takha blocks a
     * new one, so a takha cannot accumulate multiple inspection records.
     * Only enforced for takha lots (grey-fabric takha entries from weaving
     * output); generic lots may still go through multiple QC stages.
     */
    protected function assertNoDuplicateInspectionForLot(?string $lotReference): void
    {
        $lotReference = trim((string) ($lotReference ?? ''));

        if ($lotReference === '') {
            return;
        }

        if (! $this->isTakhaLot($lotReference)) {
            return;
        }

        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $existing = TextileWorkflowDocument::query()
            ->where('document_type', 'inspection')
            ->where('lot_reference', $lotReference)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->exists();

        if ($existing) {
            throw new RuntimeException('An inspection already exists for this takha lot. A takha can be inspected only once.');
        }
    }

    /**
     * Whether a lot reference maps to a takha lot (grey-fabric takha entry).
     */
    protected function isTakhaLot(string $lotReference): bool
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        return TextileLot::query()
            ->where('lot_reference', $lotReference)
            ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
            ->where('source_document_type', 'takha_entry')
            ->whereNotNull('source_document_id')
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->exists();
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
