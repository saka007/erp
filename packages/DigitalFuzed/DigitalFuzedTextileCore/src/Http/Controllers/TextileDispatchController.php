<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileDispatchService;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

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
            'vehicleOptions' => $this->vehicleOptions(),
            'driverOptions' => $this->driverOptions(),
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
            'truck_number' => ['nullable', 'string', 'max:100'],
            'container_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'lr_number' => ['nullable', 'string', 'max:100'],
            'eway_bill_number' => ['nullable', 'string', 'max:100'],
            'freight_amount' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

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
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'lr_number' => ['nullable', 'string', 'max:100'],
            'eway_bill_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

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
        if (!Schema::hasTable('textile_reference_masters')) {
            return ['dispatch_plan', 'challan_reference', 'transport_manifest'];
        }

        $query = TextileReferenceMaster::query()
            ->type('source_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('dispatch');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : ['dispatch_plan', 'challan_reference', 'transport_manifest'];
    }

    private function sourceActionOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return ['dispatch_plan', 'vehicle_assign', 'tracking_update'];
        }

        $query = TextileReferenceMaster::query()
            ->type('source_action')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('dispatch');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : ['dispatch_plan', 'vehicle_assign', 'tracking_update'];
    }

    private function dispatchModeOptions(): array
    {
        return ['truck', 'container'];
    }

    private function trackingStatusOptions(): array
    {
        return ['planned', 'in_transit', 'delayed', 'delivered'];
    }

    private function vehicleOptions(): array
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'dispatch_plan')
            ->pluck('metadata')
            ->map(fn ($metadata) => is_array($metadata) ? ($metadata['vehicle_number'] ?? null) : null)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();
    }

    private function driverOptions(): array
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'dispatch_plan')
            ->pluck('metadata')
            ->map(fn ($metadata) => is_array($metadata) ? ($metadata['driver_name'] ?? null) : null)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
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
