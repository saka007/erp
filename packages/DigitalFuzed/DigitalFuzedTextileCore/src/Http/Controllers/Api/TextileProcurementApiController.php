<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TextileProcurementApiController extends Controller
{
    public function __construct(protected TextileProcurementService $procurementService)
    {
    }

    public function storeRequisition(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        $document = $this->procurementService->createRequisition($payload);

        return response()->json(['success' => true, 'data' => $document], 201);
    }

    public function approveRequisition(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->procurementService->approveRequisition($id),
        ]);
    }

    public function storePurchaseOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'requisition_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        $document = $this->procurementService->createPurchaseOrder((int) $payload['requisition_id'], $payload);

        return response()->json(['success' => true, 'data' => $document], 201);
    }

    public function approvePurchaseOrder(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->procurementService->approvePurchaseOrder($id),
        ]);
    }

    public function storeGrn(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        $document = $this->procurementService->createGrn((int) $payload['purchase_order_id'], $payload);

        return response()->json(['success' => true, 'data' => $document], 201);
    }

    public function releaseGrn(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->procurementService->releaseGrn($id),
        ]);
    }

    public function storeIncomingQc(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'grn_id' => ['required', 'integer', 'min:1'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        $document = $this->procurementService->createIncomingQc((int) $payload['grn_id'], $payload);

        return response()->json(['success' => true, 'data' => $document], 201);
    }

    public function finalizeIncomingQc(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'decision' => ['required', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->procurementService->finalizeIncomingQc($id, $payload['decision']),
        ]);
    }
}
