<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\LoginHistory;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileLogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_view_login_history_and_audit_logs_with_tenant_isolation(): void
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

        LoginHistory::create([
            'user_id' => $companyA->id,
            'ip' => '10.0.0.1',
            'date' => now()->toDateString(),
            'details' => ['source' => 'textile'],
            'type' => 'login',
            'created_by' => $companyA->id,
        ]);

        LoginHistory::create([
            'user_id' => $companyB->id,
            'ip' => '10.0.0.2',
            'date' => now()->toDateString(),
            'details' => ['source' => 'textile'],
            'type' => 'login',
            'created_by' => $companyB->id,
        ]);

        TextileAuditLog::create([
            'event_type' => 'textile.workflow.created',
            'payload' => ['document_number' => 'TXT-LOG-0001', 'action' => 'created'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        TextileAuditLog::create([
            'event_type' => 'textile.workflow.created',
            'payload' => ['document_number' => 'TXT-LOG-0002', 'action' => 'created'],
            'creator_id' => $companyB->id,
            'created_by' => $companyB->id,
        ]);

        $this->actingAs($companyA)
            ->get(route('textile.logs.index'))
            ->assertOk()
            ->assertSee('10.0.0.1')
            ->assertSee('TXT-LOG-0001')
            ->assertDontSee('10.0.0.2')
            ->assertDontSee('TXT-LOG-0002');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Logs Plan',
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