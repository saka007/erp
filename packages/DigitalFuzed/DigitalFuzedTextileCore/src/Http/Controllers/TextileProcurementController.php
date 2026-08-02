<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileProcurementService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use RuntimeException;

class TextileProcurementController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Procurement/Index', [
            'requisitions' => $this->documents('purchase_requisition'),
            'purchaseOrders' => $this->documents('purchase_order'),
            'grns' => $this->documents('grn'),
            'incomingQcs' => $this->documents('incoming_qc'),
        ]);
    }

    public function storeRequisition(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();

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

    public function storeIncomingQc(Request $request, TextileProcurementService $service)
    {
        $this->authorizeTextileAccess();

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
            ->get();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
