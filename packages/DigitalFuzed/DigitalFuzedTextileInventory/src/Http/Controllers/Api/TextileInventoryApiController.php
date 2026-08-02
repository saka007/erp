<?php

namespace DigitalFuzed\TextileInventory\Http\Controllers\Api;

use DigitalFuzed\TextileInventory\Services\TextileAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TextileInventoryApiController extends Controller
{
    public function __construct(protected TextileAvailabilityService $availabilityService)
    {
    }

    public function reserve(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $reservation = $this->availabilityService->reserve(
            $payload['lot_reference'],
            (float) $payload['quantity'],
            $payload['reference_type'] ?? null,
            $payload['reference_id'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $reservation], 201);
    }

    public function availability(string $lotReference): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->availabilityService->getAvailability($lotReference),
        ]);
    }
}
