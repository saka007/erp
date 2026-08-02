<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;

class TextileCustomerOperatingProfileAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_create_and_update_customer_operating_profile_fields(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();

        $this->actingAs($company)
            ->post(route('account.customers.store'), [
                'company_name' => 'Prime Looms Pvt Ltd',
                'contact_person_name' => 'Ayaan Merchant',
                'contact_person_email' => 'ayaan@primelooms.test',
                'contact_person_mobile' => '+911234567890',
                'tax_number' => 'GST-PL-100',
                'payment_terms' => 'Net 30',
                'operating_model' => 'jobwork_weaving_beam_supplied',
                'material_ownership' => 'customer_owned',
                'billing_mode' => 'conversion_charge',
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
                'notes' => 'Powerloom customer profile',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()
            ->where('created_by', $company->id)
            ->where('company_name', 'Prime Looms Pvt Ltd')
            ->latest('id')
            ->first();

        $this->assertNotNull($customer);
        $this->assertSame('jobwork_weaving_beam_supplied', $customer->operating_model);
        $this->assertSame('customer_owned', $customer->material_ownership);
        $this->assertSame('conversion_charge', $customer->billing_mode);

        $this->actingAs($company)
            ->put(route('account.customers.update', $customer->id), [
                'company_name' => 'Prime Looms Pvt Ltd',
                'contact_person_name' => 'Ayaan Merchant',
                'contact_person_email' => 'ayaan@primelooms.test',
                'contact_person_mobile' => '+911234567890',
                'tax_number' => 'GST-PL-100',
                'payment_terms' => 'Net 45',
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
                'notes' => 'Converted profile',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('full_package_buyer', $customer->operating_model);
        $this->assertSame('company_owned', $customer->material_ownership);
        $this->assertSame('sale_value', $customer->billing_mode);
    }

    public function test_jobwork_customer_profile_is_blocked_from_sales_order_flow(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();

        $jobworkCustomer = Customer::create([
            'company_name' => 'Powerloom Ops',
            'contact_person_name' => 'Ayaan Merchant',
            'contact_person_email' => 'ayaan@powerloom.test',
            'contact_person_mobile' => '+911234567890',
            'operating_model' => 'jobwork_weaving_beam_supplied',
            'material_ownership' => 'customer_owned',
            'billing_mode' => 'conversion_charge',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'source_reference_type' => 'sales_quotation',
                'source_reference_id' => 11,
                'source_action' => 'convert',
                'customer_id' => $jobworkCustomer->id,
                'party_name' => 'Powerloom Ops',
                'lot_reference' => 'LOT-BLOCK-100',
                'quantity' => 100,
                'unit' => 'mtr',
            ])
            ->assertSessionHasErrors('source_reference_id');

        $fullPackageCustomer = Customer::create([
            'company_name' => 'Retail Fabric Buyer',
            'contact_person_name' => 'Rina Shah',
            'contact_person_email' => 'rina@retail.test',
            'contact_person_mobile' => '+919999999999',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'source_reference_type' => 'sales_quotation',
                'source_reference_id' => 12,
                'source_action' => 'convert',
                'customer_id' => $fullPackageCustomer->id,
                'party_name' => 'Retail Fabric Buyer',
                'lot_reference' => 'LOT-ALLOW-200',
                'quantity' => 180,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('textile_workflow_documents', [
            'created_by' => $company->id,
            'document_type' => 'sales_order',
            'lot_reference' => 'LOT-ALLOW-200',
        ]);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Customer Profile Plan',
            'modules' => ['TextileCore', 'Account'],
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

        foreach (['manage-customers', 'create-customers', 'edit-customers'] as $permissionName) {
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

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'Account',
        ]);

        return $company;
    }
}
