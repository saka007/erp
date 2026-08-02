<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\LoginHistory;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileDashboardAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_view_tenant_scoped_dashboard_and_reports(): void
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

        TextileWorkflowDocument::create([
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'document_type' => 'sales_order',
            'document_number' => 'SO-A-0001',
            'party_name' => 'Alpha Customer',
            'lot_reference' => 'LOT-R-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
        ]);

        TextileWorkflowDocument::create([
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'document_type' => 'margin_snapshot',
            'document_number' => 'MS-A-0001',
            'party_name' => 'Alpha Customer',
            'lot_reference' => 'LOT-R-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => [
                'revenue_value' => 3000,
                'total_cost' => 2400,
                'margin_value' => 600,
                'margin_percent' => 20,
            ],
        ]);

        TextileWorkflowDocument::create([
            'created_by' => $companyB->id,
            'creator_id' => $companyB->id,
            'document_type' => 'margin_snapshot',
            'document_number' => 'MS-B-0001',
            'party_name' => 'Beta Customer',
            'lot_reference' => 'LOT-R-B',
            'quantity' => 90,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => [
                'revenue_value' => 9999,
                'total_cost' => 9000,
                'margin_value' => 999,
                'margin_percent' => 9.99,
            ],
        ]);

        LoginHistory::create([
            'user_id' => $companyA->id,
            'ip' => '10.0.0.1',
            'date' => now()->toDateString(),
            'details' => ['source' => 'dashboard-test'],
            'type' => 'login',
            'created_by' => $companyA->id,
        ]);

        TextileAuditLog::create([
            'event_type' => 'textile.workflow.created',
            'payload' => ['document_number' => 'AUD-A-0001', 'action' => 'created'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->get(route('textile.dashboard.index'))
            ->assertOk()
            ->assertSee('LOT-R-1')
            ->assertSee('Alpha Customer')
            ->assertSee('MS-A-0001')
            ->assertDontSee('LOT-R-B')
            ->assertDontSee('Beta Customer')
            ->assertDontSee('MS-B-0001')
            ->assertInertia(function (Assert $page): void {
                $page->where('loginHistoryCount', 1)
                    ->where('auditLogCount', 1)
                    ->has('recentLoginHistory', 1)
                    ->has('recentAuditLogs', 1);

                $this->assertSame('10.0.0.1', $page->toArray()['props']['recentLoginHistory'][0]['ip'] ?? null);
                $this->assertSame('AUD-A-0001', $page->toArray()['props']['recentAuditLogs'][0]['payload']['document_number'] ?? null);
            });
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Dashboard Plan',
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
