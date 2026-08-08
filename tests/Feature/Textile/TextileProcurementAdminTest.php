<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Hrm\Models\Branch;

class TextileProcurementAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_run_procurement_lifecycle_and_tenant_data_is_isolated(): void
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
            'module' => 'TextileInventory',
            'name' => 'Textile Inventory',
            'package_name' => 'textile-inventory',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $companyA = $this->company();
        $companyB = $this->company();

        $this->actingAs($companyA)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => 'Alpha Fibers',
                'lot_reference' => 'LOT-A-1',
                'quantity' => 100,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();

        $requisitionA = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'purchase_requisition')
            ->latest('id')
            ->first();

        $this->assertNotNull($requisitionA);
        $this->assertSame('draft', $requisitionA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.requisitions.approve'), [
                'requisition_id' => $requisitionA->id,
            ])
            ->assertSessionHasNoErrors();

        $requisitionA->refresh();
        $this->assertSame('approved', $requisitionA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.rfqs.store'), [
                'requisition_id' => $requisitionA->id,
            ])
            ->assertSessionHasNoErrors();

        $rfqA = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'rfq')
            ->latest('id')
            ->first();

        $this->assertNotNull($rfqA);
        $this->assertSame('draft', $rfqA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.rfqs.send'), [
                'rfq_id' => $rfqA->id,
            ])
            ->assertSessionHasNoErrors();

        $rfqA->refresh();
        $this->assertSame('approved', $rfqA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.rfqs.close'), [
                'rfq_id' => $rfqA->id,
            ])
            ->assertSessionHasNoErrors();

        $rfqA->refresh();
        $this->assertSame('released', $rfqA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.purchase-orders.store'), [
                'source_type' => 'rfq',
                'source_id' => $rfqA->id,
            ])
            ->assertSessionHasNoErrors();

        $purchaseOrderA = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'purchase_order')
            ->latest('id')
            ->first();

        $this->assertNotNull($purchaseOrderA);
        $this->assertSame('draft', $purchaseOrderA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.purchase-orders.approve'), [
                'purchase_order_id' => $purchaseOrderA->id,
            ])
            ->assertSessionHasNoErrors();

        $purchaseOrderA->refresh();
        $this->assertSame('approved', $purchaseOrderA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.grns.store'), [
                'purchase_order_id' => $purchaseOrderA->id,
            ])
            ->assertSessionHasNoErrors();

        $grnA = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'grn')
            ->latest('id')
            ->first();

        $this->assertNotNull($grnA);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.grns.release'), [
                'grn_id' => $grnA->id,
            ])
            ->assertSessionHasNoErrors();

        $grnA->refresh();
        $this->assertSame('released', $grnA->status);

        $invoiceA = PurchaseInvoice::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($invoiceA);
        $this->assertSame('draft', $invoiceA->status);
        $this->assertSame($companyA->id, $invoiceA->vendor_id);
        $this->assertSame($invoiceA->id, (int) ($grnA->metadata['purchase_invoice_id'] ?? 0));

        $this->actingAs($companyA)
            ->post(route('textile.procurement.invoices.from-grn'), [
                'grn_id' => $grnA->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PurchaseInvoice::query()->where('created_by', $companyA->id)->count());

        $this->actingAs($companyA)
            ->post(route('textile.procurement.supplier-claims.store'), [
                'grn_id' => $grnA->id,
                'claim_type' => 'quality',
                'claim_amount' => 250,
                'resolution_type' => 'credit_note',
                'claim_note' => 'Shade mismatch in received lot',
            ])
            ->assertSessionHasNoErrors();

        $claimA = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'supplier_claim')
            ->latest('id')
            ->first();

        $this->assertNotNull($claimA);
        $this->assertSame('draft', $claimA->status);
        $this->assertSame('quality', $claimA->metadata['claim_type']);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.supplier-claims.approve'), [
                'supplier_claim_id' => $claimA->id,
            ])
            ->assertSessionHasNoErrors();

        $claimA->refresh();
        $this->assertSame('approved', $claimA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.supplier-claims.settle'), [
                'supplier_claim_id' => $claimA->id,
            ])
            ->assertSessionHasNoErrors();

        $claimA->refresh();
        $this->assertSame('released', $claimA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.incoming-qc.store'), [
                'grn_id' => $grnA->id,
            ])
            ->assertSessionHasNoErrors();

        $incomingQcA = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'incoming_qc')
            ->latest('id')
            ->first();

        $this->assertNotNull($incomingQcA);
        $this->assertSame('draft', $incomingQcA->status);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.incoming-qc.finalize'), [
                'incoming_qc_id' => $incomingQcA->id,
                'decision' => 'pass',
            ])
            ->assertSessionHasNoErrors();

        $incomingQcA->refresh();
        $this->assertSame('approved', $incomingQcA->status);

        $this->actingAs($companyB)
            ->get(route('textile.procurement.index'))
            ->assertOk()
            ->assertDontSee('LOT-A-1')
            ->assertDontSee('Alpha Fibers');

        $this->actingAs($companyA)
            ->get(route('textile.procurement.index'))
            ->assertOk()
            ->assertSee('LOT-A-1')
            ->assertSee('Alpha Fibers');
    }

    public function test_requisition_create_auto_defaults_to_first_branch_when_none_selected(): void
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

        // Tenant has branches but no active branch is selected in session.
        $branchA = Branch::create([
            'branch_name' => 'Main Office',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => 'Alpha Fibers',
                'lot_reference' => 'LOT-AUTOBRANCH',
                'quantity' => 100,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();

        $requisition = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'purchase_requisition')
            ->latest('id')
            ->first();

        $this->assertNotNull($requisition);
        $this->assertSame('draft', $requisition->status);
        $this->assertSame((int) $branchA->id, (int) $requisition->branch_id);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Procurement Plan',
            'modules' => ['TextileCore', 'TextileInventory'],
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

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileInventory',
        ]);

        return $company;
    }
}
