<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TextileCostingApiController extends Controller
{
    public function __construct(protected TextileCostingService $costingService)
    {
    }

    public function storeCostingEntry(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_document_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'material_cost' => ['required', 'numeric', 'gte:0'],
            'conversion_cost' => ['required', 'numeric', 'gte:0'],
            'overhead_cost' => ['required', 'numeric', 'gte:0'],
            'variance_value' => ['nullable', 'numeric'],
            'revenue_value' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->costingService->createCostingEntry($payload),
        ], 201);
    }

    public function finalizeCostingEntry(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->costingService->finalizeCostingEntry($id),
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->costingService->summary(),
        ]);
    }
}
