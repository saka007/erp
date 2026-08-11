<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;

class TextileInvoiceHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_hub_lists_sales_and_purchase_invoices_scoped_to_tenant(): void
    {
        $company = $this->enableTextileModule();
        $this->actingAs($company);

        $product = $this->makeProduct($company, 'YRN-HUB-001');
        $customerUser = $this->makeCustomerUser($company);
        $vendorUser = $this->makeVendorUser($company);

        $salesInvoice = SalesInvoice::create([
            'invoice_number' => 'SI-HUB-0001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(15),
            'customer_id' => $customerUser->id,
            'subtotal' => 5000.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 5000.00,
            'paid_amount' => 1000.00,
            'balance_amount' => 4000.00,
            'status' => 'partial',
            'type' => 'product',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        SalesInvoiceItem::create([
            'invoice_id' => $salesInvoice->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 500.00,
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ]);

        $purchaseInvoice = PurchaseInvoice::create([
            'invoice_number' => 'PI-HUB-0001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'vendor_id' => $vendorUser->id,
            'subtotal' => 17000.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 17000.00,
            'paid_amount' => 0,
            'balance_amount' => 17000.00,
            'status' => 'draft',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        PurchaseInvoiceItem::create([
            'invoice_id' => $purchaseInvoice->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 170.00,
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ]);

        $response = $this->get(route('textile.invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('DigitalFuzedTextileCore/Invoices/Index', false)
            ->has('salesInvoices', 1)
            ->has('purchaseInvoices', 1)
            ->has('jobWorkInvoices', 0)
            ->where('salesInvoices.0.invoice_number', 'SI-HUB-0001')
            ->where('salesInvoices.0.total_amount', 5000)
            ->where('salesInvoices.0.balance_amount', 4000)
            ->where('salesInvoices.0.status', 'partial')
            ->where('purchaseInvoices.0.invoice_number', 'PI-HUB-0001')
            ->where('purchaseInvoices.0.total_amount', 17000)
            ->where('purchaseInvoices.0.balance_amount', 17000)
            ->where('purchaseInvoices.0.status', 'draft')
        );
    }

    public function test_invoice_hub_does_not_leak_other_tenant_invoices(): void
    {
        $company = $this->enableTextileModule();
        $this->actingAs($company);

        $otherTenant = User::factory()->create([
            'type' => 'company',
            'email_verified_at' => now(),
        ]);

        $customerUser = $this->makeCustomerUser($otherTenant);

        // Invoice belonging to another tenant.
        SalesInvoice::create([
            'invoice_number' => 'SI-OTHER-0001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(15),
            'customer_id' => $customerUser->id,
            'subtotal' => 999.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 999.00,
            'paid_amount' => 0,
            'balance_amount' => 999.00,
            'status' => 'draft',
            'type' => 'product',
            'creator_id' => $otherTenant->id,
            'created_by' => $otherTenant->id,
        ]);

        $response = $this->get(route('textile.invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('DigitalFuzedTextileCore/Invoices/Index', false)
            ->has('salesInvoices', 0)
            ->has('purchaseInvoices', 0)
            ->has('jobWorkInvoices', 0)
        );
    }

    public function test_invoice_hub_groups_service_type_sales_invoices_under_job_work(): void
    {
        $company = $this->enableTextileModule();
        $this->actingAs($company);

        $customerUser = $this->makeCustomerUser($company);

        SalesInvoice::create([
            'invoice_number' => 'SI-JW-0001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(15),
            'customer_id' => $customerUser->id,
            'subtotal' => 2500.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 2500.00,
            'paid_amount' => 0,
            'balance_amount' => 2500.00,
            'status' => 'posted',
            'type' => 'service',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $response = $this->get(route('textile.invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('DigitalFuzedTextileCore/Invoices/Index', false)
            ->has('salesInvoices', 0)
            ->has('purchaseInvoices', 0)
            ->has('jobWorkInvoices', 1)
            ->where('jobWorkInvoices.0.invoice_number', 'SI-JW-0001')
            ->where('jobWorkInvoices.0.kind', 'job-work')
        );
    }

    public function test_non_textile_user_cannot_access_invoice_hub(): void
    {
        // A user type outside the allowed set (company/superadmin/staff) must be
        // rejected by the controller even when the TextileCore module is active.
        $company = $this->enableTextileModule();
        $user = User::factory()->create([
            'type' => 'client',
            'created_by' => $company->id,
            'creator_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('textile.invoices.index'))
            ->assertStatus(403);
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

    private function makeCustomerUser(User $company): User
    {
        $customer = Customer::create([
            'company_name' => 'Metro Fashions',
            'contact_person_name' => 'Metro Contact',
            'contact_person_email' => 'metro@example.test',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $user = User::create([
            'name' => $customer->company_name,
            'email' => $customer->contact_person_email,
            'password' => bcrypt('secret'),
            'type' => 'client',
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);

        $customer->update(['user_id' => $user->id]);

        return $user;
    }

    private function makeVendorUser(User $company): User
    {
        $vendor = Vendor::create([
            'company_name' => 'Shree Yarn Traders',
            'supplier_type' => 'yarn',
            'contact_person_name' => 'Shree Contact',
            'contact_person_email' => 'shree@example.test',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $user = User::create([
            'name' => $vendor->company_name,
            'email' => $vendor->contact_person_email,
            'password' => bcrypt('secret'),
            'type' => 'vendor',
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);

        $vendor->update(['user_id' => $user->id]);

        return $user;
    }

    private function enableTextileModule(): User
    {
        AddOn::firstOrCreate(
            ['module' => 'TextileCore'],
            ['name' => 'Textile Core', 'package_name' => 'textile-core', 'is_enable' => true, 'monthly_price' => 0, 'yearly_price' => 0]
        );

        $company = $this->company();

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        return $company;
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Invoice Hub Plan',
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

        $company->assignRole($role);

        return $company;
    }
}
