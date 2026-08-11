<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;
use Workdo\Hrm\Models\Branch;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;
use Workdo\ProductService\Models\WarehouseStock;

class TextilePurchaseInvoiceItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_grn_generated_purchase_invoice_has_line_items_from_metadata(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'PI Branch', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $vendor = $this->makeVendor($company);
        $vendorUser = $this->linkVendorUser($vendor);
        $product = $this->makeProduct($company, 'YRN-PI-001');

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'quantity' => 100,
                'unit' => 'kg',
                'product_service_item_id' => $product->id,
                'rate' => 170,
            ])
            ->assertSessionHasNoErrors();

        $requisition = $this->latestDocument($company->id, 'purchase_requisition');

        $this->actingAs($company)->post(route('textile.procurement.requisitions.approve'), ['requisition_id' => $requisition->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.store'), [
            'source_type' => 'requisition',
            'source_id' => $requisition->id,
        ])->assertSessionHasNoErrors();

        $purchaseOrder = $this->latestDocument($company->id, 'purchase_order');

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.approve'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.grns.store'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $grn = $this->latestDocument($company->id, 'grn');

        $this->actingAs($company)->post(route('textile.procurement.grns.release'), ['grn_id' => $grn->id])->assertSessionHasNoErrors();

        $grn->refresh();
        $this->assertSame('released', $grn->status);

        $invoice = PurchaseInvoice::query()
            ->where('created_by', $company->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(sprintf('TX-GRN-%s', str_pad((string) $grn->id, 6, '0', STR_PAD_LEFT)), $invoice->invoice_number);
        $this->assertSame($vendorUser->id, (int) $invoice->vendor_id);
        $this->assertEquals(17000.00, (float) $invoice->total_amount);

        // The invoice must carry a real line item (not a bare total-only record).
        $items = $invoice->items()->get();
        $this->assertCount(1, $items);
        $this->assertSame((int) $product->id, (int) $items->first()->product_id);
        $this->assertEquals(100, (int) $items->first()->quantity);
        $this->assertEquals(170.00, (float) $items->first()->unit_price);
        $this->assertEquals(17000.00, (float) $items->first()->total_amount);
    }

    public function test_grn_invoice_without_rate_keeps_legacy_single_total_behavior(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'PI Branch 2', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $vendor = $this->makeVendor($company);
        $this->linkVendorUser($vendor);

        // Legacy requisition without product/rate (metadata has no rate).
        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'quantity' => 50,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();

        $requisition = $this->latestDocument($company->id, 'purchase_requisition');

        $this->actingAs($company)->post(route('textile.procurement.requisitions.approve'), ['requisition_id' => $requisition->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.store'), [
            'source_type' => 'requisition',
            'source_id' => $requisition->id,
        ])->assertSessionHasNoErrors();

        $purchaseOrder = $this->latestDocument($company->id, 'purchase_order');

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.approve'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.grns.store'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $grn = $this->latestDocument($company->id, 'grn');

        $this->actingAs($company)->post(route('textile.procurement.grns.release'), ['grn_id' => $grn->id])->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()
            ->where('created_by', $company->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);
        // No line items expected in the legacy path (no rate available).
        $this->assertSame(0, $invoice->items()->count());
    }

    public function test_grn_generated_invoice_can_be_posted_without_warehouse_id_crash(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'PI Branch WH', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Plot 12, GIDC',
            'city' => 'Ahmedabad',
            'zip_code' => '382445',
            'branch_id' => $branch->id,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $vendor = $this->makeVendor($company);
        $this->linkVendorUser($vendor);
        $product = $this->makeProduct($company, 'YRN-PI-WH-001');

        // Requisition now carries warehouse_id so the metadata chain keeps it.
        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'quantity' => 100,
                'unit' => 'kg',
                'product_service_item_id' => $product->id,
                'rate' => 170,
                'warehouse_id' => $warehouse->id,
            ])
            ->assertSessionHasNoErrors();

        $requisition = $this->latestDocument($company->id, 'purchase_requisition');
        $this->assertSame((int) $warehouse->id, (int) ($requisition->metadata['warehouse_id'] ?? null));

        $this->actingAs($company)->post(route('textile.procurement.requisitions.approve'), ['requisition_id' => $requisition->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.store'), [
            'source_type' => 'requisition',
            'source_id' => $requisition->id,
        ])->assertSessionHasNoErrors();

        $purchaseOrder = $this->latestDocument($company->id, 'purchase_order');

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.approve'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.grns.store'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $grn = $this->latestDocument($company->id, 'grn');

        $this->actingAs($company)->post(route('textile.procurement.grns.release'), ['grn_id' => $grn->id])->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()
            ->where('created_by', $company->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame((int) $warehouse->id, (int) $invoice->warehouse_id);

        // Posting must not crash with a NULL warehouse_id constraint violation.
        $this->actingAs($company)
            ->post(route('purchase-invoices.post', $invoice->id))
            ->assertSessionHasNoErrors();

        $this->assertSame('posted', $invoice->fresh()->status);

        $stock = WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals(100, (int) $stock->quantity);
    }

    public function test_listener_falls_back_to_branch_warehouse_when_invoice_has_no_warehouse(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'PI Branch WH2', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $warehouse = Warehouse::create([
            'name' => 'Branch Warehouse',
            'address' => 'Shed 7, MIDC',
            'city' => 'Pune',
            'zip_code' => '411014',
            'branch_id' => $branch->id,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $vendor = $this->makeVendor($company);
        $this->linkVendorUser($vendor);
        $product = $this->makeProduct($company, 'YRN-PI-WH-002');

        // Legacy requisition WITHOUT warehouse_id → invoice carries NULL.
        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'quantity' => 50,
                'unit' => 'kg',
                'product_service_item_id' => $product->id,
                'rate' => 170,
            ])
            ->assertSessionHasNoErrors();

        $requisition = $this->latestDocument($company->id, 'purchase_requisition');

        $this->actingAs($company)->post(route('textile.procurement.requisitions.approve'), ['requisition_id' => $requisition->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.store'), [
            'source_type' => 'requisition',
            'source_id' => $requisition->id,
        ])->assertSessionHasNoErrors();

        $purchaseOrder = $this->latestDocument($company->id, 'purchase_order');

        $this->actingAs($company)->post(route('textile.procurement.purchase-orders.approve'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $this->actingAs($company)->post(route('textile.procurement.grns.store'), ['purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

        $grn = $this->latestDocument($company->id, 'grn');

        $this->actingAs($company)->post(route('textile.procurement.grns.release'), ['grn_id' => $grn->id])->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()
            ->where('created_by', $company->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);

        // Legacy requisition had no warehouse, so the invoice generation
        // resolved the tenant's active-branch warehouse automatically.
        $this->assertSame((int) $warehouse->id, (int) $invoice->warehouse_id);

        // Posting must not crash and must stock the resolved warehouse.
        $this->actingAs($company)
            ->post(route('purchase-invoices.post', $invoice->id))
            ->assertSessionHasNoErrors();

        $this->assertSame('posted', $invoice->fresh()->status);

        $stock = WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals(50, (int) $stock->quantity);
    }

    private function latestDocument(int $tenantId, string $type): TextileWorkflowDocument
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', $tenantId)
            ->where('document_type', $type)
            ->latest('id')
            ->firstOrFail();
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

    private function linkVendorUser(Vendor $vendor): User
    {
        $user = User::create([
            'name' => $vendor->company_name,
            'email' => $vendor->contact_person_email ?: 'vendor' . $vendor->id . '@example.test',
            'password' => bcrypt('secret'),
            'type' => 'vendor',
            'creator_id' => $vendor->created_by,
            'created_by' => $vendor->created_by,
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);

        $vendor->update(['user_id' => $user->id]);

        return $user;
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
            'name' => 'Textile Purchase Invoice Items Plan',
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

        // Grant the invoice post permission so the GRN-generated invoice can be
        // posted in the warehouse regression tests.
        Permission::firstOrCreate(
            ['name' => 'post-purchase-invoices', 'guard_name' => 'web'],
            ['module' => 'purchase-invoices', 'label' => 'Post Purchase Invoices', 'add_on' => 'general']
        );
        $company->givePermissionTo('post-purchase-invoices');

        return $company;
    }
}
