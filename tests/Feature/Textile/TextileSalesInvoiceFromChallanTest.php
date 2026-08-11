<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileSalesService;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerPriceList;
use Workdo\Hrm\Models\Branch;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;

class TextileSalesInvoiceFromChallanTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_invoice_generated_from_approved_challan_with_line_items(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Invoice Branch', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $product = $this->makeProduct($company, 'YRN-INV-001');
        $customer = $this->makeCustomer($company);
        $this->linkCustomerUser($customer);

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

        $challan = $this->buildChallanChain($company, $branch, $product, $customer, 'TAKHA-INV-LOT-1', 40);

        $result = app(TextileSalesService::class)->createSalesInvoiceFromChallan((int) $challan->id);

        $this->assertTrue((bool) ($result->sales_invoice_created_now ?? false));

        $invoiceId = (int) $result->metadata['sales_invoice_id'];
        $this->assertGreaterThan(0, $invoiceId);

        $invoice = SalesInvoice::query()->findOrFail($invoiceId);
        $this->assertSame('TX-CHL-' . str_pad((string) $challan->id, 6, '0', STR_PAD_LEFT), $invoice->invoice_number);
        $this->assertSame((int) $customer->user_id, (int) $invoice->customer_id);
        $this->assertEquals(221.40 * 40, (float) $invoice->subtotal);
        $this->assertEquals(221.40 * 40, (float) $invoice->total_amount);
        $this->assertEquals(221.40 * 40, (float) $invoice->balance_amount);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame($company->id, (int) $invoice->created_by);

        // Line items must be present (not a bare total-only invoice).
        $items = $invoice->items()->get();
        $this->assertCount(1, $items);
        $this->assertSame((int) $product->id, (int) $items->first()->product_id);
        $this->assertEquals(40, (int) $items->first()->quantity);
        $this->assertEquals(221.40, (float) $items->first()->unit_price);

        // Challan metadata must record the generated invoice.
        $challan->refresh();
        $this->assertSame($invoice->id, (int) $challan->metadata['sales_invoice_id']);
        $this->assertSame($invoice->invoice_number, $challan->metadata['sales_invoice_number']);
    }

    public function test_generating_invoice_twice_is_idempotent(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Invoice Branch 2', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $product = $this->makeProduct($company, 'YRN-INV-002');
        $customer = $this->makeCustomer($company);
        $this->linkCustomerUser($customer);

        $challan = $this->buildChallanChain($company, $branch, $product, $customer, 'TAKHA-INV-LOT-2', 40, 221.40);

        $service = app(TextileSalesService::class);
        $first = $service->createSalesInvoiceFromChallan((int) $challan->id);
        $firstInvoiceId = (int) $first->metadata['sales_invoice_id'];

        $second = $service->createSalesInvoiceFromChallan((int) $challan->id);

        $this->assertFalse((bool) ($second->sales_invoice_created_now ?? false));
        $this->assertSame($firstInvoiceId, (int) $second->metadata['sales_invoice_id']);
        $this->assertSame(1, SalesInvoice::query()->count());
        $this->assertSame(1, SalesInvoiceItem::query()->count());
    }

    public function test_draft_challan_cannot_generate_invoice(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Invoice Branch 3', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $product = $this->makeProduct($company, 'YRN-INV-003');
        $customer = $this->makeCustomer($company);
        $this->linkCustomerUser($customer);

        $challan = $this->buildChallanChain($company, $branch, $product, $customer, 'TAKHA-INV-LOT-3', 40, 221.40);

        // Downgrade the challan to draft to simulate an un-approved challan.
        $challan->update(['status' => 'draft']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Challan must be approved or released before generating the sales invoice.');

        app(TextileSalesService::class)->createSalesInvoiceFromChallan((int) $challan->id);
    }

    public function test_rate_falls_back_to_customer_price_list_when_metadata_rate_missing(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Invoice Branch 4', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $product = $this->makeProduct($company, 'YRN-INV-004');
        $customer = $this->makeCustomer($company);
        $this->linkCustomerUser($customer);

        CustomerPriceList::create([
            'customer_id' => $customer->id,
            'product_service_item_id' => $product->id,
            'unit_price' => 199.50,
            'currency_code' => 'INR',
            'min_quantity' => 1,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $challan = $this->buildChallanChain($company, $branch, $product, $customer, 'TAKHA-INV-LOT-4', 40);

        // Remove the rate from the challan metadata to force fallback.
        $metadata = is_array($challan->metadata) ? $challan->metadata : [];
        unset($metadata['rate'], $metadata['rate_source']);
        $challan->update(['metadata' => $metadata]);

        $result = app(TextileSalesService::class)->createSalesInvoiceFromChallan((int) $challan->id);

        $invoice = SalesInvoice::query()->findOrFail((int) $result->metadata['sales_invoice_id']);
        $this->assertEquals(199.50 * 40, (float) $invoice->subtotal);
        $this->assertEquals(199.50 * 40, (float) $invoice->total_amount);
        $this->assertSame('customer_price_list', $result->metadata['sales_invoice_rate_source']);
    }

    public function test_controller_generates_invoice_and_returns_success(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Invoice Branch 5', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $product = $this->makeProduct($company, 'YRN-INV-005');
        $customer = $this->makeCustomer($company);
        $this->linkCustomerUser($customer);

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

        $challan = $this->buildChallanChain($company, $branch, $product, $customer, 'TAKHA-INV-LOT-5', 40, 221.40);

        $this->actingAs($company)
            ->post(route('textile.sales.invoices.generate'), ['challan_id' => $challan->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', __('Sales invoice generated from challan successfully.'));

        $this->assertSame(1, SalesInvoice::query()->count());
    }

    /**
     * Builds the full SO -> allocation -> dispatch -> challan chain and returns
     * the challan document (approved).
     */
    private function buildChallanChain(User $company, Branch $branch, ProductServiceItem $product, Customer $customer, string $lotReference, float $quantity, ?float $rate = null): TextileWorkflowDocument
    {
        $takhaSource = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-' . $lotReference,
            'party_name' => 'Own Loom Unit',
            'lot_reference' => $lotReference,
            'quantity' => $quantity + 60,
            'unit' => 'kg',
            'status' => 'approved',
            'metadata' => ['grade' => 'A'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'branch_id' => $branch->id,
        ]);

        TextileLot::create([
            'lot_reference' => $lotReference,
            'received_quantity' => $quantity + 60,
            'available_quantity' => $quantity + 60,
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
            'document_number' => 'INSP-' . $lotReference,
            'lot_reference' => $lotReference,
            'quantity' => $quantity + 60,
            'unit' => 'kg',
            'status' => 'approved',
            'metadata' => ['final_decision' => 'pass'],
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'branch_id' => $branch->id,
        ]);

        $service = app(TextileSalesService::class);

        // Mirror what TextileSalesController::storeSalesOrder() puts into the
        // sales order metadata (item info + optional rate).
        $orderMetadata = [
            'item_name' => $product->name,
            'item_sku' => $product->sku,
            'product_service_item_id' => (int) $product->id,
        ];

        if ($rate !== null) {
            $orderMetadata['rate'] = $rate;
            $orderMetadata['rate_source'] = 'manual';
        }

        $salesOrder = $service->createSalesOrder([
            'customer_id' => (int) $customer->id,
            'lot_selections' => [
                ['lot_reference' => $lotReference, 'quantity' => $quantity],
            ],
            'product_service_item_id' => (int) $product->id,
            'required_delivery_date' => now()->addDays(7)->toDateString(),
            'metadata' => $orderMetadata,
        ]);

        $salesOrder = $service->approveSalesOrder((int) $salesOrder->id);
        $allocation = $service->createAllocation((int) $salesOrder->id, [
            'lot_allocations' => [
                ['lot_reference' => $lotReference, 'quantity' => $quantity],
            ],
        ]);
        $allocation = $service->releaseAllocation((int) $allocation->id);
        $dispatch = $service->createDispatch((int) $allocation->id);
        $dispatch = $service->releaseDispatch((int) $dispatch->id);
        $challan = $service->createChallan((int) $dispatch->id);

        return $service->approveChallan((int) $challan->id);
    }

    private function linkCustomerUser(Customer $customer): void
    {
        $user = User::create([
            'name' => $customer->company_name,
            'email' => $customer->contact_person_email ?: 'customer' . $customer->id . '@example.test',
            'password' => bcrypt('secret'),
            'type' => 'client',
            'creator_id' => $customer->created_by,
            'created_by' => $customer->created_by,
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);

        $customer->update(['user_id' => $user->id]);
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
            'name' => 'Textile Invoice Plan',
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
