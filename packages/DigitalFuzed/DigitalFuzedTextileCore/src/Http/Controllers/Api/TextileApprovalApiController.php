<?php

namespace DigitalFuzed\TextileCore\Http\Controllers\Api;

use DigitalFuzed\TextileCore\Services\TextileApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TextileApprovalApiController extends Controller
{
    public function __construct(protected TextileApprovalService $approvalService)
    {
    }

    public function indexRules(Request $request): JsonResponse
    {
        $rules = $this->approvalService->listRules($request->query('document_type'));

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'document_type' => ['nullable', 'string', 'max:100'],
            'from_status' => ['required', 'string', 'max:50'],
            'to_status' => ['required', 'string', 'max:50'],
            'min_quantity' => ['nullable', 'numeric', 'gte:0'],
            'max_quantity' => ['nullable', 'numeric', 'gte:0'],
            'required_approvals' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array'],
        ]);

        $rule = $this->approvalService->createRule($payload);

        return response()->json([
            'success' => true,
            'data' => $rule,
        ], 201);
    }

    public function pending(Request $request): JsonResponse
    {
        $data = $this->approvalService->pendingApprovals(
            $request->query('to_status'),
            $request->query('document_type')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function recordDecision(Request $request, int $documentId): JsonResponse
    {
        $payload = $request->validate([
            'to_status' => ['required', 'string', 'max:50'],
            'decision' => ['nullable', 'string', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $decision = $this->approvalService->recordDecision(
            $documentId,
            $payload['to_status'],
            $payload['decision'] ?? 'approved',
            $payload['comment'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $decision,
        ]);
    }
}
