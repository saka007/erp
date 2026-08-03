<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Workdo\Account\Models\Customer;

class TextileCostingService
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function createCostingEntry(array $payload): TextileWorkflowDocument
    {
        $source = $this->findTenantSourceDocument((int) $payload['source_document_id']);
        $profile = $this->resolveCustomerProfile($source, $payload['party_name'] ?? null);
        $enteredMaterialCost = (float) ($payload['material_cost'] ?? 0);
        $isCustomerOwned = ($profile['material_ownership'] ?? null) === 'customer_owned';
        $effectiveMaterialCost = $isCustomerOwned ? 0.0 : $enteredMaterialCost;

        $metadata = [
            'material_cost' => $effectiveMaterialCost,
            'conversion_cost' => (float) ($payload['conversion_cost'] ?? 0),
            'overhead_cost' => (float) ($payload['overhead_cost'] ?? 0),
            'revenue_value' => (float) ($payload['revenue_value'] ?? 0),
            'variance_value' => (float) ($payload['variance_value'] ?? 0),
            'rolls_count' => isset($payload['rolls_count']) && $payload['rolls_count'] !== '' ? (float) $payload['rolls_count'] : null,
            'notes' => $payload['notes'] ?? null,
        ];

        if ($isCustomerOwned) {
            $metadata['entered_material_cost'] = $enteredMaterialCost;
            $metadata['costing_mode'] = 'conversion_only';
        }

        if (!empty($profile)) {
            $metadata = array_merge($metadata, $profile);
        }

        return $this->workflowService->createDocument([
            'document_type' => 'costing_entry',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $source->id,
            'source_action' => 'cost_capture',
            'party_name' => $payload['party_name'] ?? ($profile['party_name'] ?? $source->party_name),
            'lot_reference' => $payload['lot_reference'] ?? $source->lot_reference,
            'quantity' => $payload['quantity'] ?? $source->quantity,
            'unit' => $payload['unit'] ?? $source->unit,
            'status' => 'draft',
            'metadata' => $metadata,
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
        if (($metadata['material_ownership'] ?? null) === 'customer_owned') {
            $material = 0.0;
            $metadata['material_cost'] = 0.0;
            $metadata['costing_mode'] = 'conversion_only';
        }
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

    protected function resolveCustomerProfile(TextileWorkflowDocument $source, ?string $partyName = null): array
    {
        $metadata = is_array($source->metadata) ? $source->metadata : [];

        if (isset($metadata['material_ownership']) || isset($metadata['operating_model']) || isset($metadata['billing_mode'])) {
            return [
                'customer_id' => isset($metadata['customer_id']) ? (int) $metadata['customer_id'] : null,
                'operating_model' => $metadata['operating_model'] ?? null,
                'material_ownership' => $metadata['material_ownership'] ?? null,
                'billing_mode' => $metadata['billing_mode'] ?? null,
                'party_name' => $partyName ?: ($metadata['party_name'] ?? $source->party_name),
            ];
        }

        if (!Schema::hasTable('customers')) {
            return [];
        }

        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $query = Customer::query()->where('created_by', $tenantId);
        $customerId = isset($metadata['customer_id']) ? (int) $metadata['customer_id'] : null;
        if ($customerId) {
            $query->where('id', $customerId);
        } else {
            $effectiveParty = trim((string) ($partyName ?: $source->party_name ?: ''));
            if ($effectiveParty === '') {
                return [];
            }

            $query->where('company_name', $effectiveParty);
        }

        $customer = $query->first();
        if (!$customer) {
            return [];
        }

        return [
            'customer_id' => (int) $customer->id,
            'operating_model' => $customer->operating_model,
            'material_ownership' => $customer->material_ownership,
            'billing_mode' => $customer->billing_mode,
            'party_name' => $customer->company_name,
        ];
    }
}
