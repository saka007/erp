<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileManufacturingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->manufacturingService->createLoomMaster($payload),
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
}
