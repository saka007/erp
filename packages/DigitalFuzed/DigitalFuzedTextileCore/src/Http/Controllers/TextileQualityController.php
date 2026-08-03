<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextileQualityService;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;

class TextileQualityController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('quality');

        return Inertia::render('DigitalFuzedTextileCore/Quality/Index', [
            'inspections' => $this->documents('inspection'),
            'holds' => $this->documents('hold_release'),
            'lots' => TextileLot::query()->where('created_by', creatorId())->latest()->get(),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'sourceActionOptions' => $this->sourceActionOptions(),
            'unitOptions' => $this->unitOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
        ]);
    }

    public function storeInspection(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('quality_inspection', 'lot_reference');

        $validated = $request->validate([
            'source_reference_type' => ['nullable', 'string', 'max:100'],
            'source_reference_id' => ['nullable', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createInspection($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['lot_reference' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Inspection created successfully.'));
    }

    public function finalizeInspection(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('quality_inspection', 'inspection_id');

        $validated = $request->validate([
            'inspection_id' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:pass,fail'],
        ]);

        try {
            $service->finalizeInspection((int) $validated['inspection_id'], $validated['decision']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['inspection_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Inspection finalized successfully.'));
    }

    public function holdLot(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('quality_hold_release', 'lot_reference');

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->holdLot($validated['lot_reference'], (string) ($validated['reason'] ?? 'Quality hold'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['lot_reference' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Lot hold applied successfully.'));
    }

    public function releaseLot(Request $request, TextileQualityService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('quality_hold_release', 'lot_reference');

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->releaseLot($validated['lot_reference'], (string) ($validated['reason'] ?? 'Quality release'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['lot_reference' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Lot release applied successfully.'));
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
            return $this->defaultSourceTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('source_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('quality');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceTypeOptions();
    }

    private function defaultSourceTypeOptions(): array
    {
        return [
            'incoming_qc',
            'in_process_qc',
            'final_qc',
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
            $query->domain('quality');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceActionOptions();
    }

    private function defaultSourceActionOptions(): array
    {
        return [
            'incoming_qc',
            'in_process_qc',
            'final_qc',
            'hold',
            'release',
        ];
    }

    private function partyOptions(): array
    {
        $customerNames = collect();
        if (Schema::hasTable('customers')) {
            $customerNames = Customer::query()
                ->where('created_by', creatorId())
                ->whereNotNull('company_name')
                ->pluck('company_name');
        }

        $vendorNames = collect();
        if (Schema::hasTable('vendors')) {
            $vendorNames = Vendor::query()
                ->where('created_by', creatorId())
                ->whereNotNull('company_name')
                ->pluck('company_name');
        }

        $workflowParties = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->whereNotNull('party_name')
            ->pluck('party_name');

        return $customerNames
            ->merge($vendorNames)
            ->merge($workflowParties)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function lotReferenceOptions(): array
    {
        $lots = TextileLot::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->pluck('lot_reference');

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
