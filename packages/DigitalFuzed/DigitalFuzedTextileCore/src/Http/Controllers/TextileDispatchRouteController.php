<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Services\TextilePartyBranchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;

class TextileDispatchRouteController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/DispatchRoutes/Index', [
            'routes' => TextileDispatchRoute::query()
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
            'route_name' => ['required', 'string', 'max:255'],
            'route_code' => ['nullable', 'string', 'max:50'],
            'origin_location' => ['nullable', 'string', 'max:255'],
            'destination_location' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'numeric', 'gte:0'],
            'transit_hours' => ['nullable', 'numeric', 'gte:0'],
            'transport_vendor_id' => ['nullable', 'integer', Rule::in($this->transportVendorIds())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transportVendor = !empty($validated['transport_vendor_id'])
            ? Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id'])
            : null;

        TextileDispatchRoute::create([
            ...$validated,
            'transport_vendor_id' => $validated['transport_vendor_id'] ?? null,
            'transporter_name' => $transportVendor?->company_name,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Dispatch route created successfully.'));
    }

    public function update(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'route_id' => ['required', 'integer', 'min:1'],
            'route_name' => ['required', 'string', 'max:255'],
            'route_code' => ['nullable', 'string', 'max:50'],
            'origin_location' => ['nullable', 'string', 'max:255'],
            'destination_location' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'numeric', 'gte:0'],
            'transit_hours' => ['nullable', 'numeric', 'gte:0'],
            'transport_vendor_id' => ['nullable', 'integer', Rule::in($this->transportVendorIds())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transportVendor = !empty($validated['transport_vendor_id'])
            ? Vendor::query()->where('created_by', creatorId())->where('supplier_type', 'transport')->findOrFail((int) $validated['transport_vendor_id'])
            : null;

        $record = TextileDispatchRoute::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['route_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->update([
            'route_name' => $validated['route_name'],
            'route_code' => $validated['route_code'] ?? null,
            'origin_location' => $validated['origin_location'] ?? null,
            'destination_location' => $validated['destination_location'] ?? null,
            'distance_km' => $validated['distance_km'] ?? null,
            'transit_hours' => $validated['transit_hours'] ?? null,
            'transport_vendor_id' => $validated['transport_vendor_id'] ?? null,
            'transporter_name' => $transportVendor?->company_name,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', __('Dispatch route updated successfully.'));
    }

    public function archive(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'route_id' => ['required', 'integer', 'min:1'],
        ]);

        $record = TextileDispatchRoute::query()
            ->where('created_by', creatorId())
            ->where('id', $validated['route_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $record->is_active = false;
        $record->save();

        return back()->with('success', __('Dispatch route deactivated successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        // Master setup is admin-only (company/superadmin) so staff cannot manage dispatch routes.
        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
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
