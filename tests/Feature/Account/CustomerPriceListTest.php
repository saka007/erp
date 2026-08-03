<?php

namespace Tests\Feature\Account;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerPriceList;
use Workdo\ProductService\Models\ProductServiceItem;

class CustomerPriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_customer_price_list_with_tenant_scope(): void
    {
        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        AddOn::create([
            'module' => 'ProductService',
            'name' => 'Product Service',
            'package_name' => 'product-service',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $companyA = $this->company();
        $companyB = $this->company();

        UserActiveModule::create(['user_id' => $companyA->id, 'module' => 'Account']);
        UserActiveModule::create(['user_id' => $companyA->id, 'module' => 'ProductService']);
        UserActiveModule::create(['user_id' => $companyB->id, 'module' => 'Account']);
        UserActiveModule::create(['user_id' => $companyB->id, 'module' => 'ProductService']);

        $customerA = Customer::create([
            'company_name' => 'Prime Looms Pvt Ltd',
            'contact_person_name' => 'Ayaan Merchant',
            'contact_person_email' => 'ayaan@primelooms.test',
            'same_as_billing' => true,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $itemA = ProductServiceItem::create([
            'name' => 'Cotton Grey Fabric',
            'sku' => 'FAB-100',
            'unit' => 'mtr',
            'sale_price' => 125.50,
            'type' => 'finished_fabric',
            'is_active' => true,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $customerB = Customer::create([
            'company_name' => 'Other Tenant Buyer',
            'contact_person_name' => 'Other User',
            'contact_person_email' => 'other@example.test',
            'same_as_billing' => true,
            'creator_id' => $companyB->id,
            'created_by' => $companyB->id,
        ]);

        $itemB = ProductServiceItem::create([
            'name' => 'Poly Fabric',
            'sku' => 'FAB-200',
            'unit' => 'mtr',
            'sale_price' => 99.90,
            'type' => 'finished_fabric',
            'is_active' => true,
            'creator_id' => $companyB->id,
            'created_by' => $companyB->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('account.customer-price-lists.store'), [
                'customer_id' => $customerA->id,
                'product_service_item_id' => $itemA->id,
                'unit_price' => 118.75,
                'currency_code' => 'INR',
                'min_quantity' => 10,
                'notes' => 'Preferred contract rate',
            ])
            ->assertSessionHasNoErrors();

        $record = CustomerPriceList::query()
            ->where('created_by', $companyA->id)
            ->where('customer_id', $customerA->id)
            ->where('product_service_item_id', $itemA->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('INR', $record->currency_code);

        // Cross-tenant customer/item pair should be rejected by tenant scoping.
        $this->actingAs($companyA)
            ->post(route('account.customer-price-lists.store'), [
                'customer_id' => $customerB->id,
                'product_service_item_id' => $itemB->id,
                'unit_price' => 100,
                'currency_code' => 'USD',
                'min_quantity' => 1,
            ])
            ->assertSessionHasErrors('customer_id');

        $this->actingAs($companyA)
            ->put(route('account.customer-price-lists.update', $record->id), [
                'unit_price' => 120.00,
                'currency_code' => 'USD',
                'min_quantity' => 5,
                'is_active' => false,
                'notes' => 'Updated for small lots',
            ])
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('USD', $record->currency_code);
        $this->assertFalse((bool) $record->is_active);

        $this->actingAs($companyA)
            ->delete(route('account.customer-price-lists.destroy', $record->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('account_customer_price_lists', [
            'id' => $record->id,
        ]);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Customer Price List Plan',
            'modules' => ['Account', 'ProductService'],
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

        foreach (['manage-customers', 'manage-any-customers', 'create-customers', 'edit-customers', 'delete-customers'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'customers',
                'label' => $permissionName,
                'add_on' => 'Account',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);

        return $company;
    }
}
