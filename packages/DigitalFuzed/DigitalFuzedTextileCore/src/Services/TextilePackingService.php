<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use RuntimeException;

class TextilePackingService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function createRollPacking(array $payload): TextileWorkflowDocument
    {
        return $this->createPackingDocument('roll_packing', 'roll_packing', $payload);
    }

    public function createBundlePacking(array $payload): TextileWorkflowDocument
    {
        return $this->createPackingDocument('bundle_packing', 'bundle_packing', $payload);
    }

    public function createBalePacking(array $payload): TextileWorkflowDocument
    {
        return $this->createPackingDocument('bale_packing', 'bale_packing', $payload);
    }

    public function createLabel(array $payload): TextileWorkflowDocument
    {
        $challanId = isset($payload['challan_id']) && $payload['challan_id'] !== null
            ? (int) $payload['challan_id']
            : null;

        $challan = $challanId ? $this->findTenantDocument($challanId, 'challan') : null;
        if ($challan && !in_array($challan->status, ['released', 'closed'], true)) {
            throw new RuntimeException('Challan must be released before label generation.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'packing_label',
            'source_reference_type' => $payload['source_reference_type'] ?? 'challan',
            'source_reference_id' => $challan?->id,
            'source_action' => 'label_generation',
            'party_name' => $challan?->party_name,
            'lot_reference' => $payload['lot_reference'] ?? ($challan?->lot_reference),
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? ($challan?->unit),
            'status' => 'draft',
            'metadata' => [
                'label_type' => $payload['label_type'] ?? null,
                'label_code' => $payload['label_code'] ?? null,
                'packing_material' => $payload['packing_material'] ?? null,
                'weight' => $payload['weight'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'challan_id' => $challan?->id,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function issueLabel(int $labelId): TextileWorkflowDocument
    {
        $label = $this->findTenantDocument($labelId, 'packing_label');

        return $this->workflowService->transitionStatus($label->id, 'approved');
    }

    protected function createPackingDocument(string $documentType, string $sourceAction, array $payload): TextileWorkflowDocument
    {
        $challanId = isset($payload['challan_id']) && $payload['challan_id'] !== null
            ? (int) $payload['challan_id']
            : null;

        $challan = $challanId ? $this->findTenantDocument($challanId, 'challan') : null;
        if ($challan && !in_array($challan->status, ['released', 'closed'], true)) {
            throw new RuntimeException('Challan must be released before packing.');
        }

        return $this->workflowService->createDocument([
            'document_type' => $documentType,
            'source_reference_type' => $payload['source_reference_type'] ?? 'challan',
            'source_reference_id' => $challan?->id,
            'source_action' => $sourceAction,
            'party_name' => $challan?->party_name,
            'lot_reference' => $payload['lot_reference'] ?? ($challan?->lot_reference),
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? ($challan?->unit),
            'status' => 'draft',
            'metadata' => [
                'packing_material' => $payload['packing_material'] ?? null,
                'weight' => $payload['weight'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'challan_id' => $challan?->id,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
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
