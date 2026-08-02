<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

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

        $this->actingAs($companyA)
            ->post(route('textile.sales.orders.store'), [
                'source_reference_type' => 'sales_quotation',
                'source_reference_id' => 7001,
                'source_action' => 'convert',
                'party_name' => 'Metro Textiles',
                'lot_reference' => 'LOT-S-1',
                'quantity' => 120,
                'unit' => 'mtr',
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
            ])
            ->assertSessionHasNoErrors();

        $allocation = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'allocation')
            ->latest('id')
            ->first();

        $this->assertNotNull($allocation);
        $this->assertSame('draft', $allocation->status);

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
            ->assertDontSee('LOT-S-1')
            ->assertDontSee('Metro Textiles');

        $this->actingAs($companyA)
            ->get(route('textile.sales.index'))
            ->assertOk()
            ->assertSee('LOT-S-1')
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
