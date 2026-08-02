<?php

namespace Tests\Feature\ProductService;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;

class ProductTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_textile_product_types_can_be_created_updated_and_filtered(): void
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

        foreach ([
            'manage-product-service-item',
            'manage-any-product-service-item',
            'create-product-service-item',
            'edit-product-service-item',
            'view-product-service-item',
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

        $category = ProductServiceCategory::create([
            'name' => 'Textile Yarns',
            'color' => '#000000',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $tax = ProductServiceTax::create([
            'tax_name' => 'GST',
            'rate' => 5,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $unit = ProductServiceUnit::create([
            'unit_name' => 'Kg',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Main Road',
            'city' => 'Lahore',
            'zip_code' => '54000',
            'phone' => '0000000000',
            'email' => 'warehouse@example.com',
            'created_by' => $company->id,
            'is_active' => true,
        ]);

        $this->actingAs($company)
            ->post(route('product-service.items.store'), [
                'name' => 'Cotton Yarn',
                'sku' => 'YRN-001',
                'tax_ids' => [(string) $tax->id],
                'category_id' => (string) $category->id,
                'description' => 'Yarn for weaving',
                'long_description' => 'High twist cotton yarn',
                'sale_price' => '120.00',
                'purchase_price' => '90.00',
                'unit' => (string) $unit->id,
                'quantity' => '25',
                'warehouse_id' => (string) $warehouse->id,
                'type' => 'yarn',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = ProductServiceItem::query()->where('created_by', $company->id)->first();

        $this->assertNotNull($item);
        $this->assertSame('yarn', $item->type);

        $this->actingAs($company)
            ->put(route('product-service.items.update', $item->id), [
                'name' => 'Cotton Yarn',
                'sku' => 'YRN-001',
                'tax_ids' => [(string) $tax->id],
                'category_id' => (string) $category->id,
                'description' => 'Fabric yarn',
                'long_description' => 'High twist cotton yarn',
                'sale_price' => '125.00',
                'purchase_price' => '95.00',
                'unit' => (string) $unit->id,
                'quantity' => '30',
                'warehouse_id' => (string) $warehouse->id,
                'type' => 'fabric',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame('fabric', $item->type);

        $this->actingAs($company)
            ->get(route('product-service.items.index', ['type' => 'fabric']))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->where('items.data.0.type', 'fabric');
                $page->where('items.data.0.name', 'Cotton Yarn');
            });
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
}
