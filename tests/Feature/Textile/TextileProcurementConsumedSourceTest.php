<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Hrm\Models\Branch;

class TextileProcurementConsumedSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfq_cannot_be_created_twice_from_same_requisition(): void
    {
        $service = $this->procurementService();

        $requisition = $this->approvedRequisition('Consumed RFQ Lot');
        $service->createRfq((int) $requisition->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RFQ already created for this requisition.');

        $service->createRfq((int) $requisition->id);
    }

    public function test_purchase_order_cannot_be_created_twice_from_same_requisition(): void
    {
        $service = $this->procurementService();

        $requisition = $this->approvedRequisition('Consumed PO Lot');
        $service->createPurchaseOrder((int) $requisition->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Purchase order already created for this requisition.');

        $service->createPurchaseOrder((int) $requisition->id);
    }

    public function test_purchase_order_cannot_be_created_twice_from_same_rfq(): void
    {
        $service = $this->procurementService();

        $requisition = $this->approvedRequisition('Consumed PO-from-RFQ Lot');
        $rfq = $service->createRfq((int) $requisition->id);
        $service->sendRfq((int) $rfq->id);
        $service->createPurchaseOrder(null, (int) $rfq->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Purchase order already created for this RFQ.');

        $service->createPurchaseOrder(null, (int) $rfq->id);
    }

    public function test_grn_cannot_be_created_twice_from_same_purchase_order(): void
    {
        $service = $this->procurementService();

        $purchaseOrder = $this->approvedPurchaseOrder('Consumed GRN Lot');
        $service->createGrn((int) $purchaseOrder->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GRN already created for this purchase order.');

        $service->createGrn((int) $purchaseOrder->id);
    }

    public function test_incoming_qc_cannot_be_created_twice_from_same_grn(): void
    {
        $service = $this->procurementService();

        $grn = $this->releasedGrn('Consumed QC Lot');
        $service->createIncomingQc((int) $grn->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incoming QC already created for this GRN.');

        $service->createIncomingQc((int) $grn->id);
    }

    public function test_supplier_claim_cannot_be_created_twice_from_same_grn(): void
    {
        $service = $this->procurementService();

        $grn = $this->releasedGrn('Consumed Claim Lot');
        $service->createSupplierClaim((int) $grn->id, [
            'claim_type' => 'shortage',
            'claim_amount' => 500,
            'resolution_type' => 'credit_note',
            'claim_note' => 'Short delivery of yarn.',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Supplier claim already created for this GRN.');

        $service->createSupplierClaim((int) $grn->id, [
            'claim_type' => 'shortage',
            'claim_amount' => 500,
            'resolution_type' => 'credit_note',
            'claim_note' => 'Short delivery of yarn.',
        ]);
    }

    public function test_distinct_sources_can_each_create_their_own_downstream_documents(): void
    {
        $service = $this->procurementService();

        // Two requisitions must not block each other.
        $first = $this->approvedRequisition('Independent Lot A');
        $second = $this->approvedRequisition('Independent Lot B');

        $service->createRfq((int) $first->id);
        $service->createRfq((int) $second->id);

        $this->assertSame(2, TextileWorkflowDocument::query()
            ->where('document_type', 'rfq')
            ->count());
    }

    private function procurementService(): TextileProcurementService
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'Consumed Source Branch', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        return app(TextileProcurementService::class);
    }

    private function approvedRequisition(string $lotReference): TextileWorkflowDocument
    {
        $service = app(TextileProcurementService::class);
        $requisition = $service->createRequisition([
            'party_name' => 'Apex Yarn Mills',
            'lot_reference' => $lotReference,
            'quantity' => 100,
            'unit' => 'kg',
        ]);
        $service->approveRequisition((int) $requisition->id);

        return $requisition->refresh();
    }

    private function approvedPurchaseOrder(string $lotReference): TextileWorkflowDocument
    {
        $service = app(TextileProcurementService::class);
        $requisition = $this->approvedRequisition($lotReference);
        $purchaseOrder = $service->createPurchaseOrder((int) $requisition->id);
        $service->approvePurchaseOrder((int) $purchaseOrder->id);

        return $purchaseOrder->refresh();
    }

    private function releasedGrn(string $lotReference): TextileWorkflowDocument
    {
        $service = app(TextileProcurementService::class);
        $purchaseOrder = $this->approvedPurchaseOrder($lotReference);
        $grn = $service->createGrn((int) $purchaseOrder->id);
        $service->releaseGrn((int) $grn->id);

        return $grn->refresh();
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
            'name' => 'Textile Procurement Guard Plan',
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
