<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerPriceList;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\VendorPriceList;
use Workdo\Hrm\Models\Branch;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;

class TextilePriceListPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_requisition_carries_vendor_price_list_rate_and_invoice_amount(): void
    {
        $company = $this->enableTextileModule();

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => 'Shree Yarn Traders',
                'vendor_id' => $this->makeVendor($company)->id,
                'quantity' => 100,
                'unit' => 'kg',
                'product_service_item_id' => $this->makeProduct($company, 'YRN-TEST-1')->id,
                'rate' => 169.75,
            ])
            ->assertSessionHasNoErrors();

        $requisition = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'purchase_requisition')
            ->latest('id')
            ->first();

        $this->assertNotNull($requisition);
        $this->assertEquals(169.75, (float) $requisition->metadata['rate']);
        $this->assertEquals(16975.00, (float) $requisition->metadata['invoice_amount']);
        $this->assertSame('Combed Yarn 40s', $requisition->metadata['item_name']);
        $this->assertArrayHasKey('vendor_id', $requisition->metadata);
    }

    public function test_requisition_without_rate_keeps_legacy_behavior(): void
    {
        $company = $this->enableTextileModule();

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => 'Alpha Fibers',
                'quantity' => 50,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();

        $requisition = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'purchase_requisition')
            ->latest('id')
            ->first();

        $this->assertNotNull($requisition);
        $this->assertNull($requisition->metadata['rate'] ?? null);
        $this->assertNull($requisition->metadata['invoice_amount'] ?? null);
    }

    public function test_sales_order_resolves_rate_from_customer_price_list(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Prefill Branch', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $product = $this->makeProduct($company, 'YRN-TEST-2');
        $customer = $this->makeCustomer($company);

        CustomerPriceList::create([
            'customer_id' => $customer->id,
            'product_service_item_id' => $product->id,
            'unit_price' => 221.40,
            'currency_code' => 'INR',
            'min_quantity' => 1,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $takhaSource = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-PREFILL-001',
            'party_name' => 'Own Loom Unit',
            'lot_reference' => 'TAKHA-PREFILL-LOT',
            'quantity' => 100,
            'unit' => 'kg',
            'status' => 'approved',
            'metadata' => ['grade' => 'A'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'branch_id' => $branch->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'TAKHA-PREFILL-LOT',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $takhaSource->id,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        TextileWorkflowDocument::create([
            'document_type' => 'inspection',
            'document_number' => 'INSP-PREFILL-001',
            'lot_reference' => 'TAKHA-PREFILL-LOT',
            'quantity' => 100,
            'unit' => 'kg',
            'status' => 'approved',
            'metadata' => ['final_decision' => 'pass'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'customer_id' => $customer->id,
                'lot_selections' => [
                    ['lot_reference' => 'TAKHA-PREFILL-LOT', 'quantity' => 40],
                ],
                'product_service_item_id' => $product->id,
                'required_delivery_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $salesOrder = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'sales_order')
            ->latest('id')
            ->first();

        $this->assertNotNull($salesOrder);
        $this->assertEquals(221.40, (float) $salesOrder->metadata['rate']);
        $this->assertSame('customer_price_list', $salesOrder->metadata['rate_source']);
        $this->assertSame('Combed Yarn 40s', $salesOrder->metadata['item_name']);
    }

    public function test_sales_order_respects_submitted_rate_over_price_list(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Prefill Branch 2', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $product = $this->makeProduct($company, 'YRN-TEST-3');
        $customer = $this->makeCustomer($company);

        CustomerPriceList::create([
            'customer_id' => $customer->id,
            'product_service_item_id' => $product->id,
            'unit_price' => 221.40,
            'currency_code' => 'INR',
            'min_quantity' => 1,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $takhaSource = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-PREFILL-002',
            'party_name' => 'Own Loom Unit',
            'lot_reference' => 'TAKHA-PREFILL-LOT-2',
            'quantity' => 100,
            'unit' => 'kg',
            'status' => 'approved',
            'metadata' => ['grade' => 'A'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'branch_id' => $branch->id,
        ]);

        TextileLot::create([
            'lot_reference' => 'TAKHA-PREFILL-LOT-2',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $takhaSource->id,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        TextileWorkflowDocument::create([
            'document_type' => 'inspection',
            'document_number' => 'INSP-PREFILL-002',
            'lot_reference' => 'TAKHA-PREFILL-LOT-2',
            'quantity' => 100,
            'unit' => 'kg',
            'status' => 'approved',
            'metadata' => ['final_decision' => 'pass'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($company)
            ->post(route('textile.sales.orders.store'), [
                'customer_id' => $customer->id,
                'lot_selections' => [
                    ['lot_reference' => 'TAKHA-PREFILL-LOT-2', 'quantity' => 40],
                ],
                'product_service_item_id' => $product->id,
                'rate' => 230.00,
                'required_delivery_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $salesOrder = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'sales_order')
            ->latest('id')
            ->first();

        $this->assertNotNull($salesOrder);
        $this->assertEquals(230.00, (float) $salesOrder->metadata['rate']);
        $this->assertSame('manual', $salesOrder->metadata['rate_source']);
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

    private function makeVendor(User $company): Vendor
    {
        return Vendor::create([
            'company_name' => 'Shree Yarn Traders',
            'supplier_type' => 'yarn',
            'contact_person_name' => 'Ramesh',
            'contact_person_email' => 'ramesh@example.test',
            'credit_limit' => 500000,
            'credit_days' => 30,
            'credit_enabled' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
    }

    private function makeCustomer(User $company): Customer
    {
        return Customer::create([
            'company_name' => 'Metro Fashions',
            'contact_person_name' => 'Suresh',
            'contact_person_email' => 'suresh@example.test',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'credit_limit' => 500000,
            'credit_days' => 30,
            'credit_enabled' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
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
            'name' => 'Textile Price List Prefill Plan',
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
