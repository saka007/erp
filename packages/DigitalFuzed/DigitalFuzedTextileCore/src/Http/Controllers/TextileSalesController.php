<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileCore\Services\TextileApprovalService;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextilePartyBranchService;
use DigitalFuzed\TextileCore\Services\TextileSalesService;
use DigitalFuzed\TextileCore\Support\TextileBranchScope;
use DigitalFuzed\TextileCore\Traits\ProvidesRecentActivity;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Customer;
use Workdo\Quotation\Models\SalesQuotation;

class TextileSalesController extends Controller
{
    use ProvidesRecentActivity;

    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('sales');

        return Inertia::render('DigitalFuzedTextileCore/Sales/Index', [
            'salesOrders' => $this->documents('sales_order'),
            'allocations' => $this->documents('allocation'),
            'dispatches' => $this->documents('dispatch'),
            'challans' => $this->documents('challan'),
            'pods' => $this->documents('pod'),
            'quotations' => $this->quotations(),
            'customers' => $this->customerOptions(),
            'warehouseOptions' => $this->warehouseOptions(),
            'sellableLotOptions' => $this->sellableLotOptions(),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'sourceActionOptions' => $this->sourceActionOptions(),
            'unitOptions' => $this->unitOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    public function storeSalesOrder(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_order', 'customer_id');

        $validated = $request->validate([
            'source_reference_type' => ['nullable', 'string', 'max:100'],
            'source_reference_id' => ['nullable', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'required_without:source_reference_id', 'integer', 'min:1'],
            'lot_selections' => ['nullable', 'required_without:source_reference_id', 'array', 'min:1'],
            'lot_selections.*.lot_reference' => ['required', 'string', 'max:100', 'distinct'],
            'lot_selections.*.quantity' => ['required', 'numeric', 'gt:0'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'required_with:source_reference_id', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'rate' => ['nullable', 'required_without:source_reference_id', 'numeric', 'gte:0'],
            'required_delivery_date' => ['nullable', 'required_without:source_reference_id', 'date'],
            'warehouse' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['customer_id']) && ! empty($validated['lot_selections'])) {
            $customer = Customer::query()->where('created_by', creatorId())->findOrFail((int) $validated['customer_id']);
            $validated['party_name'] = $customer->company_name;
            $validated['metadata'] = [
                'item_name' => 'Takha / Woven Fabric',
                'rate' => (float) $validated['rate'],
                'required_delivery_date' => $validated['required_delivery_date'],
                'warehouse' => $validated['warehouse'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ];
        }

        try {
            $service->createSalesOrder($validated);
        } catch (RuntimeException $exception) {
            $errorKey = ! empty($validated['source_reference_id']) ? 'source_reference_id' : 'customer_id';
            return back()->withErrors([$errorKey => __($exception->getMessage())]);
        }

        return back()->with('success', __('Sales order created successfully.'));
    }

    public function approveSalesOrder(Request $request, TextileSalesService $service, TextileApprovalService $approvalService)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_order', 'sales_order_id');

        $validated = $request->validate([
            'sales_order_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveSalesOrder((int) $validated['sales_order_id']);
        } catch (RuntimeException $exception) {
            // If an approval workflow is configured, record this actor's approval
            // decision and retry once so "Approve" works as a single-step action.
            if (str_contains($exception->getMessage(), 'Approval required before transition')) {
                try {
                    $approvalService->recordDecision(
                        (int) $validated['sales_order_id'],
                        'approved',
                        'approved',
                        'Recorded from Approve action.'
                    );

                    $service->approveSalesOrder((int) $validated['sales_order_id']);

                    return back()->with('success', __('Sales order approved successfully.'));
                } catch (RuntimeException $retryException) {
                    $message = __($retryException->getMessage());

                    return back()
                        ->withErrors(['sales_order_id' => $message])
                        ->with('error', $message);
                }
            }

            $message = __($exception->getMessage());

            return back()
                ->withErrors(['sales_order_id' => $message])
                ->with('error', $message);
        }

        return back()->with('success', __('Sales order approved successfully.'));
    }

    public function storeAllocation(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'sales_order_id');

        $validated = $request->validate([
            'sales_order_id' => ['required', 'integer', 'min:1'],
            'lot_allocations' => ['nullable', 'array', 'min:1'],
            'lot_allocations.*.lot_reference' => ['required', 'string', 'max:100', 'distinct'],
            'lot_allocations.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $salesOrderQuery = TextileWorkflowDocument::query()
            ->where('id', $validated['sales_order_id'])
            ->where('created_by', creatorId())
            ->where('document_type', 'sales_order');
        TextileBranchScope::applyWorkflowScope($salesOrderQuery);
        $salesOrder = $salesOrderQuery->firstOrFail();
        $salesOrderMetadata = is_array($salesOrder->metadata) ? $salesOrder->metadata : [];
        $validated['lot_allocations'] = $validated['lot_allocations'] ?? ($salesOrderMetadata['requested_lots'] ?? null);

        try {
            $service->createAllocation((int) $validated['sales_order_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sales_order_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Allocation created successfully.'));
    }

    public function releaseAllocation(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'allocation_id');

        $validated = $request->validate([
            'allocation_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseAllocation((int) $validated['allocation_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['allocation_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Allocation released successfully.'));
    }

    public function storeDispatch(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'allocation_id');

        $validated = $request->validate([
            'allocation_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createDispatch((int) $validated['allocation_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['allocation_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Dispatch created successfully.'));
    }

    public function releaseDispatch(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'dispatch_id');

        $validated = $request->validate([
            'dispatch_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseDispatch((int) $validated['dispatch_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['dispatch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Dispatch released successfully.'));
    }

    public function storeChallan(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_challan_pod', 'dispatch_id');

        $validated = $request->validate([
            'dispatch_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createChallan((int) $validated['dispatch_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['dispatch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Challan created successfully.'));
    }

    public function markPod(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_challan_pod', 'challan_id');

        $validated = $request->validate([
            'challan_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->markPod((int) $validated['challan_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['challan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('POD marked successfully.'));
    }

    private function documents(string $type)
    {
        $query = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type);

        TextileBranchScope::applyWorkflowScope($query);

        return $query->latest()->get();
    }

    private function quotations(): array
    {
        return SalesQuotation::query()
            ->where('created_by', creatorId())
            ->with('customer:id,company_name')
            ->latest()
            ->get()
            ->map(fn (SalesQuotation $q) => [
                'id' => $q->id,
                'quotation_number' => $q->quotation_number,
                'customer_name' => $q->customer?->company_name ?? '-',
                'quotation_date' => $q->quotation_date?->format('d M Y') ?? '-',
                'due_date' => $q->due_date?->format('d M Y') ?? '-',
                'quotation_type' => $q->quotation_type ?? 'general',
                'total_amount' => $q->total_amount,
                'status' => $q->status,
                'converted_to_invoice' => $q->converted_to_invoice,
            ])
            ->all();
    }

    private function customerOptions()
    {
        if (!Schema::hasTable('customers')) {
            return [];
        }

        return Customer::query()
            ->where('created_by', creatorId())
            ->orderBy('company_name')
            ->pipe(fn ($query) => TextilePartyBranchService::applyPartyScope($query, TextilePartyBranchService::PARTY_CUSTOMER, 'customers'))
            ->get(['id', 'company_name', 'operating_model', 'material_ownership', 'billing_mode', 'default_rate'])
            ->map(function (Customer $customer) {
                return [
                    'id' => (int) $customer->id,
                    'company_name' => $customer->company_name,
                    'operating_model' => $customer->operating_model,
                    'material_ownership' => $customer->material_ownership,
                    'billing_mode' => $customer->billing_mode,
                    'default_rate' => $customer->default_rate !== null ? (float) $customer->default_rate : null,
                ];
            })
            ->values();
    }

    private function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = \DB::table('warehouses')->where('created_by', creatorId());
        if (Schema::hasColumn('warehouses', 'branch_id') && session('active_branch_id')) {
            $query->where('branch_id', (int) session('active_branch_id'));
        }

        return $query->orderBy('name')->pluck('name')->map(fn ($name) => ['value' => (string) $name, 'label' => (string) $name])->values()->all();
    }

    private function sellableLotOptions(): array
    {
        if (! Schema::hasTable('textile_lots')) {
            return [];
        }

        $sourceQuery = TextileWorkflowDocument::query()->where('created_by', creatorId());
        TextileBranchScope::applyWorkflowScope($sourceQuery);
        $sourceDocuments = $sourceQuery->get(['id', 'party_name', 'unit', 'metadata', 'created_at'])->keyBy('id');

        $lotQuery = TextileLot::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->where('available_quantity', '>', 0)
            ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
            ->where('source_document_type', 'takha_entry')
            ->whereIn('source_document_id', $sourceDocuments->keys());

        // QC gate mirror: when the tenant operates quality inspection, only show
        // takha lots that have passed inspection so the picker matches the backend
        // gate instead of letting the user pick a lot that will be rejected.
        // Pass = an approved/released inspection document exists, or the lot is
        // marked quality-approved (production_stage) — the visible QC marker.
        if ($this->tenantOperatesQualityInspection()) {
            $inspectedQuery = TextileWorkflowDocument::query()
                ->where('created_by', creatorId())
                ->where('document_type', 'inspection')
                ->whereIn('status', ['approved', 'released']);
            TextileBranchScope::applyWorkflowScope($inspectedQuery);
            $lotQuery->where(function ($query) use ($inspectedQuery) {
                $query->whereIn('lot_reference', $inspectedQuery->pluck('lot_reference'))
                    ->orWhere('production_stage', TextileLot::STAGE_QUALITY_APPROVED);
            });
        }

        return $lotQuery->latest('created_at')
            ->get()
            ->map(function (TextileLot $lot) use ($sourceDocuments) {
                $source = $sourceDocuments->get($lot->source_document_id);
                $metadata = is_array($source?->metadata) ? $source->metadata : [];
                $quantity = rtrim(rtrim(number_format((float) $lot->available_quantity, 2, '.', ''), '0'), '.');
                $details = array_filter([
                    $quantity . ' available',
                    $metadata['grade'] ?? null,
                    isset($metadata['gsm']) ? $metadata['gsm'] . ' GSM' : null,
                    isset($metadata['width']) ? $metadata['width'] . ' width' : null,
                    $metadata['warehouse'] ?? null,
                    $source?->created_at?->format('d M Y'),
                ]);

                return [
                    'value' => $lot->lot_reference,
                    'label' => $lot->lot_reference . ' (' . implode(' | ', $details) . ')',
                    'available_quantity' => $quantity,
                    'material_type' => $lot->material_type,
                    'unit' => (string) ($source?->unit ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    private function tenantOperatesQualityInspection(): bool
    {
        try {
            $this->policyService->assertCapability('quality_inspection');

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function sourceTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultSourceTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('source_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('sales');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceTypeOptions();
    }

    private function defaultSourceTypeOptions(): array
    {
        return [
            'sales_quotation',
            'sales_order',
            'customer_contract',
        ];
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

    private function sourceActionOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultSourceActionOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('source_action')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('sales');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceActionOptions();
    }

    private function defaultSourceActionOptions(): array
    {
        return [
            'convert',
            'allocate_for_dispatch',
            'dispatch_release',
            'generate_challan',
            'proof_of_delivery',
        ];
    }

    private function partyOptions(): array
    {
        $customers = collect();
        if (Schema::hasTable('customers')) {
            $customers = Customer::query()
                ->where('created_by', creatorId())
                ->whereNotNull('company_name')
                ->pipe(fn ($query) => TextilePartyBranchService::applyPartyScope($query, TextilePartyBranchService::PARTY_CUSTOMER, 'customers'))
                ->pluck('company_name');
        }

        $workflowPartiesQuery = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name');

        TextileBranchScope::applyWorkflowScope($workflowPartiesQuery);

        $workflowParties = $workflowPartiesQuery->pluck('party_name');

        return $customers
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

        $workflowLotsQuery = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('lot_reference');

        TextileBranchScope::applyWorkflowScope($workflowLotsQuery);

        $workflowLots = $workflowLotsQuery->pluck('lot_reference');

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
