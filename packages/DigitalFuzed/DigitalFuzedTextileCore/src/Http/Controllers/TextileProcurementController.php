<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use App\Http\Controllers\Concerns\HasBranchWarehouseScope;
use App\Models\PurchaseInvoice;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileApprovalService;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextileProcurementService;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileCore\Traits\ProvidesRecentActivity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Vendor;

class TextileProcurementController extends Controller
{
    use HasBranchWarehouseScope;
    use ProvidesRecentActivity;

    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('procurement');

        return Inertia::render('DigitalFuzedTextileCore/Procurement/Index', [
            'requisitions' => $this->documents('purchase_requisition'),
            'rfqs' => $this->documents('rfq'),
            'purchaseOrders' => $this->documents('purchase_order'),
            'grns' => $this->documents('grn'),
            'incomingQcs' => $this->documents('incoming_qc'),
            'supplierClaims' => $this->documents('supplier_claim'),
            'purchaseInvoices' => $this->purchaseInvoices(),
            'unitOptions' => $this->unitOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
            'suppliers' => $this->supplierSummaries(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    public function storeRequisition(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_requisition', 'party_name');

        $validated = $request->validate([
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'requisition_type' => ['nullable', 'in:yarn,beam,grey_fabric,finished_fabric,chemical,packing_material,spare_part,service,general'],
            'priority' => ['nullable', 'string', 'max:20'],
            'required_for' => ['nullable', 'string', 'max:100'],
            'expected_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'warehouse' => ['nullable', 'string', 'max:100'],
        ]);

        $service->createRequisition(array_merge($validated, [
            'metadata' => [
                'requisition_type' => $validated['requisition_type'] ?? 'general',
                'priority' => $validated['priority'] ?? null,
                'required_for' => $validated['required_for'] ?? null,
                'expected_date' => $validated['expected_date'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'warehouse' => $validated['warehouse'] ?? null,
            ],
        ]));

        return back()->with('success', __('Purchase requisition created successfully.'));
    }

    public function approveRequisition(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_requisition', 'requisition_id');

        $validated = $request->validate([
            'requisition_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveRequisition((int) $validated['requisition_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['requisition_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Purchase requisition approved successfully.'));
    }

    public function destroyRequisition(int $requisition, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_requisition', 'requisition_id');

        try {
            $service->deleteRequisition($requisition);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['requisition_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Purchase requisition deleted successfully.'));
    }

    public function storeRfq(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_rfq', 'requisition_id');

        $validated = $request->validate([
            'requisition_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createRfq((int) $validated['requisition_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['requisition_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('RFQ created successfully.'));
    }

    public function sendRfq(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_rfq', 'rfq_id');

        $validated = $request->validate([
            'rfq_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->sendRfq((int) $validated['rfq_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['rfq_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('RFQ sent successfully.'));
    }

    public function closeRfq(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_rfq', 'rfq_id');

        $validated = $request->validate([
            'rfq_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->closeRfq((int) $validated['rfq_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['rfq_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('RFQ closed successfully.'));
    }

    public function storePurchaseOrder(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_purchase_order', 'requisition_id');

        $validated = $request->validate([
            'source_type' => ['nullable', 'in:requisition,rfq'],
            'source_id' => ['required', 'integer', 'min:1'],
        ]);

        $sourceType = $validated['source_type'] ?? 'requisition';

        $requisitionId = $sourceType === 'requisition' ? (int) $validated['source_id'] : null;
        $rfqId = $sourceType === 'rfq' ? (int) $validated['source_id'] : null;

        try {
            $service->createPurchaseOrder($requisitionId, $rfqId);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Purchase order created successfully.'));
    }

    public function approvePurchaseOrder(Request $request, TextileProcurementService $service, TextileApprovalService $approvalService)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_purchase_order', 'purchase_order_id');

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approvePurchaseOrder((int) $validated['purchase_order_id']);
        } catch (RuntimeException $exception) {
            // If approval workflow is enabled, record this actor's approval decision
            // and retry once so "Send Proforma" works as a single-step action.
            if (str_contains($exception->getMessage(), 'Approval required before transition')) {
                try {
                    $approvalService->recordDecision(
                        (int) $validated['purchase_order_id'],
                        'approved',
                        'approved',
                        'Recorded from Send Proforma action.'
                    );

                    $service->approvePurchaseOrder((int) $validated['purchase_order_id']);

                    return back()->with('success', __('Purchase order approved successfully.'));
                } catch (RuntimeException $retryException) {
                    $message = __($retryException->getMessage());

                    return back()
                        ->withErrors(['purchase_order_id' => $message])
                        ->with('error', $message);
                }
            }

            $message = __($exception->getMessage());

            return back()
                ->withErrors(['purchase_order_id' => $message])
                ->with('error', $message);
        }

        return back()->with('success', __('Purchase order approved successfully.'));
    }

    public function storeGrn(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_grn', 'purchase_order_id');

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'min:1'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $service->createGrn((int) $validated['purchase_order_id'], [
                'lot_reference' => $validated['lot_reference'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['purchase_order_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('GRN created successfully.'));
    }

    public function releaseGrn(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_grn', 'grn_id');

        $validated = $request->validate([
            'grn_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseGrn((int) $validated['grn_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['grn_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('GRN released successfully.'));
    }

    public function createPurchaseInvoiceFromGrn(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('grn_invoice_sync', 'grn_id');

        $validated = $request->validate([
            'grn_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $grn = $service->createPurchaseInvoiceFromGrn((int) $validated['grn_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['grn_id' => __($exception->getMessage())]);
        }

        if (!((bool) $grn->getAttribute('purchase_invoice_created_now'))) {
            return back()->with('success', __('Purchase invoice was already synced for this GRN.'));
        }

        return back()->with('success', __('Draft purchase invoice synced from GRN successfully.'));
    }

    public function storeIncomingQc(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_incoming_qc', 'grn_id');

        $validated = $request->validate([
            'grn_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createIncomingQc((int) $validated['grn_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['grn_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Incoming QC record created successfully.'));
    }

    public function finalizeIncomingQc(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_incoming_qc', 'incoming_qc_id');

        $validated = $request->validate([
            'incoming_qc_id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:pass,fail'],
        ]);

        try {
            $service->finalizeIncomingQc((int) $validated['incoming_qc_id'], $validated['decision']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['incoming_qc_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Incoming QC finalized successfully.'));
    }

    public function storeSupplierClaim(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_supplier_claims', 'grn_id');

        $validated = $request->validate([
            'grn_id' => ['required', 'integer', 'min:1'],
            'claim_type' => ['required', 'in:quality,quantity,damage,delay,rate_difference'],
            'claim_amount' => ['required', 'numeric', 'gte:0'],
            'resolution_type' => ['required', 'in:replacement,credit_note,debit_adjustment,return_to_vendor'],
            'claim_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->createSupplierClaim((int) $validated['grn_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['grn_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Supplier claim created successfully.'));
    }

    public function approveSupplierClaim(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_supplier_claims', 'supplier_claim_id');

        $validated = $request->validate([
            'supplier_claim_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveSupplierClaim((int) $validated['supplier_claim_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['supplier_claim_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Supplier claim approved successfully.'));
    }

    public function settleSupplierClaim(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement_supplier_claims', 'supplier_claim_id');

        $validated = $request->validate([
            'supplier_claim_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->settleSupplierClaim((int) $validated['supplier_claim_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['supplier_claim_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Supplier claim settled successfully.'));
    }

    private function documents(string $type)
    {
        $query = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type);

        TextileBranchScope::applyWorkflowScope($query);

        return $query->latest()
            ->get()
            ->map(function (TextileWorkflowDocument $document) {
                $metadata = is_array($document->metadata) ? $document->metadata : [];
                $document->purchase_invoice_id = $metadata['purchase_invoice_id'] ?? null;

                return $document;
            });
    }

    private function purchaseInvoices(): array
    {
        $user = Auth::user();

        if (! $user || ! $user->can('manage-purchase-invoices')) {
            return [];
        }

        $query = PurchaseInvoice::query()
            ->where('created_by', creatorId())
            ->with('vendor:id,name')
            ->when($user->can('manage-own-purchase-invoices') && ! $user->can('manage-any-purchase-invoices'), function ($q) use ($user) {
                $q->where(function ($scope) use ($user) {
                    $scope->where('creator_id', $user->id)
                        ->orWhere('vendor_id', $user->id);
                });

                if ($user->type === 'vendor') {
                    $q->where('status', '!=', 'draft');
                }
            })
            ->when(! $user->can('manage-own-purchase-invoices') && ! $user->can('manage-any-purchase-invoices'), fn ($q) => $q->whereRaw('1 = 0'));

        $this->applyWarehouseScope($query, 'warehouse_id', $user);

        return $query
            ->latest()
            ->get()
            ->map(fn (PurchaseInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'vendor_name' => $invoice->vendor?->name ?? '-',
                'invoice_date' => $invoice->invoice_date?->format('d M Y') ?? '-',
                'due_date' => $invoice->due_date?->format('d M Y') ?? '-',
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'balance_amount' => $invoice->balance_amount,
                'status' => $invoice->display_status,
            ])
            ->all();
    }

    private function unitOptions(): array
    {
        return TextileUnitConversion::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->get(['from_unit', 'to_unit'])
            ->flatMap(fn ($row) => [$row->from_unit, $row->to_unit])
            ->filter(fn ($unit) => is_string($unit) && trim($unit) !== '')
            ->map(fn ($unit) => trim((string) $unit))
            ->unique()
            ->values()
            ->all();
    }

    private function supplierSummaries(): array
    {
        $statsQuery = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name')
            ->selectRaw('party_name, count(*) as doc_count, sum(quantity) as total_quantity, max(created_at) as last_purchase_at')
            ->groupBy('party_name');

        TextileBranchScope::applyWorkflowScope($statsQuery);

        $stats = $statsQuery
            ->get()
            ->keyBy('party_name');

        return Vendor::query()
            ->where('created_by', creatorId())
            ->whereNotNull('company_name')
            ->get()
            ->map(function (Vendor $vendor) use ($stats) {
                $vendorStats = $stats->get($vendor->company_name);

                return [
                    'id' => $vendor->id,
                    'name' => $vendor->company_name,
                    'contact_person' => $vendor->contact_person_name,
                    'contact_mobile' => $vendor->contact_person_mobile,
                    'primary_email' => $vendor->primary_email,
                    'credit_limit' => $vendor->credit_limit,
                    'payment_terms' => $vendor->payment_terms,
                    'currency_code' => $vendor->currency_code,
                    'doc_count' => $vendorStats?->doc_count ?? 0,
                    'total_quantity' => $vendorStats?->total_quantity ?? 0,
                    'last_purchase_at' => $vendorStats?->last_purchase_at,
                ];
            })
            ->sortByDesc('doc_count')
            ->values()
            ->all();
    }

    private function partyOptions(): array
    {
        $vendors = collect();
        if (Schema::hasTable('vendors')) {
            $vendors = Vendor::query()
                ->where('created_by', creatorId())
                ->whereNotNull('company_name')
                ->pluck('company_name');
        }

        $workflowQuery = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name');

        TextileBranchScope::applyWorkflowScope($workflowQuery);

        $workflowParties = $workflowQuery->pluck('party_name');

        return $vendors
            ->merge($workflowParties)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function lotReferenceOptions(): array
    {
        $lots = collect();
        if (Schema::hasTable('textile_lots')) {
            $lots = TextileLot::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->pluck('lot_reference');
        }

        $workflowLots = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('lot_reference');

        TextileBranchScope::applyWorkflowScope($workflowLots);

        $workflowLots = $workflowLots->pluck('lot_reference');

        return $lots
            ->merge($workflowLots)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
    }

    private function authorizeCapability(string $capability, string $errorKey): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => __($exception->getMessage()),
            ]);
        }
    }

    private function authorizeCapabilityOrAbort(string $capability): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            abort(403, __($exception->getMessage()));
        }
    }
}
