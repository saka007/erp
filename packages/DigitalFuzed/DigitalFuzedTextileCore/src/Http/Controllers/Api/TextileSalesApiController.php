<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileSalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Workdo\Account\Models\Customer;

class TextileSalesApiController extends Controller
{
    public function __construct(protected TextileSalesService $salesService)
    {
    }

    public function storeSalesOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'source_reference_type' => ['nullable', 'string', 'max:100'],
            'source_reference_id' => ['nullable', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['required', 'integer', 'min:1'],
            'lot_selections' => ['required', 'array', 'min:1'],
            'lot_selections.*.lot_reference' => ['required', 'string', 'max:100', 'distinct'],
            'lot_selections.*.quantity' => ['required', 'numeric', 'gt:0'],
            'rate' => ['required', 'numeric', 'gte:0'],
            'required_delivery_date' => ['required', 'date'],
            'warehouse' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        $customer = Customer::query()->where('created_by', creatorId())->findOrFail((int) $payload['customer_id']);
        $payload['party_name'] = $customer->company_name;
        $payload['metadata'] = [
            'item_name' => 'Takha / Woven Fabric',
            'rate' => (float) $payload['rate'],
            'required_delivery_date' => $payload['required_delivery_date'],
            'warehouse' => $payload['warehouse'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];

        return response()->json([
            'success' => true,
            'data' => $this->salesService->createSalesOrder($payload),
        ], 201);
    }

    public function approveSalesOrder(int $id): JsonResponse
    {
        try {
            $salesOrder = $this->salesService->approveSalesOrder($id);
        } catch (\RuntimeException $exception) {
            // If an approval workflow is configured, record this actor's approval
            // decision and retry once so the API approve is a single-step action.
            if (str_contains($exception->getMessage(), 'Approval required before transition')) {
                try {
                    app(\DigitalFuzed\TextileCore\Services\TextileApprovalService::class)->recordDecision(
                        $id,
                        'approved',
                        'approved',
                        'Recorded from API Approve action.'
                    );

                    $salesOrder = $this->salesService->approveSalesOrder($id);
                } catch (\RuntimeException $retryException) {
                    return response()->json([
                        'success' => false,
                        'message' => $retryException->getMessage(),
                    ], 422);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $salesOrder,
        ]);
    }

    public function storeAllocation(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sales_order_id' => ['required', 'integer', 'min:1'],
            'lot_allocations' => ['required', 'array', 'min:1'],
            'lot_allocations.*.lot_reference' => ['required', 'string', 'max:100', 'distinct'],
            'lot_allocations.*.quantity' => ['required', 'numeric', 'gt:0'],
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
