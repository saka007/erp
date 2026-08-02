<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TextileWorkflowService
{
    public function __construct(
        protected TextileNumberingService $numberingService,
        protected TextileAuditService $auditService,
        protected TextileIdempotencyService $idempotencyService,
        protected TextileCommercialBoundaryService $commercialBoundaryService,
        protected TextileApprovalService $approvalService
    ) {
    }

    public function createDocument(array $payload): TextileWorkflowDocument
    {
        return DB::transaction(function () use ($payload) {
            $documentType = $payload['document_type'];
            $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

            if (!empty($payload['idempotency_key'])) {
                $existingId = $this->idempotencyService->findResourceId($payload['idempotency_key'], 'textile_workflow_document');
                if ($existingId !== null) {
                    return TextileWorkflowDocument::findOrFail($existingId);
                }
            }

            if (!empty($payload['source_reference_type']) && !empty($payload['source_reference_id']) && !empty($payload['source_action'])) {
                if ($payload['source_reference_type'] === 'sales_proposal') {
                    $mapped = $this->commercialBoundaryService->resolveCanonical('sales_proposal', (int) $payload['source_reference_id']);
                    if ($mapped === null || $mapped->canonical_type !== 'sales_quotation') {
                        throw new RuntimeException('SalesProposal is legacy source. Map it to a canonical sales_quotation first.');
                    }

                    $payload['source_reference_type'] = 'sales_quotation';
                    $payload['source_reference_id'] = (int) $mapped->canonical_id;
                }

                $existing = TextileWorkflowDocument::query()
                    ->where('created_by', $tenantId)
                    ->where('source_reference_type', $payload['source_reference_type'])
                    ->where('source_reference_id', $payload['source_reference_id'])
                    ->where('source_action', $payload['source_action'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $documentNumber = $payload['document_number'] ?? $this->numberingService->next($documentType);

            $document = TextileWorkflowDocument::create([
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'source_reference_type' => $payload['source_reference_type'] ?? null,
                'source_reference_id' => $payload['source_reference_id'] ?? null,
                'source_action' => $payload['source_action'] ?? null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'party_name' => $payload['party_name'] ?? null,
                'lot_reference' => $payload['lot_reference'] ?? null,
                'quantity' => $payload['quantity'] ?? 0,
                'unit' => $payload['unit'] ?? null,
                'status' => $payload['status'] ?? 'draft',
                'metadata' => $payload['metadata'] ?? null,
                'creator_id' => auth()->id(),
                'created_by' => $tenantId,
            ]);

            if (!empty($payload['idempotency_key'])) {
                $this->idempotencyService->remember($payload['idempotency_key'], 'textile_workflow_document', $document->id);
            }

            $this->auditService->record('textile.workflow.created', [
                'action' => 'created',
                'entity_type' => 'textile_workflow_document',
                'entity_id' => $document->id,
                'document_type' => $document->document_type,
                'document_number' => $document->document_number,
                'id' => $document->id,
            ]);

            return $document;
        });
    }

    public function transitionStatus(int $documentId, string $nextStatus): TextileWorkflowDocument
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        $document = TextileWorkflowDocument::query()
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->findOrFail($documentId);

        $allowed = [
            'draft' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['released', 'rejected', 'cancelled'],
            'released' => ['closed', 'rejected', 'cancelled'],
            'rejected' => [],
            'closed' => [],
            'cancelled' => [],
        ];

        $currentStatus = $document->status;
        if (!in_array($nextStatus, $allowed[$currentStatus] ?? [], true)) {
            throw new RuntimeException("Invalid workflow transition from {$currentStatus} to {$nextStatus}.");
        }

        $this->approvalService->assertTransitionIsApproved($document, $nextStatus);

        $document->status = $nextStatus;
        $document->save();

        $this->auditService->record('textile.workflow.status_changed', [
            'action' => 'status_changed',
            'entity_type' => 'textile_workflow_document',
            'entity_id' => $document->id,
            'document_type' => $document->document_type,
            'document_number' => $document->document_number,
            'id' => $document->id,
            'from' => $currentStatus,
            'to' => $nextStatus,
            'from_status' => $currentStatus,
            'to_status' => $nextStatus,
        ]);

        return $document;
    }

    public function listByType(string $documentType)
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        return TextileWorkflowDocument::query()
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->where('document_type', $documentType)
            ->latest('id')
            ->get();
    }

    public function summary(): array
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        $counts = TextileWorkflowDocument::query()
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->selectRaw('document_type, COUNT(*) as total')
            ->groupBy('document_type')
            ->pluck('total', 'document_type')
            ->toArray();

        return [
            'total_documents' => array_sum($counts),
            'by_type' => $counts,
        ];
    }
}
