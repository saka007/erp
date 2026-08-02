<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileApprovalDecision;
use DigitalFuzed\TextileCore\Models\TextileApprovalRule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Support\Collection;
use RuntimeException;

class TextileApprovalService
{
    public function listRules(?string $documentType = null): Collection
    {
        return TextileApprovalRule::query()
            ->where('created_by', $this->tenantId())
            ->when($documentType !== null, fn ($q) => $q->where('document_type', $documentType))
            ->latest('id')
            ->get();
    }

    public function createRule(array $payload): TextileApprovalRule
    {
        return TextileApprovalRule::create([
            'document_type' => $payload['document_type'] ?? null,
            'from_status' => $payload['from_status'],
            'to_status' => $payload['to_status'],
            'min_quantity' => $payload['min_quantity'] ?? null,
            'max_quantity' => $payload['max_quantity'] ?? null,
            'required_approvals' => (int) ($payload['required_approvals'] ?? 1),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'conditions' => $payload['conditions'] ?? null,
            'creator_id' => auth()->id(),
            'created_by' => $this->tenantId(),
        ]);
    }

    public function assertTransitionIsApproved(TextileWorkflowDocument $document, string $toStatus): void
    {
        $rule = $this->resolveRule($document, $toStatus);
        if ($rule === null) {
            return;
        }

        $approvals = TextileApprovalDecision::query()
            ->where('created_by', $this->tenantId())
            ->where('textile_workflow_document_id', $document->id)
            ->where('to_status', $toStatus)
            ->where('decision', 'approved')
            ->count();

        if ($approvals < (int) $rule->required_approvals) {
            throw new RuntimeException(
                "Approval required before transition from {$document->status} to {$toStatus}."
            );
        }
    }

    public function recordDecision(int $documentId, string $toStatus, string $decision = 'approved', ?string $comment = null): TextileApprovalDecision
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Approval decision must be approved or rejected.');
        }

        $document = TextileWorkflowDocument::query()
            ->where('created_by', $this->tenantId())
            ->findOrFail($documentId);

        $rule = $this->resolveRule($document, $toStatus);
        if ($rule === null) {
            throw new RuntimeException('No active approval rule configured for this transition.');
        }

        return TextileApprovalDecision::updateOrCreate(
            [
                'created_by' => $this->tenantId(),
                'textile_workflow_document_id' => $document->id,
                'to_status' => $toStatus,
                'creator_id' => auth()->id(),
            ],
            [
                'textile_approval_rule_id' => $rule->id,
                'decision' => $decision,
                'comment' => $comment,
            ]
        );
    }

    public function pendingApprovals(?string $toStatus = null, ?string $documentType = null): Collection
    {
        $rules = TextileApprovalRule::query()
            ->where('created_by', $this->tenantId())
            ->where('is_active', true)
            ->when($toStatus !== null, fn ($q) => $q->where('to_status', $toStatus))
            ->when($documentType !== null, fn ($q) => $q->where('document_type', $documentType))
            ->get();

        if ($rules->isEmpty()) {
            return collect();
        }

        $documents = TextileWorkflowDocument::query()
            ->where('created_by', $this->tenantId())
            ->latest('id')
            ->limit(200)
            ->get();

        return $documents->map(function (TextileWorkflowDocument $document) use ($rules) {
            $rule = $this->resolveRuleForCollection($rules, $document, null);
            if ($rule === null) {
                return null;
            }

            $approvedCount = TextileApprovalDecision::query()
                ->where('created_by', $this->tenantId())
                ->where('textile_workflow_document_id', $document->id)
                ->where('to_status', $rule->to_status)
                ->where('decision', 'approved')
                ->count();

            if ($approvedCount >= (int) $rule->required_approvals) {
                return null;
            }

            return [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'document_number' => $document->document_number,
                'current_status' => $document->status,
                'next_status' => $rule->to_status,
                'required_approvals' => (int) $rule->required_approvals,
                'approved_count' => $approvedCount,
            ];
        })->filter()->values();
    }

    protected function resolveRule(TextileWorkflowDocument $document, string $toStatus): ?TextileApprovalRule
    {
        $rules = TextileApprovalRule::query()
            ->where('created_by', $this->tenantId())
            ->where('is_active', true)
            ->where('from_status', $document->status)
            ->where('to_status', $toStatus)
            ->where(function ($q) use ($document) {
                $q->whereNull('document_type')
                    ->orWhere('document_type', $document->document_type);
            })
            ->orderByDesc('required_approvals')
            ->get();

        return $this->resolveRuleForCollection($rules, $document, $toStatus);
    }

    protected function resolveRuleForCollection(Collection $rules, TextileWorkflowDocument $document, ?string $toStatus): ?TextileApprovalRule
    {
        return $rules
            ->when($toStatus !== null, fn (Collection $items) => $items->where('to_status', $toStatus))
            ->first(function (TextileApprovalRule $rule) use ($document) {
                if ($rule->from_status !== $document->status) {
                    return false;
                }

                if ($rule->document_type !== null && $rule->document_type !== $document->document_type) {
                    return false;
                }

                if ($rule->min_quantity !== null && (float) $document->quantity < (float) $rule->min_quantity) {
                    return false;
                }

                if ($rule->max_quantity !== null && (float) $document->quantity > (float) $rule->max_quantity) {
                    return false;
                }

                return true;
            });
    }

    protected function tenantId(): ?int
    {
        return auth()->check() && function_exists('creatorId') ? (int) creatorId() : auth()->id();
    }
}
