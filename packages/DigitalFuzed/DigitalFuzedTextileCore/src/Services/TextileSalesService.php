<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerDocument;
use Workdo\Account\Models\CustomerPriceList;

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

        $profile = $this->resolveCustomerProfile($payload);
        if ($this->isJobWorkOnlyProfile($profile['operating_model'] ?? null)) {
            throw new RuntimeException('Selected customer profile is job-work only. Use Manufacturing or Processing workflows instead of Sales Order.');
        }

        $this->enforceSalesOrderProfileRules($profile);

        $metadata = array_merge($payload['metadata'] ?? [], $profile);

        return $this->workflowService->createDocument([
            'document_type' => 'sales_order',
            'source_reference_type' => $payload['source_reference_type'],
            'source_reference_id' => (int) $payload['source_reference_id'],
            'source_action' => $payload['source_action'] ?? 'convert',
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
            'metadata' => array_merge($metadata, $payload['metadata'] ?? []),
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

        return $this->workflowService->transitionStatus($dispatch->id, 'released');
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
