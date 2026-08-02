<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TextileWorkflowApiController extends Controller
{
    public function __construct(protected TextileWorkflowService $workflowService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'document_type' => ['required', 'string'],
            'source_reference_type' => ['nullable', 'string', 'max:100'],
            'source_reference_id' => ['nullable', 'integer', 'min:1'],
            'source_action' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
            'party_name' => ['nullable', 'string', 'max:100'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'gte:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ]);

        $document = $this->workflowService->createDocument($payload);

        return response()->json(['success' => true, 'data' => $document], 201);
    }

    public function index(string $type): JsonResponse
    {
        $items = $this->workflowService->listByType($type);

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->workflowService->summary(),
        ]);
    }

    public function transition(Request $request, int $documentId): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'max:50'],
        ]);

        $document = $this->workflowService->transitionStatus($documentId, $payload['status']);

        return response()->json([
            'success' => true,
            'data' => $document,
        ]);
    }
}
