<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use RuntimeException;

class TextileSalesService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function createSalesOrder(array $payload): TextileWorkflowDocument
    {
        if (empty($payload['source_reference_type']) || empty($payload['source_reference_id'])) {
            throw new RuntimeException('Sales order requires a commercial source reference.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'sales_order',
            'source_reference_type' => $payload['source_reference_type'],
            'source_reference_id' => (int) $payload['source_reference_id'],
            'source_action' => $payload['source_action'] ?? 'convert',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function approveSalesOrder(int $salesOrderId): TextileWorkflowDocument
    {
        $salesOrder = $this->findTenantDocument($salesOrderId, 'sales_order');

        return $this->workflowService->transitionStatus($salesOrder->id, 'approved');
    }

    public function createAllocation(int $salesOrderId, array $payload = []): TextileWorkflowDocument
    {
        $salesOrder = $this->findTenantDocument($salesOrderId, 'sales_order');
        if ($salesOrder->status !== 'approved') {
            throw new RuntimeException('Sales order must be approved before creating allocation.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'allocation',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $salesOrder->id,
            'source_action' => 'allocate_for_dispatch',
            'party_name' => $payload['party_name'] ?? $salesOrder->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $salesOrder->lot_reference,
            'quantity' => $payload['quantity'] ?? $salesOrder->quantity,
            'unit' => $payload['unit'] ?? $salesOrder->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function releaseAllocation(int $allocationId): TextileWorkflowDocument
    {
        $allocation = $this->findTenantDocument($allocationId, 'allocation');

        if ($allocation->status === 'draft') {
            $allocation = $this->workflowService->transitionStatus($allocation->id, 'approved');
        }

        if ($allocation->status !== 'approved') {
            throw new RuntimeException('Only draft or approved allocation can be released.');
        }

        return $this->workflowService->transitionStatus($allocation->id, 'released');
    }

    public function createDispatch(int $allocationId, array $payload = []): TextileWorkflowDocument
    {
        $allocation = $this->findTenantDocument($allocationId, 'allocation');
        if ($allocation->status !== 'released') {
            throw new RuntimeException('Allocation must be released before creating dispatch.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'dispatch',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $allocation->id,
            'source_action' => 'dispatch_release',
            'party_name' => $payload['party_name'] ?? $allocation->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $allocation->lot_reference,
            'quantity' => $payload['quantity'] ?? $allocation->quantity,
            'unit' => $payload['unit'] ?? $allocation->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function releaseDispatch(int $dispatchId): TextileWorkflowDocument
    {
        $dispatch = $this->findTenantDocument($dispatchId, 'dispatch');

        if ($dispatch->status === 'draft') {
            $dispatch = $this->workflowService->transitionStatus($dispatch->id, 'approved');
        }

        if ($dispatch->status !== 'approved') {
            throw new RuntimeException('Only draft or approved dispatch can be released.');
        }

        return $this->workflowService->transitionStatus($dispatch->id, 'released');
    }

    public function createChallan(int $dispatchId, array $payload = []): TextileWorkflowDocument
    {
        $dispatch = $this->findTenantDocument($dispatchId, 'dispatch');
        if ($dispatch->status !== 'released') {
            throw new RuntimeException('Dispatch must be released before creating challan.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'challan',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $dispatch->id,
            'source_action' => 'generate_challan',
            'party_name' => $payload['party_name'] ?? $dispatch->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $dispatch->lot_reference,
            'quantity' => $payload['quantity'] ?? $dispatch->quantity,
            'unit' => $payload['unit'] ?? $dispatch->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function markPod(int $challanId, array $payload = []): TextileWorkflowDocument
    {
        $challan = $this->findTenantDocument($challanId, 'challan');

        if ($challan->status === 'draft') {
            $challan = $this->workflowService->transitionStatus($challan->id, 'approved');
        }

        if ($challan->status === 'approved') {
            $challan = $this->workflowService->transitionStatus($challan->id, 'released');
        }

        if ($challan->status !== 'released') {
            throw new RuntimeException('Challan must be released before POD confirmation.');
        }

        $pod = $this->workflowService->createDocument([
            'document_type' => 'pod',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $challan->id,
            'source_action' => 'proof_of_delivery',
            'party_name' => $payload['party_name'] ?? $challan->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $challan->lot_reference,
            'quantity' => $payload['quantity'] ?? $challan->quantity,
            'unit' => $payload['unit'] ?? $challan->unit,
            'status' => 'approved',
            'metadata' => array_merge($payload['metadata'] ?? [], ['invoice_ready' => true]),
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        $this->workflowService->transitionStatus($challan->id, 'closed');

        return $pod;
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
