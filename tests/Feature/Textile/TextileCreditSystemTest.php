<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Hrm\Models\Branch;

class TextileCreditSystemTest extends TestCase
{
    use RefreshDatabase;

    private function enableTextileModule(): void
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
            'module' => 'TextileInventory',
            'name' => 'Textile Inventory',
            'package_name' => 'textile-inventory',
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
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Credit System Plan',
            'modules' => ['TextileCore'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web'], ['label' => 'Company', 'created_by' => $company->id]);

        foreach (['create-customers', 'edit-customers', 'manage-customers', 'manage-any-customers', 'create-vendors', 'edit-vendors', 'manage-vendors', 'manage-any-vendors'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'parties',
                'label' => $permissionName,
                'add_on' => 'Account',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);

        UserActiveModule::create(['user_id' => $company->id, 'module' => 'TextileCore']);
        UserActiveModule::create(['user_id' => $company->id, 'module' => 'Account']);

        return $company;
    }

    private function makeVendor(User $company, array $overrides = []): Vendor
    {
        $vendorUser = User::create([
            'name' => 'Credit Vendor User',
            'email' => 'credit.vendor.' . uniqid() . '@example.test',
            'type' => 'vendor',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        return Vendor::create(array_merge([
            'user_id' => $vendorUser->id,
            'company_name' => 'Yarn Credit Supplier Co',
            'contact_person_name' => 'Supplier Contact',
            'contact_person_email' => 'vendor@example.test',
            'supplier_type' => 'yarn',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ], $overrides));
    }

    private function makeCustomer(User $company, array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'company_name' => 'Credit Fabric Buyer Co',
            'contact_person_name' => 'Buyer Contact',
            'contact_person_email' => 'buyer@example.test',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ], $overrides));
    }

    public function test_grn_invoice_due_date_honors_vendor_credit_days_when_credit_enabled(): void
    {
        $this->enableTextileModule();

        $company = $this->company();

        // Vendor opts into credit: 30 days payment window.
        $vendor = $this->makeVendor($company, [
            'credit_enabled' => true,
            'credit_days' => 30,
            'credit_limit' => 500000,
        ]);

        // Purchase order carries the vendor user as the party (user_id match).
        $purchaseOrder = TextileWorkflowDocument::create([
            'document_type' => 'purchase_order',
            'document_number' => 'PO-CREDIT-1',
            'party_name' => $vendor->company_name,
            'status' => 'approved',
            'metadata' => [
                'vendor_id' => $vendor->user_id,
                'invoice_amount' => 10000,
            ],
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $grn = TextileWorkflowDocument::create([
            'document_type' => 'grn',
            'document_number' => 'GRN-CREDIT-1',
            'source_reference_type' => 'purchase_order',
            'source_reference_id' => $purchaseOrder->id,
            'source_action' => 'grn_from_purchase_order',
            'party_name' => $vendor->company_name,
            'status' => 'released',
            'quantity' => 100,
            'unit' => 'kg',
            'metadata' => [
                'vendor_id' => $vendor->user_id,
                'invoice_amount' => 10000,
            ],
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.procurement.invoices.from-grn'), [
                'grn_id' => $grn->id,
            ])
            ->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()
            ->where('created_by', $company->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(now()->addDays(30)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame('Net 30', $invoice->payment_terms);
    }

    public function test_grn_invoice_falls_back_to_same_day_due_when_vendor_credit_disabled(): void
    {
        $this->enableTextileModule();

        $company = $this->company();

        // Vendor exists but has NOT opted into credit (credit_enabled default false).
        $vendor = $this->makeVendor($company, [
            'credit_enabled' => false,
            'credit_days' => 45,
        ]);

        $purchaseOrder = TextileWorkflowDocument::create([
            'document_type' => 'purchase_order',
            'document_number' => 'PO-CASH-1',
            'party_name' => $vendor->company_name,
            'status' => 'approved',
            'metadata' => [
                'vendor_id' => $vendor->user_id,
                'invoice_amount' => 5000,
            ],
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $grn = TextileWorkflowDocument::create([
            'document_type' => 'grn',
            'document_number' => 'GRN-CASH-1',
            'source_reference_type' => 'purchase_order',
            'source_reference_id' => $purchaseOrder->id,
            'source_action' => 'grn_from_purchase_order',
            'party_name' => $vendor->company_name,
            'status' => 'released',
            'quantity' => 50,
            'unit' => 'kg',
            'metadata' => [
                'vendor_id' => $vendor->user_id,
                'invoice_amount' => 5000,
            ],
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.procurement.invoices.from-grn'), [
                'grn_id' => $grn->id,
            ])
            ->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()
            ->where('created_by', $company->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(now()->toDateString(), $invoice->due_date->toDateString());
        $this->assertNull($invoice->payment_terms);
    }

    public function test_customer_master_store_persists_credit_days_and_credit_enabled(): void
    {
        $this->enableTextileModule();

        $company = $this->company();

        $this->actingAs($company)
            ->post(route('account.customers.store'), [
                'company_name' => 'Credit Buyer Ltd',
                'contact_person_name' => 'Buyer Person',
                'contact_person_email' => 'buyer.ltd@example.test',
                'contact_person_mobile' => '+911234567890',
                'tax_number' => 'TX-991',
                'payment_terms' => 'Net 60',
                'credit_limit' => 750000,
                'credit_days' => 60,
                'credit_enabled' => 1,
                'currency_code' => 'INR',
                'operating_model' => 'full_package_buyer',
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
                'billing_address' => [
                    'name' => 'Credit Buyer Ltd',
                    'address_line_1' => '1 Main Street',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395001',
                ],
                'same_as_billing' => 1,
                'shipping_address' => [
                    'name' => 'Credit Buyer Ltd',
                    'address_line_1' => '1 Main Street',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395001',
                ],
                'notes' => 'Credit customer',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::query()->where('created_by', $company->id)->latest('id')->first();

        $this->assertNotNull($customer);
        $this->assertTrue((bool) $customer->credit_enabled);
        $this->assertSame(60, (int) $customer->credit_days);
        $this->assertEquals(750000, (float) $customer->credit_limit);
    }

    public function test_vendor_master_store_persists_credit_days_credit_limit_and_credit_enabled(): void
    {
        $this->enableTextileModule();

        $company = $this->company();

        $this->actingAs($company)
            ->post(route('account.vendors.store'), [
                'supplier_type' => 'yarn',
                'company_name' => 'Credit Yarn Mills',
                'contact_person_name' => 'Mills Contact',
                'contact_person_email' => 'mills@example.test',
                'contact_person_mobile' => '+911234567891',
                'tax_number' => 'TX-992',
                'payment_terms' => 'Net 45',
                'credit_limit' => 900000,
                'credit_days' => 45,
                'credit_enabled' => 1,
                'currency_code' => 'INR',
                'billing_address' => [
                    'name' => 'Credit Yarn Mills',
                    'address_line_1' => '2 Mill Road',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '380001',
                ],
                'same_as_billing' => 1,
                'shipping_address' => [
                    'name' => 'Credit Yarn Mills',
                    'address_line_1' => '2 Mill Road',
                    'city' => 'Ahmedabad',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '380001',
                ],
                'notes' => 'Credit vendor',
            ])
            ->assertSessionHasNoErrors();

        $vendor = Vendor::query()->where('created_by', $company->id)->latest('id')->first();

        $this->assertNotNull($vendor);
        $this->assertTrue((bool) $vendor->credit_enabled);
        $this->assertSame(45, (int) $vendor->credit_days);
        $this->assertEquals(900000, (float) $vendor->credit_limit);
    }
}
