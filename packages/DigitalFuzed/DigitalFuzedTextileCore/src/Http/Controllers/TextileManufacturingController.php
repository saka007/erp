<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileCore\Services\TextileManufacturingService;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;
use Workdo\Account\Models\Customer;

class TextileManufacturingController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('manufacturing');

        return Inertia::render('DigitalFuzedTextileCore/Manufacturing/Index', [
            'warpPlans' => $this->documents('warp_plan'),
            'yarnAllocations' => $this->documents('yarn_allocation'),
            'warpSheets' => $this->documents('warp_sheet'),
            'warpProductions' => $this->documents('warp_production'),
            'sizingRecipes' => $this->documents('sizing_recipe'),
            'loomMasters' => $this->documents('loom_master'),
            'beams' => $this->documents('beam'),
            'beamIssues' => $this->documents('beam_issue'),
            'beamReturns' => $this->documents('beam_return'),
            'productionBatches' => $this->documents('production_batch'),
            'weavingOutputs' => $this->documents('weaving_output'),
            'wastes' => $this->documents('waste'),
            'reworks' => $this->documents('rework'),
            'sourceTypeOptions' => $this->sourceTypeOptions(),
            'sourceActionOptions' => $this->sourceActionOptions(),
            'machineTypeOptions' => $this->machineTypeOptions(),
            'unitOptions' => $this->unitOptions(),
            'partyOptions' => $this->partyOptions(),
            'lotReferenceOptions' => $this->lotReferenceOptions(),
        ]);
    }

    public function storeWarpPlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'source_reference_id');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWarpPlan($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp plan created successfully.'));
    }

    public function approveWarpPlan(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'warp_plan_id');

        $validated = $request->validate([
            'warp_plan_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveWarpPlan((int) $validated['warp_plan_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_plan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp plan approved successfully.'));
    }

    public function storeYarnAllocation(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'warp_plan_id');

        $validated = $request->validate([
            'warp_plan_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createYarnAllocation((int) $validated['warp_plan_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_plan_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Yarn allocation recorded successfully.'));
    }

    public function storeWarpSheet(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'yarn_allocation_id');

        $validated = $request->validate([
            'yarn_allocation_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createWarpSheet((int) $validated['yarn_allocation_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['yarn_allocation_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp sheet created successfully.'));
    }

    public function storeWarpProduction(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'warp_sheet_id');

        $validated = $request->validate([
            'warp_sheet_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createWarpProduction((int) $validated['warp_sheet_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_sheet_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Warp production created successfully.'));
    }

    public function storeSizingRecipe(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'warp_production_id');

        $validated = $request->validate([
            'warp_production_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createSizingRecipe((int) $validated['warp_production_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['warp_production_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Sizing recipe created successfully.'));
    }

    public function storeBeamIssue(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createBeamIssue((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam issue created successfully.'));
    }

    public function storeBeamReturn(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'beam_issue_id');

        $validated = $request->validate([
            'beam_issue_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createBeamReturn((int) $validated['beam_issue_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_issue_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam return created successfully.'));
    }

    public function storeBeam(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'source_reference_id');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createBeam($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam created successfully.'));
    }

    public function storeBeamFromSizingRecipe(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'sizing_recipe_id');

        $validated = $request->validate([
            'sizing_recipe_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createBeamFromSizingRecipe((int) $validated['sizing_recipe_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sizing_recipe_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam created from sizing recipe successfully.'));
    }

    public function storeLoomMaster(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'source_reference_id');

        $validated = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['required', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createLoomMaster($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['source_reference_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Loom master created successfully.'));
    }

    public function approveBeam(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->approveBeam((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Beam approved successfully.'));
    }

    public function storeProductionBatch(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'beam_id');

        $validated = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->createProductionBatch((int) $validated['beam_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['beam_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production batch created successfully.'));
    }

    public function releaseProductionBatch(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $service->releaseProductionBatch((int) $validated['batch_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Production batch released successfully.'));
    }

    public function storeWeavingOutput(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWeavingOutput((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Weaving output recorded successfully.'));
    }

    public function storeWaste(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'batch_id');

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createWaste((int) $validated['batch_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['batch_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Waste recorded successfully.'));
    }

    public function storeRework(Request $request, TextileManufacturingService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('manufacturing', 'weaving_output_id');

        $validated = $request->validate([
            'weaving_output_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $service->createRework((int) $validated['weaving_output_id'], $validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['weaving_output_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Rework recorded successfully.'));
    }

    private function documents(string $type)
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', $type)
            ->latest()
            ->get();
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
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceTypeOptions();
    }

    private function machineTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultMachineTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('machine_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultMachineTypeOptions();
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
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultSourceActionOptions();
    }

    private function defaultSourceTypeOptions(): array
    {
        return [
            'warp_plan',
            'beam_register',
            'sizing_recipe',
            'loom_allocation',
            'factory',
        ];
    }

    private function defaultMachineTypeOptions(): array
    {
        return [
            'rapier',
            'airjet',
            'waterjet',
            'shuttle',
        ];
    }

    private function defaultSourceActionOptions(): array
    {
        return [
            'warp_plan',
            'beam_prepare',
            'loom_register',
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
