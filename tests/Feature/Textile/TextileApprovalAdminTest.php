<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileApprovalRule;
use DigitalFuzed\TextileCore\Models\TextileOperatingPolicy;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Services\TextileWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileApprovalAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_approval_rules_and_decisions_with_tenant_isolation(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $companyA = $this->company();
        $companyB = $this->company();

        // Company A runs a jobwork operating model with sales disabled, so its
        // textile capabilities must not surface sales_order anywhere on page.
        TextileOperatingPolicy::create([
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'operating_model' => TextileOperatingPolicyService::MODEL_JOBWORK_WEAVING,
            'material_ownership' => 'customer_owned',
            'billing_mode' => 'process_charge',
            'settings' => [],
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.approvals.rules.store'), [
                'document_type' => 'purchase_requisition',
                'from_status' => 'draft',
                'to_status' => 'approved',
                'required_approvals' => 1,
            ])
            ->assertRedirect();

        $rule = TextileApprovalRule::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'purchase_requisition')
            ->first();

        $this->assertNotNull($rule);

        $this->actingAs($companyA);
        $workflowService = app(TextileWorkflowService::class);

        $documentA = $workflowService->createDocument([
            'document_type' => 'purchase_requisition',
            'party_name' => 'Alpha Fibers',
            'lot_reference' => 'LOT-ADM-A',
            'quantity' => 75,
            'unit' => 'kg',
            'status' => 'draft',
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.approvals.decisions.store'), [
                'document_id' => $documentA->id,
                'to_status' => 'approved',
                'decision' => 'approved',
                'comment' => 'Approved in admin flow',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('textile_approval_decisions', [
            'created_by' => $companyA->id,
            'textile_workflow_document_id' => $documentA->id,
            'to_status' => 'approved',
            'decision' => 'approved',
        ]);

        $documentB = TextileWorkflowDocument::create([
            'created_by' => $companyB->id,
            'creator_id' => $companyB->id,
            'document_type' => 'purchase_requisition',
            'document_number' => 'TRQ-B-0001',
            'party_name' => 'Other Tenant',
            'lot_reference' => 'LOT-ADM-B',
            'quantity' => 90,
            'unit' => 'kg',
            'status' => 'draft',
        ]);

        TextileApprovalRule::create([
            'created_by' => $companyB->id,
            'creator_id' => $companyB->id,
            'document_type' => 'sales_order',
            'from_status' => 'draft',
            'to_status' => 'approved',
            'required_approvals' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.approvals.decisions.store'), [
                'document_id' => $documentB->id,
                'to_status' => 'approved',
                'decision' => 'approved',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('textile_approval_decisions', [
            'created_by' => $companyA->id,
            'textile_workflow_document_id' => $documentB->id,
            'to_status' => 'approved',
        ]);

        $this->actingAs($companyA)
            ->get(route('textile.approvals.index'))
            ->assertOk()
            ->assertSee('purchase_requisition')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.textile_capabilities.sales_order', false));
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Approval Admin Plan',
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
