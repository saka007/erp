<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TextileProcessingApiController extends Controller
{
    public function __construct(protected TextileProcessingService $processingService)
    {
    }

    public function storeOutward(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_reference_type' => ['nullable', 'string', 'max:100'],
            'source_reference_id' => ['nullable', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->processingService->createJobWorkOutward($payload),
        ], 201);
    }

    public function releaseOutward(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->processingService->releaseJobWorkOutward($id),
        ]);
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'outward_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->processingService->createProcessingBatch((int) $payload['outward_id'], $payload),
        ], 201);
    }

    public function releaseBatch(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->processingService->releaseProcessingBatch($id),
        ]);
    }

    public function storeInward(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->processingService->createJobWorkInward((int) $payload['batch_id'], $payload),
        ], 201);
    }

    public function finalizeInward(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'decision' => ['required', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->processingService->finalizeJobWorkInward($id, $payload['decision']),
        ]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'outward_id' => ['required', 'integer', 'min:1'],
            'inward_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->processingService->reconcileJobWork((int) $payload['outward_id'], (int) $payload['inward_id'], $payload['notes'] ?? null),
        ], 201);
    }
}
