<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileDashboardRedirectStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_of_textile_company_is_redirected_to_textile_dashboard(): void
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
        UserActiveModule::firstOrCreate([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        $staff = User::factory()->create([
            'type' => 'staff',
            'created_by' => $company->id,
            'creator_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        $staffRole = Role::firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ], [
            'label' => 'Staff',
            'created_by' => $company->id,
        ]);

        $staff->assignRole($staffRole);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertRedirect(route('textile.dashboard.index'));
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Staff Redirect Plan',
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

        return $company;
    }
}
