<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextileProcurementService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Vendor;

class TextileProcurementController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('procurement');

        return Inertia::render('DigitalFuzedTextileCore/Procurement/Index', [
            'requisitions' => $this->documents('purchase_requisition'),
            'purchaseOrders' => $this->documents('purchase_order'),
            'grns' => $this->documents('grn'),
            'incomingQcs' => $this->documents('incoming_qc'),
            'unitOptions' => $this->unitOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
        ]);
    }

    public function storeRequisition(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement', 'party_name');

        $validated = $request->validate([
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        $service->createRequisition($validated);

        return back()->with('success', __('Purchase requisition created successfully.'));
    }

    public function approveRequisition(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement', 'requisition_id');

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

    public function storePurchaseOrder(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement', 'requisition_id');

        $validated = $request->validate([
            'requisition_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createPurchaseOrder((int) $validated['requisition_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['requisition_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Purchase order created successfully.'));
    }

    public function approvePurchaseOrder(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement', 'purchase_order_id');

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approvePurchaseOrder((int) $validated['purchase_order_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['purchase_order_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Purchase order approved successfully.'));
    }

    public function storeGrn(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement', 'purchase_order_id');

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createGrn((int) $validated['purchase_order_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['purchase_order_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('GRN created successfully.'));
    }

    public function releaseGrn(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('procurement', 'grn_id');

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
        $this->authorizeCapability('procurement', 'grn_id');

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
        $this->authorizeCapability('procurement', 'incoming_qc_id');

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

    private function documents(string $type)
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type)
            ->latest()
            ->get()
            ->map(function (TextileWorkflowDocument $document) {
                $metadata = is_array($document->metadata) ? $document->metadata : [];
                $document->purchase_invoice_id = $metadata['purchase_invoice_id'] ?? null;

                return $document;
            });
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

    private function partyOptions(): array
    {
        $vendors = collect();
        if (Schema::hasTable('vendors')) {
            $vendors = Vendor::query()
                ->where('created_by', creatorId())
                ->whereNotNull('company_name')
                ->pluck('company_name');
        }

        $workflowParties = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name')
            ->pluck('party_name');

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
            ->whereNotNull('lot_reference')
            ->pluck('lot_reference');

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

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
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
