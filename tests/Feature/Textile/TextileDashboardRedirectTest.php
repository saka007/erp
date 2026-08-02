<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileDashboardRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_with_textile_module_is_redirected_to_textile_dashboard(): void
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

        $this->actingAs($company)
            ->get(route('dashboard'))
            ->assertRedirect(route('textile.dashboard.index'));
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Redirect Plan',
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
