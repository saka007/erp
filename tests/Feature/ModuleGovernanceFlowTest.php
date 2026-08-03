<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\TenantModuleActivationRequest;
use App\Models\TenantModuleEntitlement;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleGovernanceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_self_activate_entitled_module_without_approval(): void
    {
        [$company] = $this->seedUsers();

        $this->actingAs($company)
            ->post(route('settings.module-governance.activate'), [
                'module_key' => 'Account',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_active_modules', [
            'user_id' => $company->id,
            'module' => 'Account',
        ]);
    }

    public function test_company_request_can_be_approved_by_superadmin_for_restricted_module(): void
    {
        [$company, $superadmin] = $this->seedUsers();

        TenantModuleEntitlement::query()->create([
            'tenant_id' => $company->id,
            'module_key' => 'TextileManufacturing',
            'is_entitled' => true,
            'requires_approval' => true,
            'set_by' => $superadmin->id,
            'set_at' => now(),
        ]);

        $this->actingAs($company)
            ->post(route('settings.module-governance.activate'), [
                'module_key' => 'TextileManufacturing',
                'request_note' => 'Need manufacturing as we scaled.',
            ])
            ->assertSessionHasNoErrors();

        $request = TenantModuleActivationRequest::query()
            ->where('tenant_id', $company->id)
            ->where('module_key', 'TextileManufacturing')
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($request);

        $this->actingAs($superadmin)
            ->post(route('settings.module-governance.requests.review', $request->id), [
                'decision' => 'approved',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tenant_module_activation_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('user_active_modules', [
            'user_id' => $company->id,
            'module' => 'TextileManufacturing',
        ]);
    }

    private function seedUsers(): array
    {
        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'priority' => 10,
        ]);

        AddOn::create([
            'module' => 'TextileManufacturing',
            'name' => 'Textile Manufacturing',
            'package_name' => 'textile-manufacturing',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'priority' => 20,
        ]);

        $plan = Plan::query()->create([
            'name' => 'Governance Plan',
            'modules' => ['Account'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $superadmin = User::factory()->create([
            'type' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        $companyRole = Role::firstOrCreate([
            'name' => 'company',
            'guard_name' => 'web',
        ], [
            'label' => 'Company',
            'created_by' => $company->id,
        ]);

        $superRole = Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'web',
        ], [
            'label' => 'Super Admin',
            'created_by' => $superadmin->id,
        ]);

        foreach (['manage-settings'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'settings',
                'label' => $permissionName,
                'add_on' => 'general',
            ]);

            $companyRole->givePermissionTo($permission);
            $superRole->givePermissionTo($permission);
        }

        $company->assignRole($companyRole);
        $superadmin->assignRole($superRole);

        UserActiveModule::query()->create([
            'user_id' => $company->id,
            'module' => 'Account',
        ]);

        return [$company, $superadmin];
    }
}
