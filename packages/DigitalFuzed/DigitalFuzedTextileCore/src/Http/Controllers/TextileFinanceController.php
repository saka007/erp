<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileChemicalCost;
use DigitalFuzed\TextileCore\Models\TextileCostCenter;
use DigitalFuzed\TextileCore\Models\TextileLabourCost;
use DigitalFuzed\TextileCore\Models\TextileMachineCost;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileFinanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TextileFinanceController extends Controller
{
    public function __construct(protected TextileFinanceService $financeService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();

        return Inertia::render('DigitalFuzedTextileCore/Finance/Index', [
            'machineCosts' => TextileMachineCost::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->latest('id')
                ->get(),
            'powerCosts' => TextilePowerCost::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->latest('id')
                ->get(),
            'chemicalCosts' => TextileChemicalCost::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->latest('id')
                ->get(),
            'labourCosts' => TextileLabourCost::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->latest('id')
                ->get(),
            'machineOptions' => $this->machineOptions(),
            'machineTypeOptions' => $this->referenceOptions('machine_type', ['loom', 'warping_machine', 'sizing_machine', 'processing_machine', 'packing_machine']),
            'processStageOptions' => ['sizing', 'dyeing', 'printing', 'bleaching', 'finishing'],
            'shiftOptions' => ['A', 'B', 'C'],
            'costCenterOptions' => TextileCostCenter::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (TextileCostCenter $row) => [
                    'id' => (int) $row->id,
                    'label' => $row->name,
                ])
                ->values()
                ->all(),
            'costPerMeter' => $this->financeService->costPerMeter(),
            'costPerRoll' => $this->financeService->costPerRoll(),
            'profitability' => $this->financeService->profitability(),
        ]);
    }

    public function storeMachineCost(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'machine_id' => ['nullable', 'integer', 'min:1', Rule::in($this->machineIds())],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'depreciation_cost' => ['nullable', 'numeric', 'gte:0'],
            'maintenance_cost' => ['nullable', 'numeric', 'gte:0'],
            'power_cost' => ['nullable', 'numeric', 'gte:0'],
            'labor_cost' => ['nullable', 'numeric', 'gte:0'],
            'other_cost' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->financeService->saveMachineCost($validated);

        return back()->with('success', __('Machine cost saved successfully.'));
    }

    public function storePowerCost(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'meter_reading_start' => ['required', 'numeric', 'gte:0'],
            'meter_reading_end' => ['required', 'numeric', 'gt:meter_reading_start'],
            'rate_per_unit' => ['required', 'numeric', 'gte:0'],
            'allocation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->financeService->savePowerCost($validated);

        return back()->with('success', __('Power cost saved successfully.'));
    }

    public function storeChemicalCost(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'chemical_date' => ['nullable', 'date'],
            'chemical_name' => ['required', 'string', 'max:255'],
            'process_stage' => ['nullable', 'string', Rule::in($this->processStageOptions())],
            'quantity' => ['nullable', 'numeric', 'gte:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'unit_cost' => ['required', 'numeric', 'gte:0'],
            'batch_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->financeService->saveChemicalCost($validated);

        return back()->with('success', __('Chemical cost saved successfully.'));
    }

    public function storeLabourCost(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'labour_date' => ['nullable', 'date'],
            'cost_center_id' => ['nullable', 'integer', 'min:1', Rule::in($this->costCenterIds())],
            'shift_name' => ['nullable', 'string', Rule::in(['A', 'B', 'C'])],
            'worker_count' => ['required', 'integer', 'min:1'],
            'hours_worked' => ['required', 'numeric', 'gte:0'],
            'rate_per_hour' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->financeService->saveLabourCost($validated);

        return back()->with('success', __('Labour cost saved successfully.'));
    }

    private function machineOptions(): array
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'loom_master')
            ->whereIn('status', ['approved', 'released', 'closed'])
            ->orderBy('document_number')
            ->get(['id', 'document_number'])
            ->map(fn (TextileWorkflowDocument $row) => [
                'id' => (int) $row->id,
                'label' => $row->document_number,
            ])
            ->values()
            ->all();
    }

    private function machineIds(): array
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'loom_master')
            ->whereIn('status', ['approved', 'released', 'closed'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function costCenterIds(): array
    {
        return TextileCostCenter::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function processStageOptions(): array
    {
        $options = $this->referenceOptions('process_stage', []);

        return count($options) > 0 ? $options : ['sizing', 'dyeing', 'printing', 'bleaching', 'finishing'];
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
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $fallback;
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
    }
}
