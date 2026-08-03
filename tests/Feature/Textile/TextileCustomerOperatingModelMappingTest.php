<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerDocument;
use Workdo\Account\Models\CustomerPriceList;
use Workdo\ProductService\Models\ProductServiceItem;

class TextileCustomerOperatingModelMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_trader_bulk_customer_requires_credit_limit_and_price_list_before_sales_order(): void
    {
        $company = $this->companyWithModules();

        $traderCustomer = Customer::create([
            'company_name' => 'Bulk Trader House',
            'contact_person_name' => 'Vikram Jain',
            'contact_person_email' => 'vikram@bulktrader.test',
            'operating_model' => 'trader_bulk',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'source_reference_type' => 'sales_quotation',
                'source_reference_id' => 9101,
                'source_action' => 'convert',
                'customer_id' => $traderCustomer->id,
                'party_name' => $traderCustomer->company_name,
                'lot_reference' => 'LOT-TR-1',
                'quantity' => 500,
                'unit' => 'mtr',
            ])
            ->assertSessionHasErrors('source_reference_id');

        $traderCustomer->credit_limit = 100000;
        $traderCustomer->currency_code = 'INR';
        $traderCustomer->save();

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'source_reference_type' => 'sales_quotation',
                'source_reference_id' => 9102,
                'source_action' => 'convert',
                'customer_id' => $traderCustomer->id,
                'party_name' => $traderCustomer->company_name,
                'lot_reference' => 'LOT-TR-2',
                'quantity' => 320,
                'unit' => 'mtr',
            ])
            ->assertSessionHasErrors('source_reference_id');

        $item = ProductServiceItem::create([
            'name' => 'Polyester Blend Fabric',
            'sku' => 'TBF-001',
            'type' => 'finished_fabric',
            'sale_price' => 180,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        CustomerPriceList::create([
            'customer_id' => $traderCustomer->id,
            'product_service_item_id' => $item->id,
            'unit_price' => 170,
            'currency_code' => 'INR',
            'min_quantity' => 1,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'source_reference_type' => 'sales_quotation',
                'source_reference_id' => 9103,
                'source_action' => 'convert',
                'customer_id' => $traderCustomer->id,
                'party_name' => $traderCustomer->company_name,
                'lot_reference' => 'LOT-TR-3',
                'quantity' => 260,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('textile_workflow_documents', [
            'created_by' => $company->id,
            'document_type' => 'sales_order',
            'lot_reference' => 'LOT-TR-3',
        ]);
    }

    public function test_export_compliance_customer_requires_active_compliance_document_before_dispatch(): void
    {
        $company = $this->companyWithModules();

        $exportCustomer = Customer::create([
            'company_name' => 'Export Looms Global',
            'contact_person_name' => 'Aarav Mehta',
            'contact_person_email' => 'aarav@exportlooms.test',
            'operating_model' => 'export_compliance',
            'material_ownership' => 'mixed',
            'billing_mode' => 'hybrid',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'source_reference_type' => 'sales_quotation',
                'source_reference_id' => 9201,
                'source_action' => 'convert',
                'customer_id' => $exportCustomer->id,
                'party_name' => $exportCustomer->company_name,
                'lot_reference' => 'LOT-EX-1',
                'quantity' => 180,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $salesOrder = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'sales_order')
            ->where('lot_reference', 'LOT-EX-1')
            ->latest('id')
            ->first();

        $this->assertNotNull($salesOrder);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.approve'), ['sales_order_id' => $salesOrder->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($company)
            ->post(route('textile.sales.allocations.store'), ['sales_order_id' => $salesOrder->id])
            ->assertSessionHasNoErrors();

        $allocation = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'allocation')
            ->latest('id')
            ->first();

        $this->assertNotNull($allocation);

        $this->actingAs($company)
            ->post(route('textile.sales.allocations.release'), ['allocation_id' => $allocation->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($company)
            ->post(route('textile.sales.dispatches.store'), ['allocation_id' => $allocation->id])
            ->assertSessionHasErrors('allocation_id');

        CustomerDocument::create([
            'customer_id' => $exportCustomer->id,
            'document_name' => 'Export Compliance Pack',
            'document_type' => 'compliance',
            'document_reference' => 'COMP-EX-1',
            'status' => 'active',
            'issue_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addMonth()->toDateString(),
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.dispatches.store'), ['allocation_id' => $allocation->id])
            ->assertSessionHasNoErrors();
    }

    private function companyWithModules(): User
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

        AddOn::create([
            'module' => 'ProductService',
            'name' => 'Product Service',
            'package_name' => 'product-service',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $plan = Plan::create([
            'name' => 'Textile Mapping Plan',
            'modules' => ['TextileCore', 'Account', 'ProductService'],
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

        foreach (['manage-customers', 'create-customers', 'edit-customers', 'delete-customers', 'manage-any-customers'] as $permissionName) {
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

        UserActiveModule::create(['user_id' => $company->id, 'module' => 'TextileCore']);
        UserActiveModule::create(['user_id' => $company->id, 'module' => 'Account']);
        UserActiveModule::create(['user_id' => $company->id, 'module' => 'ProductService']);

        return $company;
    }
}
