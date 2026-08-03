<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Services\TextileManufacturingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TextileManufacturingApiController extends Controller
{
    public function __construct(protected TextileManufacturingService $manufacturingService)
    {
    }

    public function storeBeam(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createBeam($payload),
        ], 201);
    }

    public function storeLoomMaster(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['required', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'shed_type' => ['required', 'string', 'max:100', Rule::in($this->shedTypeOptions())],
            'width' => ['nullable', 'numeric', 'gt:0'],
            'loom_status' => ['required', 'string', 'max:100', Rule::in($this->loomStatusOptions())],
            'running_hours' => ['nullable', 'numeric', 'gte:0'],
            'idle_hours' => ['nullable', 'numeric', 'gte:0'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createLoomMaster($payload),
        ], 201);
    }

    public function storeLoomBreakdown(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'breakdown_reason' => ['required', 'string', 'max:100', Rule::in($this->breakdownReasonOptions())],
            'downtime_hours' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createLoomBreakdown((int) $payload['loom_master_id'], $payload),
        ], 201);
    }

    public function storeLoomMaintenance(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'maintenance_type' => ['required', 'string', 'max:100', Rule::in($this->maintenanceTypeOptions())],
            'maintenance_hours' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createLoomMaintenance((int) $payload['loom_master_id'], $payload),
        ], 201);
    }

    public function storeMachinePlan(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'loom_master_id' => ['required', 'integer', 'min:1'],
            'beam_id' => ['required', 'integer', 'min:1'],
            'planned_date' => ['required', 'date'],
            'planned_shift' => ['required', 'string', 'max:100', Rule::in($this->shiftOptions())],
            'planned_quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createMachinePlan((int) $payload['loom_master_id'], (int) $payload['beam_id'], $payload),
        ], 201);
    }

    public function storeWarpPlan(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_reference_type' => ['required', 'string', 'max:100'],
            'source_reference_id' => ['required', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createWarpPlan($payload),
        ], 201);
    }

    public function approveWarpPlan(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->approveWarpPlan($id),
        ]);
    }

    public function storeYarnAllocation(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'warp_plan_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createYarnAllocation((int) $payload['warp_plan_id'], $payload),
        ], 201);
    }

    public function storeWarpSheet(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'yarn_allocation_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createWarpSheet((int) $payload['yarn_allocation_id'], $payload),
        ], 201);
    }

    public function storeWarpProduction(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'warp_sheet_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createWarpProduction((int) $payload['warp_sheet_id'], $payload),
        ], 201);
    }

    public function storeSizingRecipe(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'warp_production_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createSizingRecipe((int) $payload['warp_production_id'], $payload),
        ], 201);
    }

    public function storeChemicalConsumption(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sizing_recipe_id' => ['required', 'integer', 'min:1'],
            'chemical_type' => ['required', 'string', 'max:100'],
            'composition_percent' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'consumption_quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createChemicalConsumption((int) $payload['sizing_recipe_id'], $payload),
        ], 201);
    }

    public function storeBeamIssue(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createBeamIssue((int) $payload['beam_id'], $payload),
        ], 201);
    }

    public function storeBeamReturn(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'beam_issue_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createBeamReturn((int) $payload['beam_issue_id'], $payload),
        ], 201);
    }

    public function storeBeamInspection(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
            'inspection_result' => ['required', 'string', 'max:100', Rule::in($this->inspectionResultOptions())],
            'remarks' => ['nullable', 'string', 'max:500'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createBeamInspection((int) $payload['beam_id'], $payload),
        ], 201);
    }

    public function storeBeamCost(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
            'cost_type' => ['required', 'string', 'max:100', Rule::in($this->costTypeOptions())],
            'cost_amount' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createBeamCost((int) $payload['beam_id'], $payload),
        ], 201);
    }

    public function storeBeamFromSizingRecipe(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sizing_recipe_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createBeamFromSizingRecipe((int) $payload['sizing_recipe_id'], $payload),
        ], 201);
    }

    public function approveBeam(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->approveBeam($id),
        ]);
    }

    public function storeProductionBatch(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'beam_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createProductionBatch((int) $payload['beam_id'], $payload),
        ], 201);
    }

    public function releaseProductionBatch(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->releaseProductionBatch($id),
        ]);
    }

    public function storeWeavingOutput(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createWeavingOutput((int) $payload['batch_id'], $payload),
        ], 201);
    }

    public function storeWaste(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createWaste((int) $payload['batch_id'], $payload),
        ], 201);
    }

    public function storeRework(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'weaving_output_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createRework((int) $payload['weaving_output_id'], $payload),
        ], 201);
    }

    private function costTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultCostTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('cost_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultCostTypeOptions();
    }

    private function inspectionResultOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultInspectionResultOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('inspection_result')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultInspectionResultOptions();
    }

    private function defaultCostTypeOptions(): array
    {
        return [
            'sizing_overhead',
            'beam_preparation',
            'labor',
            'energy',
            'other',
        ];
    }

    private function defaultInspectionResultOptions(): array
    {
        return [
            'pass',
            'hold',
            'rework',
        ];
    }

    private function shedTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultShedTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('shed_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultShedTypeOptions();
    }

    private function loomStatusOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultLoomStatusOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('loom_status')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultLoomStatusOptions();
    }

    private function defaultShedTypeOptions(): array
    {
        return [
            'open',
            'closed',
            'dobby',
            'jacquard',
        ];
    }

    private function defaultLoomStatusOptions(): array
    {
        return [
            'running',
            'idle',
        ];
    }

    private function breakdownReasonOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultBreakdownReasonOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('breakdown_reason')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultBreakdownReasonOptions();
    }

    private function maintenanceTypeOptions(): array
    {
        if (!Schema::hasTable('textile_reference_masters')) {
            return $this->defaultMaintenanceTypeOptions();
        }

        $query = TextileReferenceMaster::query()
            ->type('maintenance_type')
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if (Schema::hasColumn('textile_reference_masters', 'master_domain')) {
            $query->domain('manufacturing');
        }

        $options = $query->orderBy('name')->pluck('name')->values()->all();

        return count($options) > 0 ? $options : $this->defaultMaintenanceTypeOptions();
    }

    private function defaultBreakdownReasonOptions(): array
    {
        return [
            'mechanical',
            'electrical',
            'yarn_break',
            'power_failure',
            'other',
        ];
    }

    private function defaultMaintenanceTypeOptions(): array
    {
        return [
            'preventive',
            'corrective',
            'lubrication',
            'cleaning',
            'other',
        ];
    }

    private function shiftOptions(): array
    {
        return [
            'day',
            'night',
            'general',
        ];
    }
}
