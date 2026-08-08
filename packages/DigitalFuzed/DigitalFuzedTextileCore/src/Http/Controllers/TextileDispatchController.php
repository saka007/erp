<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileDispatchService;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextilePartyBranchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Vendor;

class TextileDispatchController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('sales_allocation_dispatch');

        return Inertia::render('DigitalFuzedTextileCore/Dispatch/Index', [
            'dispatchPlans' => $this->documents('dispatch_plan'),
            'dispatchTrackings' => $this->documents('dispatch_tracking'),
            'challans' => $this->documents('challan')->filter(fn (TextileWorkflowDocument $row) => in_array($row->status, ['released', 'closed'], true))->values(),
            'pods' => $this->documents('pod'),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'sourceActionOptions' => $this->sourceActionOptions(),
            'dispatchModeOptions' => $this->dispatchModeOptions(),
            'trackingStatusOptions' => $this->trackingStatusOptions(),
            'truckNumberOptions' => $this->truckNumberOptions(),
            'containerNumberOptions' => $this->containerNumberOptions(),
            'vehicleOptions' => $this->vehicleOptions(),
            'driverOptions' => $this->driverOptions(),
            'routeOptions' => $this->routeOptions(),
            'transportVendorOptions' => $this->transportVendorOptions(),
            'lrNumberOptions' => $this->lrNumberOptions(),
            'ewayBillOptions' => $this->ewayBillOptions(),
        ]);
    }

    public function storeDispatchPlan(Request $request, TextileDispatchService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'challan_id');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', Rule::in($this->sourceTypeOptions())],
            'source_action' => ['required', 'string', Rule::in($this->sourceActionOptions())],
            'challan_id' => ['required', 'integer', 'min:1'],
            'dispatch_mode' => ['required', 'string', Rule::in($this->dispatchModeOptions())],
            'truck_number' => ['nullable', 'string', Rule::in($this->truckNumberOptions())],
            'container_number' => ['nullable', 'string', Rule::in($this->containerNumberOptions())],
            'driver_id' => ['nullable', 'integer', Rule::in($this->driverIds())],
            'vehicle_id' => ['nullable', 'integer', Rule::in($this->vehicleIds())],
            'route_id' => ['nullable', 'integer', Rule::in($this->routeIds())],
            'transport_vendor_id' => ['nullable', 'integer', Rule::in($this->transportVendorIds())],
            'lr_number' => ['nullable', 'string', Rule::in($this->lrNumberOptions())],
            'eway_bill_number' => ['nullable', 'string', Rule::in($this->ewayBillOptions())],
            'freight_amount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (!empty($validated['driver_id'])) {
            $driver = TextileDispatchDriver::query()->where('created_by', creatorId())->where('is_active', true)->findOrFail((int) $validated['driver_id']);
            $validated['driver_name'] = $driver->name;
        }

        if (!empty($validated['vehicle_id'])) {
            $vehicle = TextileDispatchVehicle::query()->where('created_by', creatorId())->where('is_active', true)->findOrFail((int) $validated['vehicle_id']);
            $validated['vehicle_number'] = $vehicle->vehicle_number;
        }

        if (!empty($validated['route_id'])) {
            $route = TextileDispatchRoute::query()->where('created_by', creatorId())->where('is_active', true)->findOrFail((int) $validated['route_id']);
            $validated['route_name'] = $route->route_name;
        }

        if (!empty($validated['transport_vendor_id'])) {
            $vendor = Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id']);
            $validated['transport_vendor_name'] = $vendor->company_name;
        }

        try {
            $service->createDispatchPlan($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['challan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Dispatch plan created successfully.'));
    }

    public function approveDispatchPlan(Request $request, TextileDispatchService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'dispatch_plan_id');

        $validated = $request->validate([
            'dispatch_plan_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveDispatchPlan((int) $validated['dispatch_plan_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['dispatch_plan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Dispatch plan approved successfully.'));
    }

    public function storeDispatchTracking(Request $request, TextileDispatchService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'dispatch_plan_id');

        $validated = $request->validate([
            'dispatch_plan_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['required', 'string', Rule::in($this->sourceActionOptions())],
            'tracking_status' => ['required', 'string', Rule::in($this->trackingStatusOptions())],
            'current_location' => ['nullable', 'string', 'max:150'],
            'vehicle_id' => ['nullable', 'integer', Rule::in($this->vehicleIds())],
            'driver_id' => ['nullable', 'integer', Rule::in($this->driverIds())],
            'route_id' => ['nullable', 'integer', Rule::in($this->routeIds())],
            'transport_vendor_id' => ['nullable', 'integer', Rule::in($this->transportVendorIds())],
            'lr_number' => ['nullable', 'string', Rule::in($this->lrNumberOptions())],
            'eway_bill_number' => ['nullable', 'string', Rule::in($this->ewayBillOptions())],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (!empty($validated['driver_id'])) {
            $driver = TextileDispatchDriver::query()->where('created_by', creatorId())->where('is_active', true)->findOrFail((int) $validated['driver_id']);
            $validated['driver_name'] = $driver->name;
        }

        if (!empty($validated['vehicle_id'])) {
            $vehicle = TextileDispatchVehicle::query()->where('created_by', creatorId())->where('is_active', true)->findOrFail((int) $validated['vehicle_id']);
            $validated['vehicle_number'] = $vehicle->vehicle_number;
        }

        if (!empty($validated['route_id'])) {
            $route = TextileDispatchRoute::query()->where('created_by', creatorId())->where('is_active', true)->findOrFail((int) $validated['route_id']);
            $validated['route_name'] = $route->route_name;
        }

        if (!empty($validated['transport_vendor_id'])) {
            $vendor = Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id']);
            $validated['transport_vendor_name'] = $vendor->company_name;
        }

        try {
            $service->createDispatchTracking($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['dispatch_plan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Dispatch tracking updated successfully.'));
    }

    public function finalizeDispatchTracking(Request $request, TextileDispatchService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('sales_allocation_dispatch', 'tracking_id');

        $validated = $request->validate([
            'tracking_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->finalizeDispatchTracking((int) $validated['tracking_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['tracking_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Dispatch tracking finalized successfully.'));
    }

    private function documents(string $type)
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type)
            ->latest()
            ->get();
    }

    private function sourceTypeOptions(): array
    {
        return $this->referenceOptions('source_type', ['dispatch_plan', 'challan_reference', 'transport_manifest']);
    }

    private function sourceActionOptions(): array
    {
        return $this->referenceOptions('source_action', ['dispatch_plan', 'vehicle_assign', 'tracking_update']);
    }

    private function dispatchModeOptions(): array
    {
        return ['truck', 'container'];
    }

    private function trackingStatusOptions(): array
    {
        return ['planned', 'in_transit', 'delayed', 'delivered'];
    }

    private function truckNumberOptions(): array
    {
        return $this->referenceOptions('dispatch_truck_number');
    }

    private function containerNumberOptions(): array
    {
        return $this->referenceOptions('dispatch_container_number');
    }

    private function vehicleOptions(): array
    {
        return TextileDispatchVehicle::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->orderBy('vehicle_number')
            ->get(['id', 'vehicle_number', 'vehicle_type'])
            ->map(fn (TextileDispatchVehicle $row) => [
                'id' => (int) $row->id,
                'label' => trim($row->vehicle_number . ($row->vehicle_type ? ' | ' . $row->vehicle_type : '')),
            ])
            ->values()
            ->all();
    }

    private function driverOptions(): array
    {
        return TextileDispatchDriver::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (TextileDispatchDriver $row) => [
                'id' => (int) $row->id,
                'label' => trim($row->name . ($row->phone ? ' | ' . $row->phone : '')),
            ])
            ->values()
            ->all();
    }

    private function routeOptions(): array
    {
        return TextileDispatchRoute::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->orderBy('route_name')
            ->get(['id', 'route_name', 'origin_location', 'destination_location'])
            ->map(fn (TextileDispatchRoute $row) => [
                'id' => (int) $row->id,
                'label' => trim($row->route_name . (($row->origin_location || $row->destination_location) ? ' | ' . trim(($row->origin_location ?? '-') . ' -> ' . ($row->destination_location ?? '-')) : '')),
            ])
            ->values()
            ->all();
    }

    private function driverIds(): array
    {
        return TextileDispatchDriver::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function vehicleIds(): array
    {
        return TextileDispatchVehicle::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function routeIds(): array
    {
        return TextileDispatchRoute::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function transportVendorOptions(): array
    {
        if (!Schema::hasTable('vendors')) {
            return [];
        }

        return Vendor::query()
            ->where('created_by', creatorId())
            ->where('supplier_type', 'transport')
            ->orderBy('company_name')
            ->pipe(fn ($query) => TextilePartyBranchService::applyPartyScope($query, TextilePartyBranchService::PARTY_VENDOR, 'vendors'))
            ->get(['id', 'vendor_code', 'company_name'])
            ->map(fn (Vendor $vendor) => [
                'id' => (int) $vendor->id,
                'label' => trim(($vendor->vendor_code ? $vendor->vendor_code . ' | ' : '') . $vendor->company_name),
            ])
            ->values()
            ->all();
    }

    private function transportVendorIds(): array
    {
        if (!Schema::hasTable('vendors')) {
            return [];
        }

        return Vendor::query()
            ->where('created_by', creatorId())
            ->where('supplier_type', 'transport')
            ->pipe(fn ($query) => TextilePartyBranchService::applyPartyScope($query, TextilePartyBranchService::PARTY_VENDOR, 'vendors'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function lrNumberOptions(): array
    {
        return $this->referenceOptions('dispatch_lr_number');
    }

    private function ewayBillOptions(): array
    {
        return $this->referenceOptions('dispatch_eway_bill');
    }

    private function referenceOptions(string $masterType, array $fallback = []): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $fallback;
        }

        $query = TextileReferenceMaster::query()
            ->type($masterType)
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('dispatch');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $fallback;
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
