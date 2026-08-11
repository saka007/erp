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
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\VendorPriceList;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;

class VendorCustomerPriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_update_syncs_per_product_price_lists(): void
    {
        [$company, $vendor] = $this->makeVendor();

        $this->enableAccountModule($company);

        $product = $this->makeProduct($company, 'YRN-40S-COM');

        // Add two price rows, save
        $this->actingAs($company)
            ->put(route('account.vendors.update', $vendor->id), $this->vendorPayload() + [
                'price_lists' => [
                    ['product_service_item_id' => $product->id, 'unit_price' => 170.00, 'min_quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_vendor_price_lists', [
            'created_by' => $company->id,
            'vendor_id' => $vendor->id,
            'product_service_item_id' => $product->id,
            'unit_price' => 170.00,
        ]);

        // Update rate + remove via omitted rows, save again
        $this->actingAs($company)
            ->put(route('account.vendors.update', $vendor->id), $this->vendorPayload() + [
                'price_lists' => [
                    ['product_service_item_id' => $product->id, 'unit_price' => 165.50, 'min_quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_vendor_price_lists', [
            'created_by' => $company->id,
            'vendor_id' => $vendor->id,
            'product_service_item_id' => $product->id,
            'unit_price' => 165.50,
        ]);

        // Empty rows → all price lists removed
        $this->actingAs($company)
            ->put(route('account.vendors.update', $vendor->id), $this->vendorPayload() + [
                'price_lists' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('account_vendor_price_lists', [
            'created_by' => $company->id,
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_vendor_index_exposes_items_and_price_lists(): void
    {
        [$company, $vendor] = $this->makeVendor();

        $this->enableAccountModule($company);

        $product = $this->makeProduct($company, 'YRN-40S-COM');

        VendorPriceList::create([
            'vendor_id' => $vendor->id,
            'product_service_item_id' => $product->id,
            'unit_price' => 169.75,
            'currency_code' => 'INR',
            'min_quantity' => 1,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->get(route('account.vendors.index'))
            ->assertOk()
            ->assertInertia(function ($page) use ($vendor, $product) {
                $page->has('items', 1)
                    ->where('items.0.id', $product->id)
                    ->where('vendors.data.0.price_lists.0.product_service_item_id', $product->id)
                    ->where('vendors.data.0.price_lists.0.unit_price', '169.75');
            });
    }

    public function test_customer_update_syncs_per_product_price_lists(): void
    {
        [$company, $customer] = $this->makeCustomer();

        $this->enableAccountModule($company);

        $product = $this->makeProduct($company, 'YRN-40S-COM');

        $this->actingAs($company)
            ->put(route('account.customers.update', $customer->id), $this->customerPayload() + [
                'price_lists' => [
                    ['product_service_item_id' => $product->id, 'unit_price' => 205.00, 'min_quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_customer_price_lists', [
            'created_by' => $company->id,
            'customer_id' => $customer->id,
            'product_service_item_id' => $product->id,
            'unit_price' => 205.00,
        ]);

        $this->actingAs($company)
            ->put(route('account.customers.update', $customer->id), $this->customerPayload() + [
                'price_lists' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('account_customer_price_lists', [
            'created_by' => $company->id,
            'customer_id' => $customer->id,
        ]);
    }

    private function makeVendor(): array
    {
        $company = $this->company();

        $vendor = Vendor::create([
            'company_name' => 'Shree Yarn Traders',
            'supplier_type' => 'yarn',
            'contact_person_name' => 'Ramesh',
            'contact_person_email' => 'ramesh@example.test',
            'contact_person_mobile' => '9876543210',
            'payment_terms' => 'Net 30',
            'billing_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'shipping_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'same_as_billing' => false,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        return [$company, $vendor];
    }

    private function makeCustomer(): array
    {
        $company = $this->company();

        $customer = Customer::create([
            'company_name' => 'Metro Fashions Pvt Ltd',
            'contact_person_name' => 'Suresh',
            'contact_person_email' => 'suresh@example.test',
            'contact_person_mobile' => '9876543211',
            'payment_terms' => 'Net 30',
            'billing_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'shipping_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'same_as_billing' => false,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        return [$company, $customer];
    }

    private function makeProduct(User $company, string $sku): ProductServiceItem
    {
        $unit = ProductServiceUnit::create([
            'unit_name' => 'Kilogram',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        return ProductServiceItem::create([
            'name' => 'Combed Yarn 40s',
            'sku' => $sku,
            'type' => 'yarn',
            'unit' => $unit->id,
            'purchase_price' => 175.00,
            'sale_price' => 205.00,
            'is_active' => 1,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
    }

    private function vendorPayload(): array
    {
        return [
            'supplier_type' => 'yarn',
            'company_name' => 'Shree Yarn Traders',
            'contact_person_name' => 'Ramesh',
            'contact_person_email' => 'ramesh@example.test',
            'contact_person_mobile' => '9876543210',
            'tax_number' => 'TAX-V-1',
            'payment_terms' => 'Net 30',
            'credit_limit' => 500000,
            'credit_days' => 30,
            'credit_enabled' => true,
            'billing_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'shipping_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'same_as_billing' => false,
            'notes' => 'Yarn supplier',
        ];
    }

    private function customerPayload(): array
    {
        return [
            'company_name' => 'Metro Fashions Pvt Ltd',
            'contact_person_name' => 'Suresh',
            'contact_person_email' => 'suresh@example.test',
            'contact_person_mobile' => '9876543211',
            'tax_number' => 'TAX-C-1',
            'payment_terms' => 'Net 30',
            'credit_limit' => 500000,
            'credit_days' => 30,
            'credit_enabled' => true,
            'default_rate' => 45.00,
            'currency_code' => 'INR',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'billing_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'shipping_address' => ['name' => 'B', 'address_line_1' => 'L1', 'address_line_2' => '', 'city' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'zip_code' => '395001'],
            'same_as_billing' => false,
            'notes' => 'Full package buyer',
        ];
    }

    private function enableAccountModule(User $company): void
    {
        AddOn::firstOrCreate(
            ['module' => 'Account'],
            ['name' => 'Account', 'package_name' => 'account', 'is_enable' => true, 'monthly_price' => 0, 'yearly_price' => 0]
        );

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'Account',
        ]);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Vendor Customer Price List Plan',
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

        foreach (['manage-vendors', 'manage-any-vendors', 'create-vendors', 'edit-vendors', 'delete-vendors', 'manage-customers', 'manage-any-customers', 'create-customers', 'edit-customers', 'delete-customers'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'account',
                'label' => $permissionName,
                'add_on' => 'Account',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);

        return $company;
    }
}
