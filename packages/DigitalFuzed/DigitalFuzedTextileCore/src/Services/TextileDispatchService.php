<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use RuntimeException;

class TextileDispatchService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function createDispatchPlan(array $payload): TextileWorkflowDocument
    {
        $sourceType = $payload['source_type'] ?? 'challan';
        $sourceId = (int) ($payload['source_id'] ?? $payload['challan_id'] ?? 0);

        $sourceDocument = $this->resolveDispatchSource($sourceType, $sourceId);

        return $this->workflowService->createDocument([
            'document_type' => 'dispatch_plan',
            'source_reference_type' => $payload['source_reference_type'],
            'source_reference_id' => $sourceDocument->id,
            'source_action' => $payload['source_action'],
            'party_name' => $sourceDocument->party_name,
            'lot_reference' => $sourceDocument->lot_reference,
            'quantity' => $sourceDocument->quantity,
            'unit' => $sourceDocument->unit,
            'status' => 'draft',
            'metadata' => [
                'source_type' => $sourceType,
                'dispatch_mode' => $payload['dispatch_mode'],
                'truck_number' => $payload['truck_number'] ?? null,
                'container_number' => $payload['container_number'] ?? null,
                'driver_id' => $payload['driver_id'] ?? null,
                'driver_name' => $payload['driver_name'] ?? null,
                'vehicle_id' => $payload['vehicle_id'] ?? null,
                'vehicle_number' => $payload['vehicle_number'] ?? null,
                'route_id' => $payload['route_id'] ?? null,
                'route_name' => $payload['route_name'] ?? null,
                'transport_vendor_id' => $payload['transport_vendor_id'] ?? null,
                'transport_vendor_name' => $payload['transport_vendor_name'] ?? null,
                'lr_number' => $payload['lr_number'] ?? null,
                'eway_bill_number' => $payload['eway_bill_number'] ?? null,
                'freight_amount' => $payload['freight_amount'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'challan_id' => $sourceType === 'challan' ? $sourceDocument->id : null,
                'source_document_id' => $sourceDocument->id,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    protected function resolveDispatchSource(string $sourceType, int $sourceId): TextileWorkflowDocument
    {
        return match ($sourceType) {
            'job_work_outward' => $this->findDispatchSource($sourceId, 'job_work_outward', ['released', 'closed'], 'Job-work outward must be released before dispatch planning.'),
            'yarn_dispatch' => $this->findDispatchSource($sourceId, 'warp_plan', ['approved', 'released', 'closed'], 'Yarn dispatch plan must be approved before dispatch planning.', 'yarn_dispatch'),
            default => $this->findDispatchSource($sourceId, 'challan', ['approved', 'released', 'closed'], 'Challan must be approved before dispatch planning.'),
        };
    }

    protected function findDispatchSource(int $documentId, string $documentType, array $allowedStatuses, string $errorMessage, ?string $requiredSourceAction = null): TextileWorkflowDocument
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $query = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('document_type', $documentType)
            ->when($tenantId !== null, fn ($query) => $query->where('created_by', $tenantId));

        if ($requiredSourceAction !== null) {
            $query->where('source_action', $requiredSourceAction);
        }

        $document = $query->first();

        if ($document === null) {
            throw new RuntimeException('Document not found for tenant context.');
        }

        if (!in_array($document->status, $allowedStatuses, true)) {
            throw new RuntimeException($errorMessage);
        }

        return $document;
    }

    public function approveDispatchPlan(int $dispatchPlanId): TextileWorkflowDocument
    {
        $plan = $this->findTenantDocument($dispatchPlanId, 'dispatch_plan');

        return $this->workflowService->transitionStatus($plan->id, 'approved');
    }

    public function createDispatchTracking(array $payload): TextileWorkflowDocument
    {
        $plan = $this->findTenantDocument((int) $payload['dispatch_plan_id'], 'dispatch_plan');
        if (!in_array($plan->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Dispatch plan must be approved before tracking updates.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'dispatch_tracking',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $plan->id,
            'source_action' => $payload['source_action'],
            'party_name' => $plan->party_name,
            'lot_reference' => $plan->lot_reference,
            'quantity' => $plan->quantity,
            'unit' => $plan->unit,
            'status' => 'draft',
            'metadata' => [
                'tracking_status' => $payload['tracking_status'],
                'current_location' => $payload['current_location'] ?? null,
                'vehicle_id' => $payload['vehicle_id'] ?? ($plan->metadata['vehicle_id'] ?? null),
                'vehicle_number' => $payload['vehicle_number'] ?? ($plan->metadata['vehicle_number'] ?? null),
                'driver_id' => $payload['driver_id'] ?? ($plan->metadata['driver_id'] ?? null),
                'driver_name' => $payload['driver_name'] ?? ($plan->metadata['driver_name'] ?? null),
                'route_id' => $payload['route_id'] ?? ($plan->metadata['route_id'] ?? null),
                'route_name' => $payload['route_name'] ?? ($plan->metadata['route_name'] ?? null),
                'transport_vendor_id' => $payload['transport_vendor_id'] ?? ($plan->metadata['transport_vendor_id'] ?? null),
                'transport_vendor_name' => $payload['transport_vendor_name'] ?? ($plan->metadata['transport_vendor_name'] ?? null),
                'lr_number' => $payload['lr_number'] ?? ($plan->metadata['lr_number'] ?? null),
                'eway_bill_number' => $payload['eway_bill_number'] ?? ($plan->metadata['eway_bill_number'] ?? null),
                'challan_id' => $plan->metadata['challan_id'] ?? null,
                'source_type' => $plan->metadata['source_type'] ?? 'challan',
                'source_document_id' => $plan->metadata['source_document_id'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function finalizeDispatchTracking(int $trackingId): TextileWorkflowDocument
    {
        $tracking = $this->findTenantDocument($trackingId, 'dispatch_tracking');

        return $this->workflowService->transitionStatus($tracking->id, 'approved');
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
