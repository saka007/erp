<?php

namespace DigitalFuzed\TextileCore\Services;

use App\Models\PurchaseInvoice;
use App\Models\User;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileCore\Support\TextileWarehouseResolver;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Services\TextileMovementService;
use Illuminate\Support\Facades\Schema;
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
        $tenantId = auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id();
        $lotReference = isset($payload['lot_reference']) ? trim((string) $payload['lot_reference']) : '';
        if ($lotReference === '') {
            $lotReference = $this->generateAutoLotReference($tenantId);
        }

        return $this->workflowService->createDocument([
            'document_type' => 'purchase_requisition',
            'party_name' => $payload['party_name'] ?? null,
            'lot_reference' => $lotReference,
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

    public function deleteRequisition(int $requisitionId): void
    {
        $requisition = $this->findTenantDocument($requisitionId, 'purchase_requisition');

        if ($requisition->status !== 'draft') {
            throw new RuntimeException('Only draft requisitions can be deleted.');
        }

        $hasDownstreamDocument = TextileWorkflowDocument::query()
            ->where('created_by', $requisition->created_by)
            ->where('source_reference_type', 'textile_workflow_document')
            ->where('source_reference_id', $requisition->id)
            ->whereIn('document_type', ['rfq', 'purchase_order'])
            ->exists();

        if ($hasDownstreamDocument) {
            throw new RuntimeException('Requisition cannot be deleted because it is already used by a downstream document.');
        }

        $requisition->delete();
    }

    public function createRfq(int $requisitionId, array $payload = []): TextileWorkflowDocument
    {
        $requisition = $this->findTenantDocument($requisitionId, 'purchase_requisition');
        if ($requisition->status !== 'approved') {
            throw new RuntimeException('Requisition must be approved before creating RFQ.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'rfq',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $requisition->id,
            'source_action' => 'request_for_quote',
            'party_name' => $payload['party_name'] ?? $requisition->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $requisition->lot_reference,
            'quantity' => $payload['quantity'] ?? $requisition->quantity,
            'unit' => $payload['unit'] ?? $requisition->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? $requisition->metadata,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function sendRfq(int $rfqId): TextileWorkflowDocument
    {
        $rfq = $this->findTenantDocument($rfqId, 'rfq');

        return $this->workflowService->transitionStatus($rfq->id, 'approved');
    }

    public function closeRfq(int $rfqId): TextileWorkflowDocument
    {
        $rfq = $this->findTenantDocument($rfqId, 'rfq');

        return $this->workflowService->transitionStatus($rfq->id, 'released');
    }

    public function createPurchaseOrder(?int $requisitionId, ?int $rfqId = null, array $payload = []): TextileWorkflowDocument
    {
        if ($rfqId !== null) {
            $rfq = $this->findTenantDocument($rfqId, 'rfq');
            if (!in_array($rfq->status, ['approved', 'released'], true)) {
                throw new RuntimeException('RFQ must be sent before creating purchase order.');
            }

            return $this->workflowService->createDocument([
                'document_type' => 'purchase_order',
                'source_reference_type' => 'textile_workflow_document',
                'source_reference_id' => $rfq->id,
                'source_action' => 'convert_rfq_to_po',
                'party_name' => $payload['party_name'] ?? $rfq->party_name,
                'lot_reference' => $payload['lot_reference'] ?? $rfq->lot_reference,
                'quantity' => $payload['quantity'] ?? $rfq->quantity,
                'unit' => $payload['unit'] ?? $rfq->unit,
                'status' => 'draft',
                'metadata' => $payload['metadata'] ?? $rfq->metadata,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
            ]);
        }

        if ($requisitionId === null) {
            throw new RuntimeException('Requisition or RFQ reference is required for purchase order.');
        }

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
            'metadata' => $payload['metadata'] ?? $requisition->metadata,
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

        $lotReference = isset($payload['lot_reference']) ? trim((string) $payload['lot_reference']) : trim((string) ($purchaseOrder->lot_reference ?? ''));
        if ($lotReference === '') {
            $lotReference = $this->generateAutoLotReference((int) $purchaseOrder->created_by);
        }

        return $this->workflowService->createDocument([
            'document_type' => 'grn',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $purchaseOrder->id,
            'source_action' => 'goods_receipt',
            'party_name' => $payload['party_name'] ?? $purchaseOrder->party_name,
            'lot_reference' => $lotReference,
            'quantity' => $payload['quantity'] ?? $purchaseOrder->quantity,
            'unit' => $payload['unit'] ?? $purchaseOrder->unit,
            'status' => 'draft',
            'metadata' => $payload['metadata'] ?? $purchaseOrder->metadata,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    private function generateAutoLotReference(?int $tenantId): string
    {
        $dateToken = now()->format('ymd');
        $sequence = TextileWorkflowDocument::query()
            ->where('created_by', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return sprintf('LOT-AUTO-%s-%03d', $dateToken, $sequence);
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

        $released = $this->workflowService->transitionStatus($grn->id, 'released');
        $this->createPurchaseInvoiceFromGrn($released->id);

        return $released->refresh();
    }

    public function createPurchaseInvoiceFromGrn(int $grnId): TextileWorkflowDocument
    {
        $grn = $this->findTenantDocument($grnId, 'grn');

        if ($grn->status !== 'released') {
            throw new RuntimeException('GRN must be released before creating purchase invoice.');
        }

        $metadata = is_array($grn->metadata) ? $grn->metadata : [];
        $existingInvoiceId = isset($metadata['purchase_invoice_id']) ? (int) $metadata['purchase_invoice_id'] : null;

        if ($existingInvoiceId) {
            $invoice = PurchaseInvoice::query()
                ->where('id', $existingInvoiceId)
                ->where('created_by', $grn->created_by)
                ->first();

            if ($invoice) {
                $grn->setAttribute('purchase_invoice_created_now', false);
                return $grn;
            }
        }

        $sourceDocument = TextileWorkflowDocument::query()
            ->where('id', $grn->source_reference_id)
            ->where('document_type', 'purchase_order')
            ->where('created_by', $grn->created_by)
            ->first();

        $sourceMetadata = is_array($sourceDocument?->metadata) ? $sourceDocument->metadata : [];
        $metadataVendorId = isset($sourceMetadata['vendor_id']) ? (int) $sourceMetadata['vendor_id'] : 0;
        $warehouseId = isset($sourceMetadata['warehouse_id']) ? (int) $sourceMetadata['warehouse_id'] : null;
        // GRN/PO metadata may not carry a warehouse — fall back to the tenant's
        // active-branch warehouse (or first active warehouse) so the generated
        // invoice can be posted without a warehouse_id NULL crash.
        $warehouseId = TextileWarehouseResolver::resolve($warehouseId, (int) $grn->created_by);
        $amount = (float) ($sourceMetadata['invoice_amount'] ?? 0);

        // The requisition stores the Vendor.id (account vendors table). Purchase
        // invoices require a users.id, so resolve the vendor's linked user (or
        // firstOrCreate a vendor user exactly like customersForSelect()).
        $vendorId = $this->resolveVendorUserId($metadataVendorId, $grn->created_by, $grn->creator_id);

        if ($vendorId < 1 || ! User::query()->whereKey($vendorId)->exists()) {
            $grn->setAttribute('purchase_invoice_created_now', false);
            return $grn;
        }

        // Line item data rides the metadata chain (requisition -> PO -> GRN).
        $rate = isset($metadata['rate']) && $metadata['rate'] !== null && $metadata['rate'] !== '' ? (float) $metadata['rate'] : null;
        $productServiceItemId = isset($metadata['product_service_item_id']) ? (int) $metadata['product_service_item_id'] : null;
        $itemName = $metadata['item_name'] ?? null;
        $itemSku = $metadata['item_sku'] ?? null;
        $quantity = (float) ($grn->quantity ?? 0);

        $invoiceDate = now()->toDateString();

        // Credit terms: the vendor's credit_days apply only when the vendor has
        // explicitly opted into credit (credit_enabled). Otherwise the invoice
        // is due on the invoice date (pay on delivery).
        $dueDate = $invoiceDate;
        $paymentTerms = null;

        if (class_exists(\Workdo\Account\Models\Vendor::class)) {
            $vendor = \Workdo\Account\Models\Vendor::query()
                ->where(function ($query) use ($vendorId) {
                    $query->where('user_id', $vendorId)->orWhere('id', $vendorId);
                })
                ->where('created_by', $grn->created_by)
                ->first();

            if ($vendor && (bool) ($vendor->credit_enabled ?? false) && (int) ($vendor->credit_days ?? 0) > 0) {
                $dueDate = \Carbon\Carbon::parse($invoiceDate)->addDays((int) $vendor->credit_days)->toDateString();
                $paymentTerms = sprintf('Net %d', (int) $vendor->credit_days);
            }
        }

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => sprintf('TX-GRN-%s', str_pad((string) $grn->id, 6, '0', STR_PAD_LEFT)),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'vendor_id' => $vendorId,
            'warehouse_id' => $warehouseId,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'debit_note_applied' => 0,
            'balance_amount' => $amount,
            'status' => 'draft',
            'payment_terms' => $paymentTerms,
            'notes' => sprintf('Draft invoice from GRN %s (%s).', $grn->id, $grn->document_number),
            'creator_id' => $grn->creator_id,
            'created_by' => $grn->created_by,
        ]);

        // Create a real line item whenever the GRN carries product + rate data so
        // the invoice is never a bare total-only record. Falls back to a generic
        // tenant-scoped item (like the sales side) when no specific product was
        // selected; keeps the legacy single-total behaviour when no rate exists.
        if ($rate !== null && $rate >= 0 && $quantity > 0) {
            if (! $productServiceItemId && $itemName) {
                $genericItem = \Workdo\ProductService\Models\ProductServiceItem::query()
                    ->where('created_by', $grn->created_by)
                    ->where('name', $itemName)
                    ->first();

                if (! $genericItem) {
                    $genericItem = \Workdo\ProductService\Models\ProductServiceItem::query()->create([
                        'name' => $itemName,
                        'sku' => $itemSku ?: 'TX-GENERIC-' . $grn->id,
                        'type' => 'product',
                        'unit' => null,
                        'purchase_price' => $rate,
                        'sale_price' => 0,
                        'is_active' => 1,
                        'creator_id' => $grn->creator_id,
                        'created_by' => $grn->created_by,
                    ]);
                }

                $productServiceItemId = (int) $genericItem->id;
            }

            if ($productServiceItemId) {
                \App\Models\PurchaseInvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $productServiceItemId,
                    'quantity' => $quantity,
                    'unit_price' => $rate,
                    'discount_percentage' => 0,
                    'tax_percentage' => 0,
                ]);

                // Reconcile invoice totals with the actual line amount so the
                // invoice never disagrees with its item rows.
                $itemAmount = round($rate * $quantity, 2);
                $invoice->update([
                    'subtotal' => $itemAmount,
                    'total_amount' => $itemAmount,
                    'balance_amount' => $itemAmount,
                ]);
            }
        }

        $metadata['purchase_invoice_id'] = $invoice->id;
        $metadata['purchase_invoice_number'] = $invoice->invoice_number;
        $metadata['purchase_invoice_status'] = $invoice->status;
        $grn->metadata = $metadata;
        $grn->save();
        $grn->setAttribute('purchase_invoice_created_now', true);

        return $grn;
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
            'metadata' => $payload['metadata'] ?? $grn->metadata,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function createSupplierClaim(int $grnId, array $payload = []): TextileWorkflowDocument
    {
        $grn = $this->findTenantDocument($grnId, 'grn');
        if ($grn->status !== 'released') {
            throw new RuntimeException('GRN must be released before creating supplier claim.');
        }

        return $this->workflowService->createDocument([
            'document_type' => 'supplier_claim',
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => $grn->id,
            'source_action' => 'supplier_claim',
            'party_name' => $payload['party_name'] ?? $grn->party_name,
            'lot_reference' => $payload['lot_reference'] ?? $grn->lot_reference,
            'quantity' => $payload['quantity'] ?? $grn->quantity,
            'unit' => $payload['unit'] ?? $grn->unit,
            'status' => 'draft',
            'metadata' => [
                'claim_type' => $payload['claim_type'] ?? null,
                'claim_amount' => $payload['claim_amount'] ?? null,
                'claim_note' => $payload['claim_note'] ?? null,
                'resolution_type' => $payload['resolution_type'] ?? null,
            ],
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);
    }

    public function approveSupplierClaim(int $supplierClaimId): TextileWorkflowDocument
    {
        $claim = $this->findTenantDocument($supplierClaimId, 'supplier_claim');

        return $this->workflowService->transitionStatus($claim->id, 'approved');
    }

    public function settleSupplierClaim(int $supplierClaimId): TextileWorkflowDocument
    {
        $claim = $this->findTenantDocument($supplierClaimId, 'supplier_claim');

        return $this->workflowService->transitionStatus($claim->id, 'released');
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
            $materialType = $this->resolveProcurementMaterialType($updated);

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
                    'material_type' => $materialType,
                    'production_stage' => TextileLot::STAGE_PROCUREMENT,
                    'source_document_type' => $updated->document_type,
                    'source_document_id' => $updated->id,
                    'is_active' => true,
                ]
            );

            if (empty($lot->material_type)) {
                $lot->material_type = $materialType;
                $lot->production_stage = TextileLot::STAGE_PROCUREMENT;
                $lot->source_document_type = $updated->document_type;
                $lot->source_document_id = $updated->id;
            }

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

    private function resolveProcurementMaterialType(TextileWorkflowDocument $document): string
    {
        $current = $document;

        for ($depth = 0; $depth < 6; $depth++) {
            $metadata = is_array($current->metadata) ? $current->metadata : [];
            $requisitionType = $metadata['requisition_type'] ?? null;

            if (is_string($requisitionType) && $requisitionType !== '') {
                return match ($requisitionType) {
                    'yarn' => TextileLot::TYPE_YARN,
                    'beam' => TextileLot::TYPE_BEAM,
                    'grey_fabric' => TextileLot::TYPE_GREY_FABRIC,
                    'finished_fabric' => TextileLot::TYPE_FINISHED_FABRIC,
                    'chemical' => TextileLot::TYPE_CHEMICAL,
                    'packing_material' => TextileLot::TYPE_PACKING_MATERIAL,
                    default => TextileLot::TYPE_OTHER,
                };
            }

            if (! $current->source_reference_id || $current->source_reference_type !== 'textile_workflow_document') {
                break;
            }

            $parent = TextileWorkflowDocument::query()
                ->where('id', $current->source_reference_id)
                ->where('created_by', $current->created_by)
                ->first();

            if (! $parent) {
                break;
            }

            $current = $parent;
        }

        return TextileLot::TYPE_OTHER;
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

    /**
     * Resolve the users.id for a purchase invoice vendor.
     *
     * The textile requisition stores the Vendor.id (account vendors table).
     * Core PurchaseInvoice.vendor_id must reference a users.id, so:
     *  - if a Vendor row matches (by id or user_id), use its user_id;
     *  - when the Vendor has no linked user yet, firstOrCreate a vendor user
     *    exactly like QuotationController::customersForSelect() does for clients.
     */
    private function resolveVendorUserId(int $metadataVendorId, ?int $tenantId, ?int $creatorId): int
    {
        $tenantId = $tenantId ?: (auth()->check() && function_exists('creatorId') ? creatorId() : auth()->id());

        $vendor = null;
        if (class_exists(\Workdo\Account\Models\Vendor::class) && Schema::hasTable('vendors')) {
            $vendorQuery = \Workdo\Account\Models\Vendor::query()->where('created_by', $tenantId);

            if ($metadataVendorId > 0) {
                $vendorQuery->where(function ($query) use ($metadataVendorId) {
                    $query->where('id', $metadataVendorId)->orWhere('user_id', $metadataVendorId);
                });
            }

            $vendor = $vendorQuery->first();
        }

        if (! $vendor) {
            // No vendor profile found: fall back to the legacy behaviour where the
            // metadata vendor id was treated as a user id directly.
            return $metadataVendorId > 0 ? $metadataVendorId : (int) ($creatorId ?? $tenantId);
        }

        if ((int) $vendor->user_id > 0) {
            return (int) $vendor->user_id;
        }

        if (! class_exists(\App\Models\User::class)) {
            return 0;
        }

        $email = $vendor->contact_person_email
            ?: 'vendor' . $vendor->id . '@' . str_replace(['https://', 'http://'], '', (string) config('app.url'));

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => $vendor->company_name ?: ($vendor->contact_person_name ?: 'Vendor ' . $vendor->id),
                'password'          => \Illuminate\Support\Str::random(16),
                'type'              => 'vendor',
                'creator_id'        => $creatorId ?: $tenantId,
                'created_by'        => $tenantId,
                'lang'              => 'en',
                'email_verified_at' => now(),
            ]
        );

        $vendor->user_id = $user->id;
        $vendor->save();

        return (int) $user->id;
    }
}
