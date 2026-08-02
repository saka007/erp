<?php

namespace Tests\Feature\Purchase;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;

class PurchaseInvoiceVendorTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_invoice_list_exposes_vendor_supplier_types_and_filters_them(): void
    {
        AddOn::create([
            'module' => 'ProductService',
            'name' => 'Product Service',
            'package_name' => 'product-service',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'ProductService',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'company',
            'guard_name' => 'web',
        ], [
            'label' => 'Company',
            'created_by' => $company->id,
        ]);

        foreach (['manage-purchase-invoices', 'manage-any-purchase-invoices', 'create-purchase-invoices', 'view-purchase-invoices', 'manage-purchase-return-invoices', 'manage-any-purchase-return-invoices', 'view-purchase-return-invoices'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'purchase',
                'label' => $permissionName,
                'add_on' => 'Purchase',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);

        $product = $this->product($company->id);
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Main Road',
            'city' => 'Lahore',
            'zip_code' => '54000',
            'phone' => '0000000000',
            'email' => 'warehouse@example.com',
            'created_by' => $company->id,
            'creator_id' => $company->id,
            'is_active' => true,
        ]);

        $yarnVendorUser = $this->vendorUser($company->id, 'Sunrise Yarns');
        $chemicalVendorUser = $this->vendorUser($company->id, 'Bright Chemicals');

        Vendor::create([
            'user_id' => $yarnVendorUser->id,
            'company_name' => 'Sunrise Yarns',
            'supplier_type' => 'yarn',
            'contact_person_name' => 'Yarn Buyer',
            'billing_address' => 'Yarn Billing',
            'shipping_address' => 'Yarn Shipping',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        Vendor::create([
            'user_id' => $chemicalVendorUser->id,
            'company_name' => 'Bright Chemicals',
            'supplier_type' => 'chemical',
            'contact_person_name' => 'Chemical Buyer',
            'billing_address' => 'Chemical Billing',
            'shipping_address' => 'Chemical Shipping',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $yarnInvoice = $this->createInvoice($company->id, $yarnVendorUser->id, $warehouse->id, $product->id, 'PI-YARN-001');
        $chemicalInvoice = $this->createInvoice($company->id, $chemicalVendorUser->id, $warehouse->id, $product->id, 'PI-CHEM-001');
        $this->createReturn($company->id, $yarnVendorUser->id, $warehouse->id, $product->id, $yarnInvoice->id, $yarnInvoice->items->first()->id, $yarnInvoice->items->first()->quantity, 'PR-YARN-001');
        $this->createReturn($company->id, $chemicalVendorUser->id, $warehouse->id, $product->id, $chemicalInvoice->id, $chemicalInvoice->items->first()->id, $chemicalInvoice->items->first()->quantity, 'PR-CHEM-001');

        $this->actingAs($company)
            ->get(route('purchase-invoices.index', ['vendor_type' => 'yarn']))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->where('invoices.data.0.vendor_type', 'yarn');
                $this->assertCount(1, $page->toArray()['props']['invoices']['data']);
            });

        $this->actingAs($company)
            ->get(route('purchase-invoices.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($yarnInvoice): void {
                $page->where('invoices.data.0.vendor_type', 'yarn');
                $this->assertSame($yarnInvoice->id, $page->toArray()['props']['invoices']['data'][0]['id']);
            });

        $this->actingAs($company)
            ->get(route('purchase-returns.index', ['vendor_type' => 'chemical']))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->where('returns.data.0.vendor_type', 'chemical');
                $this->assertCount(1, $page->toArray()['props']['returns']['data']);
            });
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Purchase Test Plan',
            'modules' => ['ProductService'],
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

    private function vendorUser(int $companyId, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
            'type' => 'vendor',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'created_by' => $companyId,
        ]);
    }

    private function product(int $companyId): ProductServiceItem
    {
        $category = ProductServiceCategory::create([
            'name' => 'Textile Products',
            'color' => '#000000',
            'creator_id' => $companyId,
            'created_by' => $companyId,
        ]);

        ProductServiceTax::create([
            'tax_name' => 'GST',
            'rate' => 5,
            'creator_id' => $companyId,
            'created_by' => $companyId,
        ]);

        $unit = ProductServiceUnit::create([
            'unit_name' => 'Kg',
            'creator_id' => $companyId,
            'created_by' => $companyId,
        ]);

        return ProductServiceItem::create([
            'name' => 'Cotton Yarn',
            'sku' => 'YRN-001',
            'category_id' => $category->id,
            'description' => 'Test product',
            'sale_price' => 120,
            'purchase_price' => 90,
            'unit' => (string) $unit->id,
            'type' => 'yarn',
            'is_active' => true,
            'creator_id' => $companyId,
            'created_by' => $companyId,
        ]);
    }

    private function createInvoice(int $companyId, int $vendorId, int $warehouseId, int $productId, string $invoiceNumber): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'vendor_id' => $vendorId,
            'warehouse_id' => $warehouseId,
            'subtotal' => 100,
            'tax_amount' => 5,
            'discount_amount' => 0,
            'total_amount' => 105,
            'paid_amount' => 0,
            'balance_amount' => 105,
            'status' => 'draft',
            'payment_terms' => 'Net 15',
            'notes' => 'Test invoice',
            'creator_id' => $companyId,
            'created_by' => $companyId,
        ]);

        PurchaseInvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $productId,
            'quantity' => 1,
            'unit_price' => 100,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 5,
            'tax_amount' => 5,
            'total_amount' => 105,
        ]);

        return $invoice;
    }

    private function createReturn(int $companyId, int $vendorId, int $warehouseId, int $productId, int $invoiceId, int $invoiceItemId, int $originalQuantity, string $returnNumber): PurchaseReturn
    {
        $return = PurchaseReturn::create([
            'return_number' => $returnNumber,
            'return_date' => now()->toDateString(),
            'vendor_id' => $vendorId,
            'warehouse_id' => $warehouseId,
            'original_invoice_id' => $invoiceId,
            'subtotal' => 100,
            'tax_amount' => 5,
            'discount_amount' => 0,
            'total_amount' => 105,
            'status' => 'draft',
            'reason' => 'defective',
            'notes' => 'Test return',
            'creator_id' => $companyId,
            'created_by' => $companyId,
        ]);

        PurchaseReturnItem::create([
            'return_id' => $return->id,
            'original_invoice_item_id' => $invoiceItemId,
            'product_id' => $productId,
            'original_quantity' => $originalQuantity,
            'quantity' => 1,
            'return_quantity' => 1,
            'unit_price' => 100,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_percentage' => 5,
            'tax_amount' => 5,
            'total_amount' => 105,
        ]);

        return $return;
    }
}
