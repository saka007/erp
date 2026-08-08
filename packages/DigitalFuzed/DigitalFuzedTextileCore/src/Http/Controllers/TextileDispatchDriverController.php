<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;

class TextileDispatchDriverController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/DispatchDrivers/Index', [
            'drivers' => TextileDispatchDriver::query()
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'driver_source' => ['required', 'string', Rule::in(['own', 'vendor'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_expiry_date' => ['nullable', 'date'],
            'transport_vendor_id' => ['nullable', 'integer', 'required_if:driver_source,vendor', Rule::in($this->transportVendorIds())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->assertTransportModeAllowed($validated['driver_source'] === 'vendor' ? 'vendor' : 'own', 'transport_vendor_id');

        $transportVendor = !empty($validated['transport_vendor_id'])
            ? Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id'])
            : null;

        TextileDispatchDriver::create([
            ...$validated,
            'driver_source' => $validated['driver_source'],
            'transport_vendor_id' => $validated['transport_vendor_id'] ?? null,
            'transporter_name' => $validated['driver_source'] === 'vendor' ? $transportVendor?->company_name : null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Dispatch driver created successfully.'));
    }

    public function update(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'driver_source' => ['required', 'string', Rule::in(['own', 'vendor'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_expiry_date' => ['nullable', 'date'],
            'transport_vendor_id' => ['nullable', 'integer', 'required_if:driver_source,vendor', Rule::in($this->transportVendorIds())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->assertTransportModeAllowed($validated['driver_source'] === 'vendor' ? 'vendor' : 'own', 'transport_vendor_id');

        $transportVendor = !empty($validated['transport_vendor_id'])
            ? Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id'])
            : null;

        $record = TextileDispatchDriver::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['driver_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'driver_source' => $validated['driver_source'],
            'phone' => $validated['phone'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'license_expiry_date' => $validated['license_expiry_date'] ?? null,
            'transport_vendor_id' => $validated['transport_vendor_id'] ?? null,
            'transporter_name' => $validated['driver_source'] === 'vendor' ? $transportVendor?->company_name : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', __('Dispatch driver updated successfully.'));
    }

    public function archive(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'min:1'],
        ]);

        $record = TextileDispatchDriver::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['driver_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Dispatch driver deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
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
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function assertTransportModeAllowed(string $mode, string $errorKey): void
    {
        $policy = $this->policyService->resolveForCurrentTenant();
        $settings = $this->policyService->settings($policy);

        $ownAllowed = (bool) ($settings[TextileOperatingPolicyService::SETTING_HAS_TRANSPORT_OWN] ?? true);
        $vendorAllowed = (bool) ($settings[TextileOperatingPolicyService::SETTING_HAS_TRANSPORT_VENDOR] ?? true);

        if ($mode === 'vendor' && !$vendorAllowed) {
            throw ValidationException::withMessages([
                $errorKey => __('Transport vendor mode is disabled in Operating Model settings.'),
            ]);
        }

        if ($mode !== 'vendor' && !$ownAllowed) {
            throw ValidationException::withMessages([
                $errorKey => __('Own transport mode is disabled in Operating Model settings.'),
            ]);
        }
    }
}
