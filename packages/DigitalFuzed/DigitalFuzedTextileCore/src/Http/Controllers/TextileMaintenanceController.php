<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileBreakdown;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceSparePartUsage;
use DigitalFuzed\TextileCore\Models\TextilePmSchedule;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileServiceSchedule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileMaintenanceService;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TextileMaintenanceController extends Controller
{
    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('maintenance_operations');

        return Inertia::render('DigitalFuzedTextileCore/Maintenance/Index', [
            'pmSchedules' => TextilePmSchedule::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'breakdowns' => TextileBreakdown::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'serviceSchedules' => TextileServiceSchedule::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'sparePartUsages' => TextileMaintenanceSparePartUsage::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'maintenanceCosts' => TextileMaintenanceCost::query()
                ->where('created_by', creatorId())
                ->latest()
                ->get(),
            'machineOptions' => $this->machineOptions(),
            'machineTypeOptions' => $this->referenceOptions('machine_type', ['loom', 'warping_machine', 'sizing_machine', 'processing_machine', 'packing_machine']),
            'maintenanceTypeOptions' => $this->referenceOptions('maintenance_type', ['general_service', 'lubrication', 'cleaning', 'inspection', 'calibration']),
            'breakdownReasonOptions' => $this->referenceOptions('breakdown_reason', ['mechanical_failure', 'electrical_failure', 'operator_error', 'material_issue', 'wear_and_tear']),
            'frequencyTypeOptions' => ['days', 'hours', 'cycles'],
            'pmOptions' => $this->pmOptions(),
            'breakdownOptions' => $this->breakdownOptions(),
            'serviceScheduleOptions' => $this->serviceScheduleOptions(),
            'machineHistory' => $this->machineHistory(),
        ]);
    }

    public function storePmSchedule(Request $request, TextileMaintenanceService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('maintenance_operations', 'task_description');

        $validated = $request->validate([
            'pm_code' => ['nullable', 'string', 'max:50'],
            'scheduled_date' => ['required', 'date'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:scheduled_date'],
            'machine_id' => ['required', 'integer', Rule::in($this->machineIds())],
            'machine_type' => ['nullable', 'string', Rule::in($this->referenceOptions('machine_type', ['loom', 'warping_machine', 'sizing_machine', 'processing_machine', 'packing_machine']))],
            'maintenance_type' => ['nullable', 'string', Rule::in($this->referenceOptions('maintenance_type', ['general_service', 'lubrication', 'cleaning', 'inspection', 'calibration']))],
            'frequency_type' => ['nullable', 'string', Rule::in(['days', 'hours', 'cycles'])],
            'frequency_value' => ['nullable', 'numeric', 'gte:0'],
            'task_description' => ['required', 'string', 'max:2000'],
            'last_completed_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['planned', 'overdue', 'completed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->savePmSchedule($validated);

        return back()->with('success', __('Preventive maintenance schedule saved successfully.'));
    }

    public function storeBreakdown(Request $request, TextileMaintenanceService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('maintenance_operations', 'fault_description');

        $validated = $request->validate([
            'breakdown_code' => ['nullable', 'string', 'max:50'],
            'breakdown_date' => ['required', 'date'],
            'machine_id' => ['required', 'integer', Rule::in($this->machineIds())],
            'machine_type' => ['nullable', 'string', Rule::in($this->referenceOptions('machine_type', ['loom', 'warping_machine', 'sizing_machine', 'processing_machine', 'packing_machine']))],
            'fault_description' => ['required', 'string', 'max:2000'],
            'symptom' => ['nullable', 'string', Rule::in($this->referenceOptions('breakdown_reason', ['mechanical_failure', 'electrical_failure', 'operator_error', 'material_issue', 'wear_and_tear']))],
            'downtime_minutes' => ['nullable', 'integer', 'gte:0'],
            'impact' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(['reported', 'in_progress', 'resolved'])],
            'resolved_date' => ['nullable', 'date', 'after_or_equal:breakdown_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->saveBreakdown($validated);

        return back()->with('success', __('Breakdown logged successfully.'));
    }

    public function storeServiceSchedule(Request $request, TextileMaintenanceService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('maintenance_operations', 'technician_name');

        $validated = $request->validate([
            'schedule_code' => ['nullable', 'string', 'max:50'],
            'scheduled_date' => ['required', 'date'],
            'pm_schedule_id' => ['nullable', 'integer', Rule::in($this->pmIds())],
            'machine_id' => ['required', 'integer', Rule::in($this->machineIds())],
            'machine_type' => ['nullable', 'string', Rule::in($this->referenceOptions('machine_type', ['loom', 'warping_machine', 'sizing_machine', 'processing_machine', 'packing_machine']))],
            'technician_name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['scheduled', 'in_progress', 'completed'])],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->saveServiceSchedule($validated);

        return back()->with('success', __('Service schedule saved successfully.'));
    }

    public function storeSparePartUsage(Request $request, TextileMaintenanceService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('maintenance_operations', 'part_name');

        $validated = $request->validate([
            'usage_code' => ['nullable', 'string', 'max:50'],
            'usage_date' => ['required', 'date'],
            'maintenance_ref_type' => ['nullable', 'string', Rule::in(['pm', 'breakdown', 'service'])],
            'maintenance_ref_id' => ['nullable', 'integer'],
            'machine_name' => ['nullable', 'string', 'max:255'],
            'part_name' => ['required', 'string', 'max:255'],
            'part_code' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gte:0'],
            'unit_cost' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['total_cost'] = round((float) $validated['quantity'] * (float) $validated['unit_cost'], 2);

        $service->saveSparePartUsage($validated);

        return back()->with('success', __('Spare part usage saved successfully.'));
    }

    public function storeMaintenanceCost(Request $request, TextileMaintenanceService $service)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('maintenance_operations', 'labor_cost');

        $validated = $request->validate([
            'cost_code' => ['nullable', 'string', 'max:50'],
            'cost_date' => ['required', 'date'],
            'machine_id' => ['required', 'integer', Rule::in($this->machineIds())],
            'machine_type' => ['nullable', 'string', Rule::in($this->referenceOptions('machine_type', ['loom', 'warping_machine', 'sizing_machine', 'processing_machine', 'packing_machine']))],
            'labor_cost' => ['required', 'numeric', 'gte:0'],
            'parts_cost' => ['nullable', 'numeric', 'gte:0'],
            'external_cost' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['total_cost'] = round(
            (float) $validated['labor_cost'] + (float) ($validated['parts_cost'] ?? 0) + (float) ($validated['external_cost'] ?? 0),
            2
        );

        $service->saveMaintenanceCost($validated);

        return back()->with('success', __('Maintenance cost saved successfully.'));
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

    private function pmOptions(): array
    {
        return TextilePmSchedule::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->latest('id')
            ->get(['id', 'pm_code', 'machine_name'])
            ->map(fn (TextilePmSchedule $row) => [
                'id' => (int) $row->id,
                'label' => trim(($row->pm_code ? $row->pm_code . ' | ' : '') . ($row->machine_name ?? '-')),
            ])
            ->values()
            ->all();
    }

    private function pmIds(): array
    {
        return TextilePmSchedule::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function breakdownOptions(): array
    {
        return TextileBreakdown::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->latest('id')
            ->get(['id', 'breakdown_code', 'machine_name'])
            ->map(fn (TextileBreakdown $row) => [
                'id' => (int) $row->id,
                'label' => trim(($row->breakdown_code ? $row->breakdown_code . ' | ' : '') . ($row->machine_name ?? '-')),
            ])
            ->values()
            ->all();
    }

    private function serviceScheduleOptions(): array
    {
        return TextileServiceSchedule::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->latest('id')
            ->get(['id', 'schedule_code', 'machine_name'])
            ->map(fn (TextileServiceSchedule $row) => [
                'id' => (int) $row->id,
                'label' => trim(($row->schedule_code ? $row->schedule_code . ' | ' : '') . ($row->machine_name ?? '-')),
            ])
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
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $fallback;
    }

    private function machineHistory(): array
    {
        $events = [];

        foreach (TextilePmSchedule::query()->where('created_by', creatorId())->get() as $row) {
            $events[] = [
                'event_date' => $row->scheduled_date?->toDateString(),
                'machine_name' => $row->machine_name,
                'event_type' => 'pm',
                'summary' => trim(($row->maintenance_type ?? 'PM') . ($row->task_description ? ': ' . $row->task_description : '')),
            ];
        }

        foreach (TextileBreakdown::query()->where('created_by', creatorId())->get() as $row) {
            $events[] = [
                'event_date' => $row->breakdown_date?->toDateString(),
                'machine_name' => $row->machine_name,
                'event_type' => 'breakdown',
                'summary' => trim(($row->fault_description ?? 'Breakdown') . ($row->downtime_minutes ? ' (' . $row->downtime_minutes . ' min downtime)' : '')),
            ];
        }

        foreach (TextileServiceSchedule::query()->where('created_by', creatorId())->get() as $row) {
            $events[] = [
                'event_date' => $row->scheduled_date?->toDateString(),
                'machine_name' => $row->machine_name,
                'event_type' => 'service',
                'summary' => trim('Service: ' . ($row->technician_name ?? '')),
            ];
        }

        foreach (TextileMaintenanceCost::query()->where('created_by', creatorId())->get() as $row) {
            $events[] = [
                'event_date' => $row->cost_date?->toDateString(),
                'machine_name' => $row->machine_name,
                'event_type' => 'cost',
                'summary' => 'Maintenance cost: ' . number_format((float) $row->total_cost, 2),
            ];
        }

        usort($events, fn (array $a, array $b) => strcmp((string) ($b['event_date'] ?? ''), (string) ($a['event_date'] ?? '')));

        return array_values($events);
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
