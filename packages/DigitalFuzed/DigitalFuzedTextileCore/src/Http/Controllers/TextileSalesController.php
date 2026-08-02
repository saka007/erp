<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileCore\Services\TextileSalesService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Customer;

class TextileSalesController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Sales/Index', [
            'salesOrders' => $this->documents('sales_order'),
            'allocations' => $this->documents('allocation'),
            'dispatches' => $this->documents('dispatch'),
            'challans' => $this->documents('challan'),
            'pods' => $this->documents('pod'),
            'customers' => $this->customerOptions(),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'sourceActionOptions' => $this->sourceActionOptions(),
            'unitOptions' => $this->unitOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
        ]);
    }

    public function storeSalesOrder(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createSalesOrder($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Sales order created successfully.'));
    }

    public function approveSalesOrder(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'sales_order_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveSalesOrder((int) $validated['sales_order_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sales_order_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Sales order approved successfully.'));
    }

    public function storeAllocation(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'sales_order_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createAllocation((int) $validated['sales_order_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sales_order_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Allocation created successfully.'));
    }

    public function releaseAllocation(Request $request, TextileSalesService $service)
    {
        $this->authorizeTextileAccess();

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
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type)
            ->latest()
            ->get();
    }

    private function customerOptions()
    {
        if (!Schema::hasTable('customers')) {
            return [];
        }

        return Customer::query()
            ->where('created_by', creatorId())
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'operating_model', 'material_ownership', 'billing_mode'])
            ->map(function (Customer $customer) {
                return [
                    'id' => (int) $customer->id,
                    'company_name' => $customer->company_name,
                    'operating_model' => $customer->operating_model,
                    'material_ownership' => $customer->material_ownership,
                    'billing_mode' => $customer->billing_mode,
                ];
            })
            ->values();
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
                ->pluck('company_name');
        }

        $workflowParties = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name')
            ->pluck('party_name');

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
}
