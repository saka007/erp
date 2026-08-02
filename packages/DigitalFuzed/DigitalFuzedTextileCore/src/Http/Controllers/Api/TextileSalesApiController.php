<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileSalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TextileSalesApiController extends Controller
{
    public function __construct(protected TextileSalesService $salesService)
    {
    }

    public function storeSalesOrder(Request $request): JsonResponse
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
            'data' => $this->salesService->createSalesOrder($payload),
        ], 201);
    }

    public function approveSalesOrder(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->salesService->approveSalesOrder($id),
        ]);
    }

    public function storeAllocation(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sales_order_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->salesService->createAllocation((int) $payload['sales_order_id'], $payload),
        ], 201);
    }

    public function releaseAllocation(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->salesService->releaseAllocation($id),
        ]);
    }

    public function storeDispatch(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'allocation_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->salesService->createDispatch((int) $payload['allocation_id'], $payload),
        ], 201);
    }

    public function releaseDispatch(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->salesService->releaseDispatch($id),
        ]);
    }

    public function storeChallan(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'dispatch_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->salesService->createChallan((int) $payload['dispatch_id'], $payload),
        ], 201);
    }

    public function markPod(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->salesService->markPod($id, $payload),
        ]);
    }
}
