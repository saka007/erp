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
use Workdo\Hrm\Models\Branch;

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
        $this->assertEquals(150, (float) $fabricLot->refresh()->available_quantity);

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
}
