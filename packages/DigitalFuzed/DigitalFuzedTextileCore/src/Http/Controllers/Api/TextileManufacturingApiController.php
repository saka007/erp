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
