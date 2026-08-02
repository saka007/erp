<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use RuntimeException;

class TextileCostingService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function createCostingEntry(array $payload): TextileWorkflowDocument
    {
        $source = $this->findTenantSourceDocument((int) $payload['source_document_id']);

        return $this->workflowService->createDocument([
            'document_type' => 'costing_entry',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $source->id,
            'source_action' => 'cost_capture',
            'party_name' => $payload['party_name'] ?? $source->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $source->lot_reference,
            'quantity' => $payload['quantity'] ?? $source->quantity,
            'unit' => $payload['unit'] ?? $source->unit,
            'status' => 'draft',
            'metadata' => [
                'material_cost' => (float) ($payload['material_cost'] ?? 0),
                'conversion_cost' => (float) ($payload['conversion_cost'] ?? 0),
                'overhead_cost' => (float) ($payload['overhead_cost'] ?? 0),
                'revenue_value' => (float) ($payload['revenue_value'] ?? 0),
                'variance_value' => (float) ($payload['variance_value'] ?? 0),
                'notes' => $payload['notes'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function finalizeCostingEntry(int $costingEntryId): array
    {
        $entry = $this->findTenantTypedDocument($costingEntryId, 'costing_entry');

        if ($entry->status !== 'draft') {
            throw new RuntimeException('Only draft costing entries can be finalized.');
        }

        $metadata = is_array($entry->metadata) ? $entry->metadata : [];
        $material = (float) ($metadata['material_cost'] ?? 0);
        $conversion = (float) ($metadata['conversion_cost'] ?? 0);
        $overhead = (float) ($metadata['overhead_cost'] ?? 0);
        $variance = (float) ($metadata['variance_value'] ?? 0);
        $revenue = (float) ($metadata['revenue_value'] ?? 0);

        $totalCost = $material + $conversion + $overhead + $variance;
        $margin = $revenue - $totalCost;
        $marginPercent = $revenue > 0 ? ($margin / $revenue) * 100 : 0;
        $quantity = max(1, (float) $entry->quantity);

        $entry->metadata = array_merge($metadata, [
            'total_cost' => round($totalCost, 2),
            'margin_value' => round($margin, 2),
            'margin_percent' => round($marginPercent, 2),
            'unit_cost' => round($totalCost / $quantity, 4),
        ]);
        $entry->save();

        $approvedEntry = $this->workflowService->transitionStatus($entry->id, 'approved');

        $snapshot = $this->workflowService->createDocument([
            'document_type' => 'margin_snapshot',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $approvedEntry->id,
            'source_action' => 'margin_compute',
            'party_name' => $approvedEntry->party_name,
            'lot_reference' => $approvedEntry->lot_reference,
            'quantity' => $approvedEntry->quantity,
            'unit' => $approvedEntry->unit,
            'status' => 'approved',
            'metadata' => [
                'revenue_value' => round($revenue, 2),
                'total_cost' => round($totalCost, 2),
                'margin_value' => round($margin, 2),
                'margin_percent' => round($marginPercent, 2),
                'unit_cost' => round($totalCost / $quantity, 4),
            ],
        ]);

        return [
            'entry' => $approvedEntry,
            'snapshot' => $snapshot,
        ];
    }

    public function summary(): array
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $snapshots = TextileWorkflowDocument::query()
            ->where('created_by', $tenantId)
            ->where('document_type', 'margin_snapshot')
            ->get();

        $totalRevenue = 0.0;
        $totalCost = 0.0;
        $totalMargin = 0.0;

        foreach ($snapshots as $snapshot) {
            $meta = is_array($snapshot->metadata) ? $snapshot->metadata : [];
            $totalRevenue += (float) ($meta['revenue_value'] ?? 0);
            $totalCost += (float) ($meta['total_cost'] ?? 0);
            $totalMargin += (float) ($meta['margin_value'] ?? 0);
        }

        $marginPercent = $totalRevenue > 0 ? ($totalMargin / $totalRevenue) * 100 : 0;

        return [
            'entries_count' => TextileWorkflowDocument::query()->where('created_by', $tenantId)->where('document_type', 'costing_entry')->count(),
            'snapshots_count' => $snapshots->count(),
            'total_revenue' => round($totalRevenue, 2),
            'total_cost' => round($totalCost, 2),
            'total_margin' => round($totalMargin, 2),
            'margin_percent' => round($marginPercent, 2),
        ];
    }

    protected function findTenantSourceDocument(int $documentId): TextileWorkflowDocument
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $source = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('created_by', $tenantId)
            ->first();

        if ($source === null) {
            throw new RuntimeException('Source document not found for tenant context.');
        }

        if (!in_array($source->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Source document must be approved/released before costing.');
        }

        return $source;
    }

    protected function findTenantTypedDocument(int $documentId, string $documentType): TextileWorkflowDocument
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $document = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('document_type', $documentType)
            ->where('created_by', $tenantId)
            ->first();

        if ($document === null) {
            throw new RuntimeException('Document not found for tenant context.');
        }

        return $document;
    }
}
