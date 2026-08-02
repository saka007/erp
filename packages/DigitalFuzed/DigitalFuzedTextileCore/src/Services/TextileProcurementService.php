<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Services\TextileMovementService;
use RuntimeException;

class TextileProcurementService
{
    public function __construct(
        protected TextileWorkflowService $workflowService,
        protected TextileMovementService $movementService
    ) {
    }

    public function createRequisition(array $payload): TextileWorkflowDocument
    {
        return $this->workflowService->createDocument([
            'document_type' => 'purchase_requisition',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function approveRequisition(int $requisitionId): TextileWorkflowDocument
    {
        return $this->workflowService->transitionStatus($requisitionId, 'approved');
    }

    public function createPurchaseOrder(int $requisitionId, array $payload = []): TextileWorkflowDocument
    {
        $requisition = $this->findTenantDocument($requisitionId, 'purchase_requisition');
        if ($requisition->status !== 'approved') {
            throw new RuntimeException('Requisition must be approved before creating purchase order.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'purchase_order',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $requisition->id,
            'source_action' => 'convert_to_po',
            'party_name' => $payload['party_name'] ?? $requisition->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $requisition->lot_reference,
            'quantity' => $payload['quantity'] ?? $requisition->quantity,
            'unit' => $payload['unit'] ?? $requisition->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function approvePurchaseOrder(int $purchaseOrderId): TextileWorkflowDocument
    {
        $purchaseOrder = $this->findTenantDocument($purchaseOrderId, 'purchase_order');
        return $this->workflowService->transitionStatus($purchaseOrder->id, 'approved');
    }

    public function createGrn(int $purchaseOrderId, array $payload = []): TextileWorkflowDocument
    {
        $purchaseOrder = $this->findTenantDocument($purchaseOrderId, 'purchase_order');
        if ($purchaseOrder->status !== 'approved') {
            throw new RuntimeException('Purchase order must be approved before creating GRN.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'grn',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $purchaseOrder->id,
            'source_action' => 'goods_receipt',
            'party_name' => $payload['party_name'] ?? $purchaseOrder->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $purchaseOrder->lot_reference,
            'quantity' => $payload['quantity'] ?? $purchaseOrder->quantity,
            'unit' => $payload['unit'] ?? $purchaseOrder->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function releaseGrn(int $grnId): TextileWorkflowDocument
    {
        $grn = $this->findTenantDocument($grnId, 'grn');
        if ($grn->status !== 'draft' && $grn->status !== 'approved') {
            throw new RuntimeException('Only draft or approved GRN can be released.');
        }

        if ($grn->status === 'draft') {
            $grn = $this->workflowService->transitionStatus($grn->id, 'approved');
        }

        return $this->workflowService->transitionStatus($grn->id, 'released');
    }

    public function createIncomingQc(int $grnId, array $payload = []): TextileWorkflowDocument
    {
        $grn = $this->findTenantDocument($grnId, 'grn');
        if ($grn->status !== 'released') {
            throw new RuntimeException('GRN must be released before creating incoming QC.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'incoming_qc',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $grn->id,
            'source_action' => 'incoming_inspection',
            'party_name' => $payload['party_name'] ?? $grn->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $grn->lot_reference,
            'quantity' => $payload['quantity'] ?? $grn->quantity,
            'unit' => $payload['unit'] ?? $grn->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function finalizeIncomingQc(int $incomingQcId, string $decision): TextileWorkflowDocument
    {
        $incomingQc = $this->findTenantDocument($incomingQcId, 'incoming_qc');

        if (!in_array($decision, ['pass', 'fail'], true)) {
            throw new RuntimeException('Incoming QC decision must be pass or fail.');
        }

        $targetStatus = $decision === 'pass' ? 'approved' : 'rejected';
        $updated = $this->workflowService->transitionStatus($incomingQc->id, $targetStatus);

        if ($decision === 'pass') {
            $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

            $lot = TextileLot::firstOrCreate(
                [
                    'created_by' => $tenantId,
                    'lot_reference' => $updated->lot_reference,
                ],
                [
                    'creator_id' => auth()->id(),
                    'received_quantity' => 0,
                    'available_quantity' => 0,
                    'status' => 'active',
                    'is_active' => true,
                ]
            );

            $lot->received_quantity = (float) $lot->received_quantity + (float) $updated->quantity;
            $lot->available_quantity = (float) $lot->available_quantity + (float) $updated->quantity;
            $lot->save();

            $this->movementService->createMovement([
                'movement_type' => 'receipt',
                'reference_type' => 'incoming_qc',
                'reference_id' => $updated->id,
                'lot_reference' => $updated->lot_reference,
                'location_from' => 'supplier',
                'location_to' => 'warehouse',
                'quantity' => $updated->quantity,
                'unit' => $updated->unit,
                'notes' => 'Receipt posted from incoming QC pass.',
            ]);
        }

        return $updated;
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
