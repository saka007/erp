<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Services\TextileAvailabilityService;
use DigitalFuzed\TextileInventory\Services\TextileLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerDocument;
use Workdo\Account\Models\CustomerPriceList;

class TextileSalesService
{
    public function __construct(
        protected TextileWorkflowService $workflowService,
        protected TextileAvailabilityService $availabilityService,
        protected TextileOperatingPolicyService $policyService,
        protected TextileLedgerService $ledgerService
    )
    {
    }

    private function tenantHasCapability(string $capability): bool
    {
        try {
            $this->policyService->assertCapability($capability);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function createSalesOrder(array $payload): TextileWorkflowDocument
    {
        $profile = $this->resolveCustomerProfile($payload);
        if (empty($profile['customer_id']) && empty($payload['source_reference_id'])) {
            throw new RuntimeException('Select a valid customer profile for the sales order.');
        }
        if ($this->isJobWorkOnlyProfile($profile['operating_model'] ?? null)) {
            throw new RuntimeException('Selected customer profile is job-work only. Use Manufacturing or Processing workflows instead of Sales Order.');
        }

        $this->enforceSalesOrderProfileRules($profile);

        $metadata = array_merge($payload['metadata'] ?? [], $profile);
        $lotSelections = collect($payload['lot_selections'] ?? []);
        if ($lotSelections->isNotEmpty()) {
            $validatedLots = $lotSelections->map(function (array $line) {
                $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();
                $lot = TextileLot::query()
                    ->where('created_by', $tenantId)
                    ->where('lot_reference', $line['lot_reference'])
                    ->where('is_active', true)
                    ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
                    ->where('source_document_type', 'takha_entry')
                    ->first();

                if (! $lot || ! $lot->source_document_id) {
                    throw new RuntimeException('Selected Takha lot is not available for sale.');
                }

                $sourceQuery = TextileWorkflowDocument::query()
                    ->where('id', $lot->source_document_id)
                    ->where('created_by', $tenantId)
                    ->where('document_type', 'takha_entry');
                TextileBranchScope::applyWorkflowScope($sourceQuery);
                $source = $sourceQuery->first();
                if (! $source) {
                    throw new RuntimeException('Selected Takha lot belongs to another branch.');
                }

                if ($lot->status === 'hold') {
                    throw new RuntimeException('Selected Takha lot is on quality hold. Release it before creating a sales order.');
                }

                // Gate: fabric lots must pass inspection before sale (only when the tenant operates QC).
                if ($this->tenantHasCapability('quality_inspection')) {
                    $inspectionQuery = TextileWorkflowDocument::query()
                        ->where('created_by', $tenantId)
                        ->where('document_type', 'inspection')
                        ->where('lot_reference', $lot->lot_reference)
                        ->whereIn('status', ['approved', 'released']);
                    TextileBranchScope::applyWorkflowScope($inspectionQuery);
                    if (! $inspectionQuery->exists()) {
                        throw new RuntimeException('Takha lot must pass inspection before it can be sold.');
                    }
                }

                $quantity = (float) ($line['quantity'] ?? 0);
                if ($quantity <= 0 || $quantity > (float) $lot->available_quantity) {
                    throw new RuntimeException('Requested Takha quantity exceeds available stock.');
                }

                return [
                    'lot_reference' => $lot->lot_reference,
                    'quantity' => $quantity,
                    'unit' => $source->unit,
                    'takha_number' => $source->metadata['takha_number'] ?? $source->lot_reference,
                ];
            })->values();

            $units = $validatedLots->pluck('unit')->filter()->unique();
            if ($units->count() > 1) {
                throw new RuntimeException('Selected Takha lots must use the same unit.');
            }

            $payload['quantity'] = (float) $validatedLots->sum('quantity');
            $payload['unit'] = $units->first() ?? $payload['unit'] ?? null;
            $payload['lot_reference'] = $validatedLots->count() === 1
                ? $validatedLots->first()['lot_reference']
                : sprintf('%d Takha lots', $validatedLots->count());
            $metadata['requested_lots'] = $validatedLots->all();
            $metadata['order_value'] = (float) ($metadata['rate'] ?? 0) * (float) $payload['quantity'];
        }

        return $this->workflowService->createDocument([
            'document_type' => 'sales_order',
            'source_reference_type' => $payload['source_reference_type'] ?? null,
            'source_reference_id' => ! empty($payload['source_reference_id']) ? (int) $payload['source_reference_id'] : null,
            'source_action' => ! empty($payload['source_reference_id']) ? ($payload['source_action'] ?? 'convert') : null,
            'party_name' => $payload['party_name'] ?? ($profile['party_name'] ?? null),
            'lot_reference' => $payload['lot_reference'] ?? null,
            'quantity' => $payload['quantity'] ?? 0,
            'unit' => $payload['unit'] ?? null,
            'status' => 'draft',
            'metadata' => $metadata,
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

        $metadata = is_array($salesOrder->metadata) ? $salesOrder->metadata : [];
        if ($this->isJobWorkOnlyProfile($metadata['operating_model'] ?? null)) {
            throw new RuntimeException('Allocation is not allowed for job-work customer profiles. Continue through manufacturing/processing flow.');
        }

        $lotAllocations = collect($payload['lot_allocations'] ?? []);
        if ($lotAllocations->isEmpty()) {
            return $this->workflowService->createDocument([
                'document_type' => 'allocation',
                'source_reference_type' => 'textile_workflow_document',
                'source_reference_id' => $salesOrder->id,
                'source_action' => 'allocate_for_dispatch',
                'party_name' => $salesOrder->party_name,
                'lot_reference' => $salesOrder->lot_reference,
                'quantity' => $salesOrder->quantity,
                'unit' => $salesOrder->unit,
                'status' => 'draft',
                'metadata' => $metadata,
            ]);
        }

        $allocatedQuantity = (float) $lotAllocations->sum(fn ($line) => (float) ($line['quantity'] ?? 0));
        if (abs($allocatedQuantity - (float) $salesOrder->quantity) > 0.01) {
            throw new RuntimeException('Allocated lot quantity must equal the sales order quantity.');
        }

        $existingQuery = TextileWorkflowDocument::query()
            ->where('created_by', $salesOrder->created_by)
            ->where('document_type', 'allocation')
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $salesOrder->id);
        TextileBranchScope::applyWorkflowScope($existingQuery);
        if ($existingQuery->exists()) {
            throw new RuntimeException('This sales order already has an allocation.');
        }

        return DB::transaction(function () use ($salesOrder, $payload, $metadata, $lotAllocations, $allocatedQuantity) {
            $validatedLines = $lotAllocations->map(function (array $line) use ($salesOrder) {
                $lot = TextileLot::query()
                    ->where('created_by', $salesOrder->created_by)
                    ->where('lot_reference', $line['lot_reference'])
                    ->where('is_active', true)
                    ->whereIn('material_type', [TextileLot::TYPE_GREY_FABRIC, TextileLot::TYPE_FINISHED_FABRIC])
                    ->lockForUpdate()
                    ->first();

                if (! $lot || ! $lot->source_document_id) {
                    throw new RuntimeException('Selected fabric lot is not available for this branch.');
                }

                $sourceQuery = TextileWorkflowDocument::query()
                    ->where('id', $lot->source_document_id)
                    ->where('created_by', $salesOrder->created_by);
                TextileBranchScope::applyWorkflowScope($sourceQuery);
                if (! $sourceQuery->exists()) {
                    throw new RuntimeException('Selected fabric lot belongs to another branch.');
                }

                $quantity = (float) $line['quantity'];
                if ($quantity <= 0 || $quantity > (float) $lot->available_quantity) {
                    throw new RuntimeException('Allocated quantity exceeds available fabric stock.');
                }

                return [
                    'lot_reference' => $lot->lot_reference,
                    'quantity' => $quantity,
                    'material_type' => $lot->material_type,
                ];
            })->values()->all();

            $allocation = $this->workflowService->createDocument([
                'document_type' => 'allocation',
                'source_reference_type' => 'textile_workflow_document',
                'source_reference_id' => $salesOrder->id,
                'source_action' => 'allocate_for_dispatch',
                'party_name' => $salesOrder->party_name,
                'lot_reference' => count($validatedLines) === 1 ? $validatedLines[0]['lot_reference'] : sprintf('%d fabric lots', count($validatedLines)),
                'quantity' => $allocatedQuantity,
                'unit' => $salesOrder->unit,
                'status' => 'draft',
                'metadata' => array_merge($metadata, $payload['metadata'] ?? [], ['lot_allocations' => $validatedLines]),
            ]);

            foreach ($validatedLines as $line) {
                $this->availabilityService->reserve(
                    $line['lot_reference'],
                    (float) $line['quantity'],
                    'allocation',
                    $allocation->id
                );
            }

            return $allocation;
        });
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

        $allocationMetadata = is_array($allocation->metadata) ? $allocation->metadata : [];
        $this->enforceDispatchProfileRules($allocationMetadata, $allocation->party_name);

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
            'metadata' => array_merge($allocationMetadata, $payload['metadata'] ?? []),
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

        $released = $this->workflowService->transitionStatus($dispatch->id, 'released');

        // Ledger: goods dispatched to customer (fail-open).
        $this->ledgerService->postDispatchIssue($released, (string) ($released->unit ?? ''));

        return $released;
    }

    public function createChallan(int $dispatchId, array $payload = []): TextileWorkflowDocument
    {
        $dispatch = $this->findTenantDocument($dispatchId, 'dispatch');
        if ($dispatch->status !== 'released') {
            throw new RuntimeException('Dispatch must be released before creating challan.');
        }

        $dispatchMetadata = is_array($dispatch->metadata) ? $dispatch->metadata : [];

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
            'metadata' => array_merge($dispatchMetadata, $payload['metadata'] ?? []),
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
            'metadata' => array_merge(is_array($challan->metadata) ? $challan->metadata : [], $payload['metadata'] ?? [], ['invoice_ready' => true]),
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        $this->workflowService->transitionStatus($challan->id, 'closed');

        return $pod;
    }

    protected function resolveCustomerProfile(array $payload): array
    {
        if (!Schema::hasTable('customers')) {
            return [];
        }

        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();
        $query = Customer::query()->where('created_by', $tenantId);

        if (!empty($payload['customer_id'])) {
            $query->where('id', (int) $payload['customer_id']);
        } elseif (!empty($payload['party_name'])) {
            $query->where('company_name', trim((string) $payload['party_name']));
        } else {
            return [];
        }

        $customer = $query->first();
        if (!$customer) {
            return [];
        }

        return [
            'customer_id' => (int) $customer->id,
            'party_name' => $customer->company_name,
            'operating_model' => $customer->operating_model,
            'material_ownership' => $customer->material_ownership,
            'billing_mode' => $customer->billing_mode,
        ];
    }

    protected function isJobWorkOnlyProfile(?string $operatingModel): bool
    {
        return in_array($operatingModel, [
            TextileOperatingPolicyService::MODEL_JOBWORK_WEAVING,
            TextileOperatingPolicyService::MODEL_JOBWORK_PROCESSING,
        ], true);
    }

    protected function enforceSalesOrderProfileRules(array $profile): void
    {
        if (($profile['operating_model'] ?? null) !== TextileOperatingPolicyService::MODEL_TRADER_BULK) {
            return;
        }

        if (($profile['billing_mode'] ?? null) !== 'sale_value') {
            throw new RuntimeException('Trader/distributor customer profile must use sale-value billing mode for sales order flow.');
        }

        $customerId = isset($profile['customer_id']) ? (int) $profile['customer_id'] : null;
        if (!$customerId || !Schema::hasTable('customers')) {
            throw new RuntimeException('Trader/distributor customer profile requires credit limit and price list setup before sales order.');
        }

        $customer = Customer::query()
            ->where('id', $customerId)
            ->where('created_by', creatorId())
            ->first();

        if (!$customer || (float) ($customer->credit_limit ?? 0) <= 0) {
            throw new RuntimeException('Trader/distributor customer profile requires a positive credit limit before sales order.');
        }

        if (!Schema::hasTable('account_customer_price_lists')) {
            throw new RuntimeException('Trader/distributor customer profile requires customer price list setup before sales order.');
        }

        $hasActivePrice = CustomerPriceList::query()
            ->where('created_by', creatorId())
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->exists();

        if (!$hasActivePrice) {
            throw new RuntimeException('Trader/distributor customer profile requires at least one active customer price list entry before sales order.');
        }
    }

    protected function enforceDispatchProfileRules(array $metadata, ?string $partyName): void
    {
        if (($metadata['operating_model'] ?? null) !== TextileOperatingPolicyService::MODEL_EXPORT_COMPLIANCE) {
            return;
        }

        if (!Schema::hasTable('account_customer_documents')) {
            throw new RuntimeException('Export/compliance customer profile requires active compliance document before dispatch.');
        }

        $customerId = isset($metadata['customer_id']) ? (int) $metadata['customer_id'] : null;

        $query = CustomerDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'compliance')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now()->toDateString());
            });

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif (!empty($partyName) && Schema::hasTable('customers')) {
            $resolvedCustomerId = Customer::query()
                ->where('created_by', creatorId())
                ->where('company_name', trim((string) $partyName))
                ->value('id');

            if ($resolvedCustomerId) {
                $query->where('customer_id', (int) $resolvedCustomerId);
            }
        }

        if (!$query->exists()) {
            throw new RuntimeException('Export/compliance customer profile requires at least one active compliance document before dispatch.');
        }
    }

    protected function findTenantDocument(int $documentId, string $documentType): TextileWorkflowDocument
    {
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();

        $query = TextileWorkflowDocument::query()
            ->where('id', $documentId)
            ->where('document_type', $documentType)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId));

        TextileBranchScope::applyWorkflowScope($query);
        $document = $query->first();

        if ($document === null) {
            throw new RuntimeException('Document not found for tenant context.');
        }

        return $document;
    }
}
