<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;

class TextileInvoiceController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    /**
     * Textile Invoice Hub — one page for all invoice types (sales, purchase,
     * job work) following the shared textile workspace layout.
     */
    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('invoices');

        $tenantId = creatorId();

        return Inertia::render('DigitalFuzedTextileCore/Invoices/Index', [
            'salesInvoices' => $this->salesInvoices($tenantId),
            'purchaseInvoices' => $this->purchaseInvoices($tenantId),
            'jobWorkInvoices' => $this->jobWorkInvoices($tenantId),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    private function salesInvoices(int $tenantId): array
    {
        if (! class_exists(SalesInvoice::class) || ! Schema::hasTable('sales_invoices')) {
            return [];
        }

        $invoices = SalesInvoice::query()
            ->with(['customer:id,name', 'customerDetails:id,user_id,company_name'])
            ->where('created_by', $tenantId)
            ->where('type', '!=', 'service')
            ->latest('invoice_date')
            ->get();

        return $invoices->map(fn ($invoice) => $this->formatSalesInvoice($invoice, 'sales'))->values()->all();
    }

    private function purchaseInvoices(int $tenantId): array
    {
        if (! class_exists(PurchaseInvoice::class) || ! Schema::hasTable('purchase_invoices')) {
            return [];
        }

        $invoices = PurchaseInvoice::query()
            ->with(['vendor:id,name', 'vendorDetails:id,user_id,company_name'])
            ->where('created_by', $tenantId)
            ->latest('invoice_date')
            ->get();

        return $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'kind' => 'purchase',
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => optional($invoice->invoice_date)->format('Y-m-d'),
                'due_date' => optional($invoice->due_date)->format('Y-m-d'),
                'party_name' => $invoice->vendorDetails?->company_name ?? $invoice->vendor?->name,
                'party_id' => $invoice->vendor_id,
                'subtotal' => (float) $invoice->subtotal,
                'tax_amount' => (float) $invoice->tax_amount,
                'discount_amount' => (float) $invoice->discount_amount,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance_amount' => (float) $invoice->balance_amount,
                'status' => $invoice->display_status ?? $invoice->status,
                'item_count' => $invoice->items_count ?? 0,
                'payment_terms' => $invoice->payment_terms,
                'notes' => $invoice->notes,
            ];
        })->values()->all();
    }

    /**
     * Job-work invoices are modelled as `type = service` sales invoices
     * (the core SalesInvoiceController::getServices() returns ProductServiceItem
     * rows with type 'service').
     */
    private function jobWorkInvoices(int $tenantId): array
    {
        if (! class_exists(SalesInvoice::class) || ! Schema::hasTable('sales_invoices')) {
            return [];
        }

        $invoices = SalesInvoice::query()
            ->with(['customer:id,name', 'customerDetails:id,user_id,company_name'])
            ->where('created_by', $tenantId)
            ->where('type', 'service')
            ->latest('invoice_date')
            ->get();

        return $invoices->map(fn ($invoice) => $this->formatSalesInvoice($invoice, 'job-work'))->values()->all();
    }

    private function formatSalesInvoice($invoice, string $kind = 'sales'): array
    {
        return [
            'id' => $invoice->id,
            'kind' => $kind,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => optional($invoice->invoice_date)->format('Y-m-d'),
            'due_date' => optional($invoice->due_date)->format('Y-m-d'),
            'type' => $invoice->type ?? 'product',
            'party_name' => $invoice->customerDetails?->company_name ?? $invoice->customer?->name,
            'party_id' => $invoice->customer_id,
            'subtotal' => (float) $invoice->subtotal,
            'tax_amount' => (float) $invoice->tax_amount,
            'discount_amount' => (float) $invoice->discount_amount,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'balance_amount' => (float) $invoice->balance_amount,
            'status' => $invoice->display_status ?? $invoice->status,
            'item_count' => $invoice->items_count ?? 0,
            'payment_terms' => $invoice->payment_terms,
            'notes' => $invoice->notes,
        ];
    }

    private function statusOptions(): array
    {
        return ['draft', 'posted', 'partial', 'paid', 'cancelled', 'overdue'];
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
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
