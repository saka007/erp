<?php

namespace Tests\Feature\Account;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;

class VendorPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_performance_snapshot_is_generated_from_ratings_with_tenant_scope(): void
    {
        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        [$company] = $this->companies();

        UserActiveModule::create(['user_id' => $company->id, 'module' => 'Account']);

        $vendor = Vendor::query()->create([
            'company_name' => 'Loom Master Vendor',
            'supplier_type' => 'processing_vendor',
            'contact_person_name' => 'Dev Patel',
            'same_as_billing' => true,
            'billing_address' => ['city' => 'Surat'],
            'shipping_address' => ['city' => 'Surat'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)->post(route('account.vendor-ratings.store'), [
            'vendor_id' => $vendor->id,
            'rating_date' => '2026-08-03',
            'quality_score' => 5,
            'delivery_score' => 4,
            'service_score' => 4,
            'price_score' => 3,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('account.vendor-ratings.store'), [
            'vendor_id' => $vendor->id,
            'rating_date' => '2026-08-11',
            'quality_score' => 4,
            'delivery_score' => 5,
            'service_score' => 3,
            'price_score' => 4,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($company)
            ->post(route('account.vendor-performance.store'), [
                'vendor_id' => $vendor->id,
                'period_month' => '2026-08',
                'remarks' => 'Monthly review',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('account_vendor_performance_snapshots', [
            'vendor_id' => $vendor->id,
            'created_by' => $company->id,
            'period_month' => '2026-08',
            'rating_count' => 2,
            'avg_quality_score' => 4.50,
            'avg_delivery_score' => 4.50,
            'avg_service_score' => 3.50,
            'avg_price_score' => 3.50,
            'avg_overall_score' => 4.00,
        ]);

        $this->actingAs($company)
            ->get(route('account.vendor-performance.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($vendor): void {
                $page->where('snapshots.0.vendor.company_name', $vendor->company_name);
            });
    }

    private function companies(): array
    {
        $plan = Plan::create([
            'name' => 'Vendor Performance Plan',
            'modules' => ['Account'],
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

        foreach (['manage-vendors', 'manage-any-vendors', 'create-vendors', 'edit-vendors', 'delete-vendors'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'vendors',
                'label' => $permissionName,
                'add_on' => 'Account',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);

        return [$company];
    }
}
