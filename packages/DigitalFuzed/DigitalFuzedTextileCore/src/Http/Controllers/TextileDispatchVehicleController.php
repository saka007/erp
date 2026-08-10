<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextilePartyBranchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;

class TextileDispatchVehicleController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/DispatchVehicles/Index', [
            'vehicles' => TextileDispatchVehicle::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->latest('id')
                ->get(),
            'transportVendorOptions' => $this->transportVendorOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'vehicle_number' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'numeric', 'gte:0'],
            'capacity_unit' => ['nullable', 'string', 'max:50'],
            'ownership_type' => ['required', 'string', Rule::in(['owned', 'hired', 'vendor'])],
            'transport_vendor_id' => ['nullable', 'integer', 'required_if:ownership_type,vendor', Rule::in($this->transportVendorIds())],
            'container_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->assertTransportModeAllowed($validated['ownership_type'] === 'vendor' ? 'vendor' : 'own', 'transport_vendor_id');

        $transportVendor = !empty($validated['transport_vendor_id'])
            ? Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id'])
            : null;

        TextileDispatchVehicle::create([
            ...$validated,
            'transport_vendor_id' => $validated['transport_vendor_id'] ?? null,
            'transporter_name' => $validated['ownership_type'] === 'vendor' ? $transportVendor?->company_name : null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Dispatch vehicle created successfully.'));
    }

    public function update(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'vehicle_number' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'numeric', 'gte:0'],
            'capacity_unit' => ['nullable', 'string', 'max:50'],
            'ownership_type' => ['required', 'string', Rule::in(['owned', 'hired', 'vendor'])],
            'transport_vendor_id' => ['nullable', 'integer', 'required_if:ownership_type,vendor', Rule::in($this->transportVendorIds())],
            'container_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->assertTransportModeAllowed($validated['ownership_type'] === 'vendor' ? 'vendor' : 'own', 'transport_vendor_id');

        $transportVendor = !empty($validated['transport_vendor_id'])
            ? Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id'])
            : null;

        $record = TextileDispatchVehicle::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['vehicle_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'vehicle_number' => $validated['vehicle_number'],
            'code' => $validated['code'] ?? null,
            'vehicle_type' => $validated['vehicle_type'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'capacity_unit' => $validated['capacity_unit'] ?? null,
            'ownership_type' => $validated['ownership_type'] ?? null,
            'transport_vendor_id' => $validated['transport_vendor_id'] ?? null,
            'container_number' => $validated['container_number'] ?? null,
            'transporter_name' => $validated['ownership_type'] === 'vendor' ? $transportVendor?->company_name : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', __('Dispatch vehicle updated successfully.'));
    }

    public function archive(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'min:1'],
        ]);

        $record = TextileDispatchVehicle::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['vehicle_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Dispatch vehicle deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        // Master setup is admin-only (company/superadmin) so staff cannot manage dispatch vehicles.
        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }

    private function assertTransportModeAllowed(string $mode, string $errorKey): void
    {
        $policy = $this->policyService->resolveForCurrentTenant();
        $settings = $this->policyService->settings($policy);
        $ownAllowed = (bool) ($settings[TextileOperatingPolicyService::SETTING_HAS_TRANSPORT_OWN] ?? true);
        $vendorAllowed = (bool) ($settings[TextileOperatingPolicyService::SETTING_HAS_TRANSPORT_VENDOR] ?? true);

        if ($mode === 'vendor' && !$vendorAllowed) {
            throw ValidationException::withMessages([$errorKey => __('Transport vendor mode is disabled in Operating Model settings.')]);
        }

        if ($mode !== 'vendor' && !$ownAllowed) {
            throw ValidationException::withMessages([$errorKey => __('Own transport mode is disabled in Operating Model settings.')]);
        }
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
}
