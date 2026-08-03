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
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerCategory;

class CustomerCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_customer_categories_and_assign_them_to_customers(): void
    {
        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $companyA = $this->company();
        $companyB = $this->company();

        UserActiveModule::create(['user_id' => $companyA->id, 'module' => 'Account']);
        UserActiveModule::create(['user_id' => $companyB->id, 'module' => 'Account']);

        $this->actingAs($companyA)
            ->post(route('account.customer-categories.store'), [
                'name' => 'Domestic Retail',
                'code' => 'DOM-RTL',
                'description' => 'Retail buyers in local market',
            ])
            ->assertSessionHasNoErrors();

        $categoryA = CustomerCategory::query()
            ->where('created_by', $companyA->id)
            ->where('name', 'Domestic Retail')
            ->first();

        $this->assertNotNull($categoryA);

        $this->actingAs($companyB)
            ->post(route('account.customer-categories.store'), [
                'name' => 'Export Buyers',
                'code' => 'EXP',
                'description' => 'International buyers',
            ])
            ->assertSessionHasNoErrors();

        $categoryB = CustomerCategory::query()
            ->where('created_by', $companyB->id)
            ->where('name', 'Export Buyers')
            ->first();

        $this->assertNotNull($categoryB);

        $this->actingAs($companyA)
            ->post(route('account.customers.store'), [
                'company_name' => 'Prime Looms Pvt Ltd',
                'contact_person_name' => 'Ayaan Merchant',
                'contact_person_email' => 'ayaan@primelooms.test',
                'contact_person_mobile' => '+911234567890',
                'tax_number' => 'GST-PL-100',
                'payment_terms' => 'Net 30',
                'customer_category_id' => $categoryA->id,
                'operating_model' => 'full_package_buyer',
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
                'billing_address' => [
                    'name' => 'Prime Looms Billing',
                    'address_line_1' => '22 Textile Park',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395003',
                ],
                'shipping_address' => [
                    'name' => 'Prime Looms Dispatch',
                    'address_line_1' => 'Unit 7, Loom Estate',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395004',
                ],
                'same_as_billing' => false,
                'notes' => 'Category test customer',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()
            ->where('created_by', $companyA->id)
            ->where('company_name', 'Prime Looms Pvt Ltd')
            ->latest('id')
            ->first();

        $this->assertNotNull($customer);
        $this->assertSame($categoryA->id, $customer->customer_category_id);

        // Cross-tenant category assignment should be ignored by scoped resolution.
        $this->actingAs($companyA)
            ->put(route('account.customers.update', $customer->id), [
                'company_name' => 'Prime Looms Pvt Ltd',
                'contact_person_name' => 'Ayaan Merchant',
                'contact_person_email' => 'ayaan@primelooms.test',
                'contact_person_mobile' => '+911234567890',
                'tax_number' => 'GST-PL-100',
                'payment_terms' => 'Net 45',
                'customer_category_id' => $categoryB->id,
                'operating_model' => 'full_package_buyer',
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
                'billing_address' => [
                    'name' => 'Prime Looms Billing',
                    'address_line_1' => '22 Textile Park',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395003',
                ],
                'shipping_address' => [
                    'name' => 'Prime Looms Billing',
                    'address_line_1' => '22 Textile Park',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395003',
                ],
                'same_as_billing' => true,
                'notes' => 'Category update test',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertNull($customer->customer_category_id);

        $this->actingAs($companyA)
            ->get(route('account.customer-categories.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->where('categories.0.name', 'Domestic Retail');
            });

        $this->actingAs($companyA)
            ->put(route('account.customer-categories.update', $categoryA->id), [
                'name' => 'Domestic Retail Updated',
                'code' => 'DOM-RTL-1',
                'description' => 'Updated',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $categoryA->refresh();
        $this->assertSame('Domestic Retail Updated', $categoryA->name);
        $this->assertFalse((bool) $categoryA->is_active);

        $this->actingAs($companyA)
            ->delete(route('account.customer-categories.destroy', $categoryA->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('account_customer_categories', [
            'id' => $categoryA->id,
        ]);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Customer Category Plan',
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
