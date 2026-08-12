<?php

namespace DigitalFuzed\TextileCore\Services;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileCore\Support\TextileWarehouseResolver;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
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
        $validatedLots = collect();
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
                // Pass = an approved/released inspection document exists, or the lot has been
                // marked quality-approved (production_stage), which is the visible QC marker.
                if ($this->tenantHasCapability('quality_inspection')) {
                    $inspectionQuery = TextileWorkflowDocument::query()
                        ->where('created_by', $tenantId)
                        ->where('document_type', 'inspection')
                        ->where('lot_reference', $lot->lot_reference)
                        ->whereIn('status', ['approved', 'released']);
                    TextileBranchScope::applyWorkflowScope($inspectionQuery);
                    $passedInspection = $inspectionQuery->exists()
                        || $lot->production_stage === TextileLot::STAGE_QUALITY_APPROVED;
                    if (! $passedInspection) {
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

        return DB::transaction(function () use ($payload, $metadata, $validatedLots, $profile) {
            $salesOrder = $this->workflowService->createDocument([
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

            // Commit the takha stock at sales-order creation so the same lot
            // cannot be double-booked by a second sales order.
            foreach ($validatedLots as $line) {
                $this->availabilityService->reserve(
                    $line['lot_reference'],
                    (float) $line['quantity'],
                    'sales_order',
                    $salesOrder->id
                );
            }

            return $salesOrder;
        });
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
                // The sales order already holds a reservation for this lot; it will be
                // transferred to this allocation, so the effective availability includes it.
                $soReservedQuantity = (float) TextileReservation::query()
                    ->where('created_by', $salesOrder->created_by)
                    ->where('lot_reference', $lot->lot_reference)
                    ->where('reference_type', 'sales_order')
                    ->where('reference_id', $salesOrder->id)
                    ->where('is_active', true)
                    ->sum('reserved_quantity');
                $effectiveAvailable = (float) $lot->available_quantity + $soReservedQuantity;
                if ($quantity <= 0 || $quantity > $effectiveAvailable) {
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
                // Transfer the sales-order reservation into the allocation so the
                // stock is not double-reserved.
                $soReservation = TextileReservation::query()
                    ->where('created_by', $salesOrder->created_by)
                    ->where('lot_reference', $line['lot_reference'])
                    ->where('reference_type', 'sales_order')
                    ->where('reference_id', $salesOrder->id)
                    ->where('is_active', true)
                    ->first();

                if ($soReservation !== null) {
                    $this->availabilityService->allocateReservation(
                        $soReservation->id,
                        $allocation->id,
                        'allocation'
                    );
                } else {
                    $this->availabilityService->reserve(
                        $line['lot_reference'],
                        (float) $line['quantity'],
                        'allocation',
                        $allocation->id
                    );
                }
            }

            // Release any sales-order reservations for lots that were not consumed
            // by this allocation (e.g. a different lot was picked at allocation time).
            TextileReservation::query()
                ->where('created_by', $salesOrder->created_by)
                ->where('reference_type', 'sales_order')
                ->where('reference_id', $salesOrder->id)
                ->where('is_active', true)
                ->get()
                ->each(fn (TextileReservation $reservation) => $this->availabilityService->releaseReservation($reservation->id));

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

    public function approveChallan(int $challanId): TextileWorkflowDocument
    {
        $challan = $this->findTenantDocument($challanId, 'challan');

        if ($challan->status !== 'draft') {
            throw new RuntimeException('Only draft challan can be approved.');
        }

        return $this->workflowService->transitionStatus($challan->id, 'approved');
    }

    public function updateChallan(int $challanId, array $payload = []): TextileWorkflowDocument
    {
        $challan = $this->findTenantDocument($challanId, 'challan');

        if ($challan->status !== 'draft') {
            throw new RuntimeException('Only draft challan can be edited.');
        }

        $challan->party_name = $payload['party_name'] ?? $challan->party_name;
        $challan->lot_reference = $payload['lot_reference'] ?? $challan->lot_reference;
        $challan->quantity = $payload['quantity'] ?? $challan->quantity;
        $challan->unit = $payload['unit'] ?? $challan->unit;
        $challan->save();

        return $challan->refresh();
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

    /**
     * Generate a core SalesInvoice (with line items) from a challan.
     *
     * The challan metadata chain (sales order -> allocation -> dispatch -> challan)
     * carries customer_id (textile Customer id), rate, rate_source, item info and
     * warehouse, so the invoice can be created with real line items.
     */
    public function createSalesInvoiceFromChallan(int $challanId): TextileWorkflowDocument
    {
        $challan = $this->findTenantDocument($challanId, 'challan');

        if (! in_array($challan->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Challan must be approved or released before generating the sales invoice.');
        }

        $metadata = is_array($challan->metadata) ? $challan->metadata : [];
        $existingInvoiceId = isset($metadata['sales_invoice_id']) ? (int) $metadata['sales_invoice_id'] : null;

        if ($existingInvoiceId) {
            $invoice = SalesInvoice::query()
                ->where('id', $existingInvoiceId)
                ->where('created_by', $challan->created_by)
                ->first();

            if ($invoice) {
                $challan->setAttribute('sales_invoice_created_now', false);

                return $challan;
            }
        }

        if (! class_exists(\App\Models\SalesInvoice::class) || ! Schema::hasTable('sales_invoices')) {
            throw new RuntimeException('Sales invoice module is not available.');
        }

        // Resolve the textile Customer for the challan party.
        $customer = Customer::query()
            ->where('created_by', $challan->created_by)
            ->where(function ($query) use ($challan, $metadata) {
                $query->where('id', isset($metadata['customer_id']) ? (int) $metadata['customer_id'] : 0)
                    ->orWhere('company_name', trim((string) ($metadata['party_name'] ?? $challan->party_name)));
            })
            ->first();

        $customerId = $customer?->user_id ? (int) $customer->user_id : null;

        // Fallback: firstOrCreate a client user exactly like customersForSelect().
        if ($customer && ! $customerId) {
            $email = $customer->contact_person_email ?: 'customer' . $customer->id . '@' . str_replace(['https://', 'http://'], '', (string) config('app.url'));
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $customer->company_name ?: ($customer->contact_person_name ?: 'Customer ' . $customer->id),
                    'password'          => \Illuminate\Support\Str::random(16),
                    'type'              => 'client',
                    'creator_id'        => $challan->creator_id,
                    'created_by'        => $challan->created_by,
                    'lang'              => 'en',
                    'email_verified_at' => now(),
                ]
            );
            $customer->user_id = $user->id;
            $customer->save();
            $customerId = (int) $user->id;
        }

        if (! $customerId) {
            throw new RuntimeException('Challan customer has no linked client user. Set up the customer profile first.');
        }

        // Resolve the invoice rate.
        $rate = isset($metadata['rate']) && $metadata['rate'] !== null && $metadata['rate'] !== '' ? (float) $metadata['rate'] : null;
        $rateSource = isset($metadata['rate_source']) ? (string) $metadata['rate_source'] : null;

        $productServiceItemId = isset($metadata['product_service_item_id']) ? (int) $metadata['product_service_item_id'] : null;
        $itemName = $metadata['item_name'] ?? 'Takha / Woven Fabric';
        $itemSku = $metadata['item_sku'] ?? null;

        if ($rate === null && $productServiceItemId && $customer) {
            // Fallback: per-customer price list, then item sale price.
            if (Schema::hasTable('account_customer_price_lists')) {
                $priceListRate = CustomerPriceList::query()
                    ->where('created_by', $challan->created_by)
                    ->where('customer_id', (int) $customer->id)
                    ->where('product_service_item_id', $productServiceItemId)
                    ->where('is_active', true)
                    ->value('unit_price');

                if ($priceListRate !== null) {
                    $rate = (float) $priceListRate;
                    $rateSource = 'customer_price_list';
                }
            }

            if ($rate === null && $productServiceItemId) {
                $salePrice = \Workdo\ProductService\Models\ProductServiceItem::query()
                    ->where('id', $productServiceItemId)
                    ->where('created_by', $challan->created_by)
                    ->value('sale_price');

                if ($salePrice !== null) {
                    $rate = (float) $salePrice;
                    $rateSource = 'item_sale_price';
                }
            }
        }

        if ($rate === null || $rate < 0) {
            throw new RuntimeException('No rate available for the challan item. Set a rate on the sales order or customer price list first.');
        }

        $quantity = (float) $challan->quantity;
        if ($quantity <= 0) {
            throw new RuntimeException('Challan quantity must be greater than zero to generate an invoice.');
        }

        // Credit terms mirror the purchase side (TX-GRN): due on invoice date
        // unless the customer has explicitly opted into credit.
        $invoiceDate = now()->toDateString();
        $dueDate = $invoiceDate;
        $paymentTerms = null;

        if ($customer && (bool) ($customer->credit_enabled ?? false) && (int) ($customer->credit_days ?? 0) > 0) {
            $dueDate = \Carbon\Carbon::parse($invoiceDate)->addDays((int) $customer->credit_days)->toDateString();
            $paymentTerms = sprintf('Net %d', (int) $customer->credit_days);
        }

        // Invoice line items require a product_service_item id. When the challan
        // has no specific product (e.g. takha-only sales order without a selected
        // item), fall back to a generic tenant-scoped "Takha / Woven Fabric" item
        // so the invoice is never a bare total-only record.
        if (! $productServiceItemId) {
            $genericItem = \Workdo\ProductService\Models\ProductServiceItem::query()
                ->where('created_by', $challan->created_by)
                ->where('name', $itemName)
                ->first();

            if (! $genericItem) {
                $genericItem = \Workdo\ProductService\Models\ProductServiceItem::query()->create([
                    'name' => $itemName,
                    'sku' => $itemSku ?: 'TX-GENERIC-' . $challan->id,
                    'type' => 'product',
                    'unit' => null,
                    'purchase_price' => 0,
                    'sale_price' => $rate,
                    'is_active' => 1,
                    'creator_id' => $challan->creator_id,
                    'created_by' => $challan->created_by,
                ]);
            }

            $productServiceItemId = (int) $genericItem->id;
        }

        $subtotal = round($rate * $quantity, 2);
        $warehouseId = isset($metadata['warehouse_id']) ? (int) $metadata['warehouse_id'] : null;
        // Challan metadata may not carry a warehouse — fall back to the tenant's
        // active-branch warehouse (or first active warehouse) so the generated
        // sales invoice can be posted and stock decremented correctly.
        $warehouseId = TextileWarehouseResolver::resolve($warehouseId, (int) $challan->created_by);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => sprintf('TX-CHL-%s', str_pad((string) $challan->id, 6, '0', STR_PAD_LEFT)),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'customer_id' => $customerId,
            'warehouse_id' => $warehouseId ?: null,
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $subtotal,
            'paid_amount' => 0,
            'balance_amount' => $subtotal,
            'status' => 'draft',
            'type' => 'product',
            'payment_terms' => $paymentTerms,
            'notes' => sprintf('Draft invoice from challan %s (%s). Item: %s.', $challan->id, $challan->document_number, $itemName),
            'creator_id' => $challan->creator_id,
            'created_by' => $challan->created_by,
        ]);

        SalesInvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $productServiceItemId,
            'quantity' => $quantity,
            'unit_price' => $rate,
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ]);

        $metadata['sales_invoice_id'] = $invoice->id;
        $metadata['sales_invoice_number'] = $invoice->invoice_number;
        $metadata['sales_invoice_status'] = $invoice->status;
        $metadata['sales_invoice_rate_source'] = $rateSource;
        $challan->metadata = $metadata;
        $challan->save();
        $challan->setAttribute('sales_invoice_created_now', true);

        return $challan;
    }

    /**
     * Generates a job-work (`type = service`) sales invoice from a yarn
     * dispatch plan (yarn → sizing vendor, source_type = yarn_dispatch).
     *
     * Idempotent: the dispatch plan metadata carries `job_work_invoice_id`,
     * so repeated calls return the existing invoice instead of creating
     * duplicates. The sizing vendor is resolved through its linked client
     * user (or created on demand), mirroring the challan invoice flow.
     */
    public function createJobWorkInvoiceFromDispatchPlan(int $dispatchPlanId, ?float $rate = null): TextileWorkflowDocument
    {
        $plan = $this->findTenantDocument($dispatchPlanId, 'dispatch_plan');

        if (! in_array($plan->status, ['approved', 'released', 'closed'], true)) {
            throw new RuntimeException('Dispatch plan must be approved before generating the job-work invoice.');
        }

        $metadata = is_array($plan->metadata) ? $plan->metadata : [];
        $sourceType = $metadata['source_type'] ?? null;

        if (! in_array($sourceType, ['yarn_dispatch', 'job_work_outward'], true)) {
            throw new RuntimeException('Job-work invoices can only be generated for yarn dispatch or job-work outward dispatches.');
        }

        $existingInvoiceId = isset($metadata['job_work_invoice_id']) ? (int) $metadata['job_work_invoice_id'] : null;
        if ($existingInvoiceId) {
            $invoice = SalesInvoice::query()
                ->where('id', $existingInvoiceId)
                ->where('created_by', $plan->created_by)
                ->first();

            if ($invoice) {
                $plan->setAttribute('job_work_invoice_created_now', false);

                return $plan;
            }
        }

        if (! class_exists(\App\Models\SalesInvoice::class) || ! Schema::hasTable('sales_invoices')) {
            throw new RuntimeException('Sales invoice module is not available.');
        }

        $partyName = trim((string) ($metadata['party_name'] ?? $plan->party_name));
        if ($partyName === '') {
            throw new RuntimeException('Dispatch plan has no party to invoice.');
        }

        $vendor = null;
        if (Schema::hasTable('vendors')) {
            $vendor = \Workdo\Account\Models\Vendor::query()
                ->where('created_by', $plan->created_by)
                ->where('company_name', $partyName)
                ->first();
        }

        $customerId = $vendor?->user_id ? (int) $vendor->user_id : null;

        if ($customerId === null) {
            $email = $vendor?->contact_person_email ?: 'jobwork' . ($vendor?->id ?? $plan->id) . '@' . str_replace(['https://', 'http://'], '', (string) config('app.url'));
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $vendor?->company_name ?: ($plan->party_name ?: 'Job Work Party ' . $plan->id),
                    'password' => \Illuminate\Support\Str::random(16),
                    'type' => 'client',
                    'creator_id' => $plan->creator_id,
                    'created_by' => $plan->created_by,
                    'lang' => 'en',
                    'email_verified_at' => now(),
                ]
            );
            if ($vendor) {
                $vendor->user_id = $user->id;
                $vendor->save();
            }
            $customerId = (int) $user->id;
        }

        $quantity = (float) $plan->quantity;
        if ($quantity <= 0) {
            throw new RuntimeException('Dispatch quantity must be greater than zero to generate an invoice.');
        }

        $itemName = 'Job Work - Sizing';
        if ($sourceType === 'job_work_outward') {
            $itemName = 'Job Work - Processing';
        }

        // Resolve (or create) a tenant-scoped service item so the invoice is
        // never a bare total-only record and shows under core service items.
        $serviceItem = \Workdo\ProductService\Models\ProductServiceItem::query()
            ->where('created_by', $plan->created_by)
            ->where('type', 'service')
            ->where('name', $itemName)
            ->first();

        if (! $serviceItem) {
            $serviceItem = \Workdo\ProductService\Models\ProductServiceItem::query()->create([
                'name' => $itemName,
                'sku' => 'TX-JW-' . $plan->id,
                'type' => 'service',
                'unit' => null,
                'purchase_price' => 0,
                'sale_price' => $rate ?? 0,
                'is_active' => 1,
                'creator_id' => $plan->creator_id,
                'created_by' => $plan->created_by,
            ]);
        }

        $invoiceRate = $rate ?? (float) ($serviceItem->sale_price ?? 0);
        $subtotal = round($invoiceRate * $quantity, 2);

        $invoiceDate = now()->toDateString();
        $invoice = SalesInvoice::query()->create([
            'invoice_number' => sprintf('TX-JW-%s', str_pad((string) $plan->id, 6, '0', STR_PAD_LEFT)),
            'invoice_date' => $invoiceDate,
            'due_date' => $invoiceDate,
            'customer_id' => $customerId,
            'warehouse_id' => null,
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $subtotal,
            'paid_amount' => 0,
            'balance_amount' => $subtotal,
            'status' => 'draft',
            'type' => 'service',
            'payment_terms' => null,
            'notes' => sprintf('Draft job-work invoice from dispatch %s (%s). Party: %s.', $plan->id, $plan->document_number, $partyName),
            'creator_id' => $plan->creator_id,
            'created_by' => $plan->created_by,
        ]);

        SalesInvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'product_id' => (int) $serviceItem->id,
            'quantity' => $quantity,
            'unit_price' => $invoiceRate,
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ]);

        $metadata['job_work_invoice_id'] = $invoice->id;
        $metadata['job_work_invoice_number'] = $invoice->invoice_number;
        $metadata['job_work_invoice_status'] = $invoice->status;
        $plan->metadata = $metadata;
        $plan->save();
        $plan->setAttribute('job_work_invoice_created_now', true);

        return $plan;
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
