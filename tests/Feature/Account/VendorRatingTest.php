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

class VendorRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_rating_crud_is_tenant_scoped_and_computes_overall_score(): void
    {
        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        [$company, $otherCompany] = $this->companies();

        UserActiveModule::create(['user_id' => $company->id, 'module' => 'Account']);
        UserActiveModule::create(['user_id' => $otherCompany->id, 'module' => 'Account']);

        $vendor = Vendor::query()->create([
            'company_name' => 'Atlas Processors',
            'supplier_type' => 'processing_vendor',
            'contact_person_name' => 'Nikhil Joshi',
            'same_as_billing' => true,
            'billing_address' => ['city' => 'Surat'],
            'shipping_address' => ['city' => 'Surat'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $otherVendor = Vendor::query()->create([
            'company_name' => 'Other Tenant Vendor',
            'supplier_type' => 'chemical',
            'contact_person_name' => 'Other User',
            'same_as_billing' => true,
            'billing_address' => ['city' => 'Ahmedabad'],
            'shipping_address' => ['city' => 'Ahmedabad'],
            'creator_id' => $otherCompany->id,
            'created_by' => $otherCompany->id,
        ]);

        $this->actingAs($company)
            ->post(route('account.vendor-ratings.store'), [
                'vendor_id' => $vendor->id,
                'rating_date' => now()->toDateString(),
                'quality_score' => 5,
                'delivery_score' => 4,
                'service_score' => 3,
                'price_score' => 4,
                'remarks' => 'Reliable vendor',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $ratingId = (int) \DB::table('account_vendor_ratings')->where('created_by', $company->id)->value('id');

        $this->assertDatabaseHas('account_vendor_ratings', [
            'id' => $ratingId,
            'vendor_id' => $vendor->id,
            'created_by' => $company->id,
            'overall_score' => 4.00,
        ]);

        $this->actingAs($company)
            ->put(route('account.vendor-ratings.update', $ratingId), [
                'rating_date' => now()->toDateString(),
                'quality_score' => 5,
                'delivery_score' => 5,
                'service_score' => 5,
                'price_score' => 4,
                'remarks' => 'Updated score',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('account_vendor_ratings', [
            'id' => $ratingId,
            'overall_score' => 4.75,
        ]);

        $this->actingAs($company)
            ->get(route('account.vendor-ratings.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($vendor): void {
                $page->where('ratings.0.vendor.company_name', $vendor->company_name);
            });

        $this->actingAs($company)
            ->post(route('account.vendor-ratings.store'), [
                'vendor_id' => $otherVendor->id,
                'rating_date' => now()->toDateString(),
                'quality_score' => 4,
                'delivery_score' => 4,
                'service_score' => 4,
                'price_score' => 4,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('vendor_id');

        $this->actingAs($company)
            ->delete(route('account.vendor-ratings.destroy', $ratingId))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('account_vendor_ratings', [
            'id' => $ratingId,
        ]);
    }

    private function companies(): array
    {
        $plan = Plan::create([
            'name' => 'Vendor Rating Plan',
            'modules' => ['Account'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $otherCompany = User::factory()->create([
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
        $otherCompany->assignRole($role);

        return [$company, $otherCompany];
    }
}
