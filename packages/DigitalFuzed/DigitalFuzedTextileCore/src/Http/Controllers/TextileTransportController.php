<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use DigitalFuzed\TextileCore\Models\TextileFuelEntry;
use DigitalFuzed\TextileCore\Models\TextileFreightCost;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileVehicleMaintenance;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextilePartyBranchService;
use DigitalFuzed\TextileCore\Services\TextileTransportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;

class TextileTransportController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('transport_operations');

        return Inertia::render('DigitalFuzedTextileCore/Transport/Index', [
            'fuelEntries' => TextileFuelEntry::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'freightCosts' => TextileFreightCost::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'vehicleMaintenances' => TextileVehicleMaintenance::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'vehicleOptions' => $this->vehicleOptions(),
            'driverOptions' => $this->driverOptions(),
            'routeOptions' => $this->routeOptions(),
            'transportVendorOptions' => $this->transportVendorOptions(),
            'fuelTypeOptions' => $this->referenceOptions('transport_fuel_type', ['diesel', 'petrol', 'cng']),
            'freightTypeOptions' => $this->referenceOptions('transport_freight_type', ['per_trip', 'per_ton', 'per_km']),
            'maintenanceTypeOptions' => $this->referenceOptions('transport_maintenance_type', ['oil_change', 'tire_replacement', 'engine_service', 'brake_service', 'general_service']),
        ]);
    }

    public function storeFuelEntry(Request $request, TextileTransportService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('transport_operations', 'fuel_quantity_liters');

        $validated = $request->validate([
            'entry_code' => ['nullable', 'string', 'max:50'],
            'fuel_date' => ['required', 'date'],
            'vehicle_id' => ['nullable', 'integer', Rule::in($this->vehicleIds())],
            'driver_id' => ['nullable', 'integer', Rule::in($this->driverIds())],
            'route_id' => ['nullable', 'integer', Rule::in($this->routeIds())],
            'fuel_quantity_liters' => ['required', 'numeric', 'gte:0'],
            'fuel_rate_per_liter' => ['required', 'numeric', 'gte:0'],
            'odometer_km' => ['nullable', 'numeric', 'gte:0'],
            'fuel_type' => ['nullable', 'string', Rule::in($this->referenceOptions('transport_fuel_type', ['diesel', 'petrol', 'cng']))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['fuel_total_cost'] = round((float) $validated['fuel_quantity_liters'] * (float) $validated['fuel_rate_per_liter'], 2);

        $service->saveFuelEntry($validated);

        return back()->with('success', __('Fuel entry saved successfully.'));
    }

    public function storeFreightCost(Request $request, TextileTransportService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('transport_operations', 'amount');

        $validated = $request->validate([
            'cost_code' => ['nullable', 'string', 'max:50'],
            'freight_date' => ['required', 'date'],
            'vehicle_id' => ['nullable', 'integer', Rule::in($this->vehicleIds())],
            'driver_id' => ['nullable', 'integer', Rule::in($this->driverIds())],
            'route_id' => ['nullable', 'integer', Rule::in($this->routeIds())],
            'transport_vendor_id' => ['nullable', 'integer', Rule::in($this->transportVendorIds())],
            'freight_type' => ['nullable', 'string', Rule::in($this->referenceOptions('transport_freight_type', ['per_trip', 'per_ton', 'per_km']))],
            'amount' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!empty($validated['transport_vendor_id'])) {
            $vendor = Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id']);
            $validated['transport_vendor_name'] = $vendor->company_name;
        }

        $service->saveFreightCost($validated);

        return back()->with('success', __('Freight cost saved successfully.'));
    }

    public function storeVehicleMaintenance(Request $request, TextileTransportService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('transport_operations', 'maintenance_type');

        $validated = $request->validate([
            'maintenance_code' => ['nullable', 'string', 'max:50'],
            'maintenance_date' => ['required', 'date'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:maintenance_date'],
            'vehicle_id' => ['required', 'integer', Rule::in($this->vehicleIds())],
            'maintenance_type' => ['nullable', 'string', Rule::in($this->referenceOptions('transport_maintenance_type', ['oil_change', 'tire_replacement', 'engine_service', 'brake_service', 'general_service']))],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost' => ['required', 'numeric', 'gte:0'],
            'service_provider' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->saveVehicleMaintenance($validated);

        return back()->with('success', __('Vehicle maintenance saved successfully.'));
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
            $query->domain('transport');
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
        } catch (\RuntimeException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => __($exception->getMessage()),
            ]);
        }
    }

    private function authorizeCapabilityOrAbort(string $capability): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (\RuntimeException $exception) {
            abort(403, __($exception->getMessage()));
        }
    }
}
