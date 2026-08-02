<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileAuthShareFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_textile_tenant_receives_textile_only_activated_packages_in_shared_auth_payload(): void
    {
        AddOn::create([
            'module' => 'ProductService',
            'name' => 'Product Service',
            'package_name' => 'product-service',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        AddOn::create([
            'module' => 'TextileInventory',
            'name' => 'Textile Inventory',
            'package_name' => 'textile-inventory',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'ProductService',
        ]);

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileInventory',
        ]);

        $this->actingAs($company)
            ->get(route('textile.dashboard.index'))
            ->assertInertia(function (Assert $page): void {
                $page->where('auth.user.industry_type', 'textile');

                $activatedPackages = $page->toArray()['props']['auth']['user']['activatedPackages'] ?? [];

                $this->assertContains('TextileCore', $activatedPackages);
                $this->assertContains('TextileInventory', $activatedPackages);
                $this->assertNotContains('ProductService', $activatedPackages);
            });
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Auth Share Plan',
            'modules' => ['TextileCore', 'TextileInventory'],
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
