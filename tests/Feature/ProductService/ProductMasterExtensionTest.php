<?php

namespace Tests\Feature\ProductService;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceItemDocument;
use Workdo\ProductService\Models\ProductServiceItemImage;
use Workdo\ProductService\Models\ProductServiceItemVariant;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;

class ProductMasterExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_master_extensions_and_yarn_tracking_are_tenant_scoped(): void
    {
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

        UserActiveModule::create([
            'user_id' => $companyA->id,
            'module' => 'ProductService',
        ]);

        UserActiveModule::create([
            'user_id' => $companyB->id,
            'module' => 'ProductService',
        ]);

        $this->grantProductPermissions($companyA);
        $this->grantProductPermissions($companyB);

        $categoryA = ProductServiceCategory::create([
            'name' => 'Yarn Category',
            'color' => '#111111',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $taxA = ProductServiceTax::create([
            'tax_name' => 'GST 5',
            'rate' => 5,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $unitA = ProductServiceUnit::create([
            'unit_name' => 'Kg',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $warehouseA = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Street 1',
            'city' => 'Lahore',
            'zip_code' => '54000',
            'phone' => '0000000000',
            'email' => 'wh-a@example.com',
            'created_by' => $companyA->id,
            'is_active' => true,
        ]);

        $this->actingAs($companyA)
            ->post(route('product-service.items.store'), [
                'name' => 'Carded Cotton Yarn',
                'sku' => 'YRN-A-001',
                'tax_ids' => [(string) $taxA->id],
                'category_id' => (string) $categoryA->id,
                'description' => 'Yarn details',
                'long_description' => 'Yarn long details',
                'sale_price' => '190.00',
                'purchase_price' => '150.00',
                'unit' => (string) $unitA->id,
                'quantity' => '100',
                'warehouse_id' => (string) $warehouseA->id,
                'type' => 'yarn',
                'cone_number' => 'CONE-01',
                'cone_weight' => '1.250',
                'yarn_barcode' => 'BC-001',
                'yarn_qr_code' => 'QR-001',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $itemA = ProductServiceItem::query()->where('created_by', $companyA->id)->latest('id')->first();

        $this->assertNotNull($itemA);
        $this->assertSame('CONE-01', $itemA->cone_number);
        $this->assertSame('1.250', (string) $itemA->cone_weight);
        $this->assertSame('BC-001', $itemA->yarn_barcode);
        $this->assertSame('QR-001', $itemA->yarn_qr_code);

        $this->actingAs($companyA)
            ->put(route('product-service.items.update', $itemA->id), [
                'name' => 'Carded Cotton Yarn Updated',
                'sku' => 'YRN-A-001',
                'tax_ids' => [(string) $taxA->id],
                'category_id' => (string) $categoryA->id,
                'description' => 'Updated yarn details',
                'long_description' => 'Updated yarn long details',
                'sale_price' => '195.00',
                'purchase_price' => '152.00',
                'unit' => (string) $unitA->id,
                'quantity' => '120',
                'warehouse_id' => (string) $warehouseA->id,
                'type' => 'yarn',
                'cone_number' => 'CONE-02',
                'cone_weight' => '1.300',
                'yarn_barcode' => 'BC-002',
                'yarn_qr_code' => 'QR-002',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $itemA->refresh();
        $this->assertSame('CONE-02', $itemA->cone_number);
        $this->assertSame('1.300', (string) $itemA->cone_weight);
        $this->assertSame('BC-002', $itemA->yarn_barcode);
        $this->assertSame('QR-002', $itemA->yarn_qr_code);

        $this->actingAs($companyA)
            ->post(route('product-service.product-master.variants.store'), [
                'product_id' => $itemA->id,
                'variant_type' => 'count',
                'variant_label' => 'Count 40s',
                'variant_value' => '40',
                'unit' => 'Ne',
                'sku_suffix' => '40S',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $variant = ProductServiceItemVariant::query()->where('created_by', $companyA->id)->latest('id')->first();
        $this->assertNotNull($variant);

        $this->actingAs($companyA)
            ->put(route('product-service.product-master.variants.update', $variant->id), [
                'variant_type' => 'denier',
                'variant_label' => 'Denier 120',
                'variant_value' => '120',
                'unit' => 'D',
                'sku_suffix' => '120D',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $variant->refresh();
        $this->assertSame('denier', $variant->variant_type);

        $this->actingAs($companyA)
            ->post(route('product-service.product-master.images.store'), [
                'product_id' => $itemA->id,
                'image_path' => 'products/yarn/front.jpg',
                'sort_order' => 1,
                'is_primary' => true,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $image = ProductServiceItemImage::query()->where('created_by', $companyA->id)->latest('id')->first();
        $this->assertNotNull($image);
        $this->assertTrue((bool) $image->is_primary);

        $this->actingAs($companyA)
            ->post(route('product-service.product-master.documents.store'), [
                'product_id' => $itemA->id,
                'document_type' => 'spec_sheet',
                'document_number' => 'SPEC-001',
                'document_path' => 'products/yarn/spec-001.pdf',
                'issued_on' => now()->toDateString(),
                'expires_on' => now()->addMonth()->toDateString(),
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $document = ProductServiceItemDocument::query()->where('created_by', $companyA->id)->latest('id')->first();
        $this->assertNotNull($document);

        $this->actingAs($companyB)
            ->get(route('product-service.product-master.variants.index'))
            ->assertOk()
            ->assertDontSee('Count 40s')
            ->assertDontSee('Denier 120');

        $this->actingAs($companyA)
            ->get(route('product-service.product-master.documents.index'))
            ->assertOk()
            ->assertSee('SPEC-001');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Product Service Plan',
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

    private function grantProductPermissions(User $company): void
    {
        $role = Role::where('name', 'company')->where('guard_name', 'web')->firstOrFail();

        foreach ([
            'manage-product-service-item',
            'manage-any-product-service-item',
            'create-product-service-item',
            'edit-product-service-item',
            'view-product-service-item',
            'delete-product-service-item',
        ] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'product-service-item',
                'label' => str_replace('-', ' ', $permissionName),
                'add_on' => 'ProductService',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);
    }
}
