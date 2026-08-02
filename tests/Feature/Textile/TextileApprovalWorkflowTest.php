<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileApprovalRule;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;
use DigitalFuzed\TextileCore\Services\TextileApprovalService;
use DigitalFuzed\TextileCore\Services\TextileWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_to_approved_requires_recorded_approval_when_rule_exists(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();
        $this->actingAs($company);

        $workflowService = app(TextileWorkflowService::class);
        $approvalService = app(TextileApprovalService::class);

        $document = $workflowService->createDocument([
            'document_type' => 'purchase_requisition',
            'party_name' => 'Alpha Fibers',
            'lot_reference' => 'LOT-APR-1',
            'quantity' => 100,
            'unit' => 'kg',
            'status' => 'draft',
        ]);

        TextileApprovalRule::create([
            'created_by' => $company->id,
            'creator_id' => $company->id,
            'document_type' => 'purchase_requisition',
            'from_status' => 'draft',
            'to_status' => 'approved',
            'required_approvals' => 1,
            'is_active' => true,
        ]);

        try {
            $workflowService->transitionStatus($document->id, 'approved');
            $this->fail('Expected transition to be blocked without approval decision.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Approval required', $e->getMessage());
        }

        $approvalService->recordDecision($document->id, 'approved', 'approved', 'Reviewed by approver');

        $updated = $workflowService->transitionStatus($document->id, 'approved');
        $this->assertSame('approved', $updated->status);
    }

    public function test_workflow_status_audit_payload_contains_normalized_fields(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();
        $this->actingAs($company);

        $workflowService = app(TextileWorkflowService::class);

        $document = $workflowService->createDocument([
            'document_type' => 'purchase_requisition',
            'party_name' => 'Beta Threads',
            'quantity' => 50,
            'unit' => 'kg',
            'status' => 'draft',
        ]);

        $workflowService->transitionStatus($document->id, 'approved');

        $log = TextileAuditLog::query()
            ->where('created_by', $company->id)
            ->where('event_type', 'textile.workflow.status_changed')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($company->id, $log->created_by);
        $this->assertSame($company->id, $log->payload['tenant_id'] ?? null);
        $this->assertSame($company->id, $log->payload['actor_id'] ?? null);
        $this->assertSame('textile_workflow_document', $log->payload['entity_type'] ?? null);
        $this->assertSame($document->id, $log->payload['entity_id'] ?? null);
        $this->assertSame('status_changed', $log->payload['action'] ?? null);
        $this->assertSame('draft', $log->payload['from_status'] ?? null);
        $this->assertSame('approved', $log->payload['to_status'] ?? null);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Approval Plan',
            'modules' => ['TextileCore'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate([
            'name' => 'company',
            'guard_name' => 'web',
        ], [
            'label' => 'Company',
            'created_by' => $company->id,
        ]);

        $company->assignRole($role);

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        return $company;
    }
}
