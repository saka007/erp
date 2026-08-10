<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileApprovalService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TextileApprovalController extends Controller
{
    public function __construct(protected TextileApprovalService $approvalService)
    {
    }

    public function index()
    {
        $this->authorizeTextileAccess();

        $tenantId = creatorId();

        return Inertia::render('DigitalFuzedTextileCore/Approvals/Index', [
            'rules' => $this->approvalService->listRules()->values(),
            'pendingApprovals' => $this->approvalService->pendingApprovals()->values(),
            'documents' => TextileWorkflowDocument::query()
                ->where('created_by', $tenantId)
                ->latest('id')
                ->limit(100)
                ->get(['id', 'document_type', 'document_number', 'status', 'quantity', 'unit']),
        ]);
    }

    public function storeRule(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'document_type' => ['nullable', 'string', 'max:100'],
            'from_status' => ['required', 'string', 'max:50'],
            'to_status' => ['required', 'string', 'max:50'],
            'min_quantity' => ['nullable', 'numeric', 'gte:0'],
            'max_quantity' => ['nullable', 'numeric', 'gte:0'],
            'required_approvals' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array'],
        ]);

        $this->approvalService->createRule($validated);

        return back()->with('success', __('Approval rule created successfully.'));
    }

    public function storeDecision(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'min:1'],
            'to_status' => ['required', 'string', 'max:50'],
            'decision' => ['nullable', 'string', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->approvalService->recordDecision(
            (int) $validated['document_id'],
            $validated['to_status'],
            $validated['decision'] ?? 'approved',
            $validated['comment'] ?? null
        );

        return back()->with('success', __('Approval decision recorded successfully.'));
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        // Master setup is admin-only (company/superadmin) so staff cannot manage approval rules.
        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}
