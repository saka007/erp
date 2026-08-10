<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Hrm\Models\Branch;
use Workdo\Quotation\Models\SalesQuotation;
use Workdo\Quotation\Models\SalesQuotationItem;

class TextileSalesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_run_sales_lifecycle_and_tenant_data_is_isolated(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $companyA = $this->company();
        $companyB = $this->company();
        $branchA = Branch::create(['branch_name' => 'Sales Branch', 'creator_id' => $companyA->id, 'created_by' => $companyA->id]);
        $this->actingAs($companyA)->withSession(['active_branch_id' => $branchA->id]);

        $customer = Customer::create([
            'company_name' => 'Metro Textiles',
            'contact_person_name' => 'Metro Buyer',
            'contact_person_email' => 'buyer@metro-textiles.test',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $takhaSource = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-SALES-001',
            'party_name' => 'Own Loom Unit',
            'lot_reference' => 'TAKHA-LOT-S-1',
            'quantity' => 150,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => ['grade' => 'A', 'gsm' => 180, 'width' => 110, 'warehouse' => 'Main Warehouse'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);
        $fabricLot = TextileLot::create([
            'lot_reference' => 'TAKHA-LOT-S-1',
            'received_quantity' => 150,
            'available_quantity' => 150,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'production_stage' => TextileLot::STAGE_WEAVING,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $takhaSource->id,
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        TextileWorkflowDocument::create([
            'document_type' => 'inspection',
            'document_number' => 'INSP-SALES-001',
            'lot_reference' => 'TAKHA-LOT-S-1',
            'quantity' => 150,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => ['qc_stage' => 'final_qc', 'inspection_result' => 'pass', 'final_decision' => 'pass'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.sales.orders.store'), [
                'customer_id' => $customer->id,
                'lot_selections' => [
                    ['lot_reference' => $fabricLot->lot_reference, 'quantity' => 120],
                ],
                'rate' => 75,
                'required_delivery_date' => now()->addDays(7)->toDateString(),
                'warehouse' => 'Main Warehouse',
            ])
            ->assertSessionHasNoErrors();

        $salesOrder = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'sales_order')
            ->latest('id')
            ->first();

        $this->assertNotNull($salesOrder);
        $this->assertSame('draft', $salesOrder->status);

        // Creating the sales order must reserve the takha stock immediately,
        // preventing the same lot from being double-booked.
        $this->assertEquals(30, (float) $fabricLot->refresh()->available_quantity);
        $this->assertDatabaseHas('textile_reservations', [
            'created_by' => $companyA->id,
            'lot_reference' => $fabricLot->lot_reference,
            'reference_type' => 'sales_order',
            'reference_id' => $salesOrder->id,
            'is_active' => true,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.sales.orders.store'), [
                'customer_id' => $customer->id,
                'lot_selections' => [
                    ['lot_reference' => $fabricLot->lot_reference, 'quantity' => 60],
                ],
                'rate' => 75,
                'required_delivery_date' => now()->addDays(7)->toDateString(),
                'warehouse' => 'Main Warehouse',
            ])
            ->assertSessionHasErrors('customer_id');

        $this->actingAs($companyA)
            ->post(route('textile.sales.orders.approve'), [
                'sales_order_id' => $salesOrder->id,
            ])
            ->assertSessionHasNoErrors();

        $salesOrder->refresh();
        $this->assertSame('approved', $salesOrder->status);

        $this->actingAs($companyA)
            ->post(route('textile.sales.allocations.store'), [
                'sales_order_id' => $salesOrder->id,
                'lot_allocations' => [
                    ['lot_reference' => $fabricLot->lot_reference, 'quantity' => 119],
                ],
            ])
            ->assertSessionHasErrors('sales_order_id');
        $this->assertEquals(30, (float) $fabricLot->refresh()->available_quantity);

        $otherBranch = Branch::create(['branch_name' => 'Other Sales Branch', 'creator_id' => $companyA->id, 'created_by' => $companyA->id]);
        $otherSource = TextileWorkflowDocument::create([
            'document_type' => 'takha_entry',
            'document_number' => 'TAKHA-OTHER-BRANCH',
            'lot_reference' => 'TAKHA-OTHER-LOT',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'branch_id' => $otherBranch->id,
        ]);
        TextileLot::create([
            'lot_reference' => 'TAKHA-OTHER-LOT',
            'received_quantity' => 120,
            'available_quantity' => 120,
            'status' => 'active',
            'is_active' => true,
            'material_type' => TextileLot::TYPE_GREY_FABRIC,
            'source_document_type' => 'takha_entry',
            'source_document_id' => $otherSource->id,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.sales.allocations.store'), [
                'sales_order_id' => $salesOrder->id,
                'lot_allocations' => [
                    ['lot_reference' => 'TAKHA-OTHER-LOT', 'quantity' => 120],
                ],
            ])
            ->assertSessionHasErrors('sales_order_id');

        $this->actingAs($companyA)
            ->post(route('textile.sales.allocations.store'), [
                'sales_order_id' => $salesOrder->id,
            ])
            ->assertSessionHasNoErrors();

        $allocation = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'allocation')
            ->latest('id')
            ->first();

        $this->assertNotNull($allocation);
        $this->assertSame('draft', $allocation->status);
        $this->assertSame('TAKHA-LOT-S-1', $allocation->metadata['lot_allocations'][0]['lot_reference']);
        $this->assertEquals(30, (float) $fabricLot->refresh()->available_quantity);

        $this->actingAs($companyA)
            ->post(route('textile.sales.allocations.release'), [
                'allocation_id' => $allocation->id,
            ])
            ->assertSessionHasNoErrors();

        $allocation->refresh();
        $this->assertSame('released', $allocation->status);

        $this->actingAs($companyA)
            ->post(route('textile.sales.dispatches.store'), [
                'allocation_id' => $allocation->id,
            ])
            ->assertSessionHasNoErrors();

        $dispatch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'dispatch')
            ->latest('id')
            ->first();

        $this->assertNotNull($dispatch);

        $this->actingAs($companyA)
            ->post(route('textile.sales.dispatches.release'), [
                'dispatch_id' => $dispatch->id,
            ])
            ->assertSessionHasNoErrors();

        $dispatch->refresh();
        $this->assertSame('released', $dispatch->status);

        $this->actingAs($companyA)
            ->post(route('textile.sales.challans.store'), [
                'dispatch_id' => $dispatch->id,
            ])
            ->assertSessionHasNoErrors();

        $challan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'challan')
            ->latest('id')
            ->first();

        $this->assertNotNull($challan);

        $this->actingAs($companyA)
            ->post(route('textile.sales.challans.pod'), [
                'challan_id' => $challan->id,
            ])
            ->assertSessionHasNoErrors();

        $pod = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'pod')
            ->latest('id')
            ->first();

        $this->assertNotNull($pod);
        $this->assertSame('approved', $pod->status);
        $this->assertTrue((bool) ($pod->metadata['invoice_ready'] ?? false));

        $challan->refresh();
        $this->assertSame('closed', $challan->status);

        $this->actingAs($companyB)
            ->get(route('textile.sales.index'))
            ->assertOk()
            ->assertDontSee('TAKHA-LOT-S-1')
            ->assertDontSee('Metro Textiles');

        $this->actingAs($companyA)
            ->get(route('textile.sales.index'))
            ->assertOk()
            ->assertSee('TAKHA-LOT-S-1')
            ->assertSee('Metro Textiles');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Sales Plan',
            'modules' => ['TextileCore'],
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

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        return $company;
    }

    public function test_sales_index_renders_quotation_customer_name_without_500(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();

        $this->actingAs($company);

        // Customer auto-linked to a client user (customers.user_id -> users.id),
        // matching how QuotationController::customersForSelect() stores quotations.
        $customer = Customer::create([
            'company_name' => 'Metro Fashions Pvt Ltd',
            'contact_person_name' => 'Metro Buyer',
            'contact_person_email' => 'buyer@metro.test',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $clientUser = User::factory()->create([
            'type' => 'client',
            'created_by' => $company->id,
        ]);

        $customer->update(['user_id' => $clientUser->id]);

        // Quotation references the CLIENT USER id, not the customer id.
        $quotation = SalesQuotation::create([
            'customer_id' => $clientUser->id,
            'quotation_date' => now(),
            'due_date' => now()->addDays(7),
            'total_amount' => 5000,
            'status' => 'draft',
            'converted_to_invoice' => false,
            'quotation_type' => 'general',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        // Regression: users table has no company_name column; the page must
        // read it from the customers table (customerDetails) instead.
        $this->actingAs($company)
            ->get(route('textile.sales.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('quotations', 1)
                ->where('quotations.0.customer_name', 'Metro Fashions Pvt Ltd'));

        $this->assertDatabaseHas('sales_quotations', [
            'id' => $quotation->id,
            'customer_id' => $clientUser->id,
        ]);
    }

    public function test_quotations_can_be_created_and_updated_inline_from_sales_screen(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();
        $this->actingAs($company);

        $customer = Customer::create([
            'company_name' => 'Inline Buyer Co',
            'contact_person_name' => 'Inline Buyer',
            'contact_person_email' => 'inline@buyer.test',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
        $clientUser = User::factory()->create(['type' => 'client', 'created_by' => $company->id]);
        $customer->update(['user_id' => $clientUser->id]);

        $warehouse = Warehouse::create([
            'name' => 'Inline Warehouse',
            'address' => 'Main Rd',
            'city' => 'Cityville',
            'zip_code' => '12345',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        // Inline CREATE from /textile/sales?section=quotations
        $this->post(route('textile.sales.quotations.store'), [
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'customer_id' => $clientUser->id,
            'warehouse_id' => $warehouse->id,
            'payment_terms' => 'Net 30',
            'notes' => 'Created inline',
            'quotation_type' => 'general',
            'items' => [
                [
                    'product_id' => 1,
                    'product_type' => 'product',
                    'quantity' => 10,
                    'unit_price' => 100,
                    'discount_percentage' => 10,
                    'tax_percentage' => 5,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('sales_quotations', [
            'customer_id' => $clientUser->id,
            'warehouse_id' => $warehouse->id,
            'quotation_type' => 'general',
            'created_by' => $company->id,
        ]);

        $quotation = SalesQuotation::where('customer_id', $clientUser->id)->firstOrFail();
        // subtotal = 10 * 100 = 1000; discount = 1000 * 10% = 100;
        // tax = (1000 - 100) * 5% = 45; total = 1000 + 45 - 100 = 945
        $this->assertEquals(1000.0, (float) $quotation->subtotal);
        $this->assertEquals(45.0, (float) $quotation->tax_amount);
        $this->assertEquals(100.0, (float) $quotation->discount_amount);
        $this->assertEquals(945.0, (float) $quotation->total_amount);
        $this->assertSame('draft', $quotation->status);

        $item = SalesQuotationItem::where('quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame(10, (int) $item->quantity);
        $this->assertSame(100.0, (float) $item->unit_price);

        // Inline UPDATE from the same screen (still draft)
        $this->post(route('textile.sales.quotations.update', $quotation->id), [
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'customer_id' => $clientUser->id,
            'warehouse_id' => $warehouse->id,
            'payment_terms' => 'Net 15',
            'notes' => 'Updated inline',
            'quotation_type' => 'general',
            'items' => [
                [
                    'product_id' => 1,
                    'product_type' => 'product',
                    'quantity' => 5,
                    'unit_price' => 200,
                    'discount_percentage' => 0,
                    'tax_percentage' => 0,
                ],
            ],
        ])->assertRedirect();

        $quotation->refresh();
        $this->assertEquals(1000.0, (float) $quotation->total_amount);
        $this->assertSame('Net 15', $quotation->payment_terms);
        $this->assertSame('Updated inline', $quotation->notes);

        // Items are replaced on update (old row deleted, one new row)
        $this->assertSame(1, SalesQuotationItem::where('quotation_id', $quotation->id)->count());

        // Non-draft quotations cannot be updated inline
        $quotation->update(['status' => 'sent']);
        $this->post(route('textile.sales.quotations.update', $quotation->id), [
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'customer_id' => $clientUser->id,
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'product_id' => 1,
                    'product_type' => 'product',
                    'quantity' => 1,
                    'unit_price' => 1,
                ],
            ],
        ])->assertRedirect();
        $quotation->refresh();
        $this->assertEquals(1000.0, (float) $quotation->total_amount);
    }

    public function test_challans_can_be_approved_and_edited_inline_from_records(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();
        $this->actingAs($company);

        // Released dispatch so a challan can be created from it.
        $dispatch = TextileWorkflowDocument::create([
            'document_type' => 'dispatch',
            'document_number' => 'DISPATCH-CHALLAN-EDIT',
            'party_name' => 'Metro Fashions Pvt Ltd',
            'lot_reference' => 'TAK-LOT-0001',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'released',
            'created_by' => $company->id,
            'creator_id' => $company->id,
        ]);

        $this->post(route('textile.sales.challans.store'), [
            'dispatch_id' => $dispatch->id,
        ])->assertSessionHasNoErrors();

        $challan = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'challan')
            ->latest('id')
            ->first();

        $this->assertNotNull($challan);
        $this->assertSame('draft', $challan->status);
        $this->assertSame('TAK-LOT-0001', $challan->lot_reference);

        // Draft challans can be edited inline (party/lot/qty/unit).
        $this->post(route('textile.sales.challans.update'), [
            'challan_id' => $challan->id,
            'party_name' => 'Updated Party Ltd',
            'lot_reference' => 'TAK-LOT-0001',
            'quantity' => 115,
            'unit' => 'mtr',
        ])->assertSessionHasNoErrors();

        $challan->refresh();
        $this->assertSame('Updated Party Ltd', $challan->party_name);
        $this->assertEquals(115, (float) $challan->quantity);
        $this->assertSame('draft', $challan->status);

        // Draft challans can be approved from the records row.
        $this->post(route('textile.sales.challans.approve'), [
            'challan_id' => $challan->id,
        ])->assertSessionHasNoErrors();

        $challan->refresh();
        $this->assertSame('approved', $challan->status);

        // Approved challans cannot be edited anymore.
        $this->post(route('textile.sales.challans.update'), [
            'challan_id' => $challan->id,
            'party_name' => 'Should Not Apply',
            'lot_reference' => 'TAK-LOT-0001',
            'quantity' => 100,
            'unit' => 'mtr',
        ])->assertSessionHasErrors('challan_id');

        $challan->refresh();
        $this->assertSame('Updated Party Ltd', $challan->party_name);
        $this->assertEquals(115, (float) $challan->quantity);

        // POD can still be marked on the approved challan.
        $this->post(route('textile.sales.challans.pod'), [
            'challan_id' => $challan->id,
        ])->assertSessionHasNoErrors();

        $challan->refresh();
        $this->assertSame('closed', $challan->status);
    }
}
