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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;
use Workdo\Hrm\Models\Branch;
use Workdo\ProductService\Models\ProductServiceItem;

class TextileJobWorkInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_work_invoice_generated_from_approved_yarn_dispatch_plan(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'JW Branch 1', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $vendor = $this->makeVendor($company, 'Vardhaman Sizing Mills');
        $user = $this->linkVendorUser($vendor);

        $plan = $this->buildDispatchPlan($company, $branch, 'Vardhaman Sizing Mills', 'yarn_dispatch', 600, 'approved');

        $result = app(TextileSalesService::class)->createJobWorkInvoiceFromDispatchPlan((int) $plan->id, 15.00);

        $this->assertTrue((bool) ($result->job_work_invoice_created_now ?? false));

        $invoiceId = (int) $result->metadata['job_work_invoice_id'];
        $this->assertGreaterThan(0, $invoiceId);

        $invoice = SalesInvoice::query()->findOrFail($invoiceId);
        $this->assertSame('TX-JW-' . str_pad((string) $plan->id, 6, '0', STR_PAD_LEFT), $invoice->invoice_number);
        $this->assertSame('service', $invoice->type);
        $this->assertSame((int) $user->id, (int) $invoice->customer_id);
        $this->assertEquals(15.00 * 600, (float) $invoice->subtotal);
        $this->assertEquals(15.00 * 600, (float) $invoice->total_amount);
        $this->assertEquals(15.00 * 600, (float) $invoice->balance_amount);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame($company->id, (int) $invoice->created_by);

        // Line items must be present (not a bare total-only invoice).
        $items = $invoice->items()->get();
        $this->assertCount(1, $items);
        $this->assertEquals(600, (int) $items->first()->quantity);
        $this->assertEquals(15.00, (float) $items->first()->unit_price);

        // The service item must be a service-type product.
        $serviceItem = ProductServiceItem::query()->findOrFail((int) $items->first()->product_id);
        $this->assertSame('service', $serviceItem->type);
        $this->assertSame('Job Work - Sizing', $serviceItem->name);

        // Dispatch plan metadata must record the generated invoice.
        $plan->refresh();
        $this->assertSame($invoice->id, (int) $plan->metadata['job_work_invoice_id']);
        $this->assertSame($invoice->invoice_number, $plan->metadata['job_work_invoice_number']);
    }

    public function test_generating_invoice_twice_is_idempotent(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'JW Branch 2', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $this->makeVendor($company, 'Gokul Yarn Sizing Co.');
        $plan = $this->buildDispatchPlan($company, $branch, 'Gokul Yarn Sizing Co.', 'yarn_dispatch', 700, 'approved');

        $service = app(TextileSalesService::class);
        $first = $service->createJobWorkInvoiceFromDispatchPlan((int) $plan->id, 12.50);
        $firstInvoiceId = (int) $first->metadata['job_work_invoice_id'];

        $second = $service->createJobWorkInvoiceFromDispatchPlan((int) $plan->id, 99.00);

        $this->assertFalse((bool) ($second->job_work_invoice_created_now ?? false));
        $this->assertSame($firstInvoiceId, (int) $second->metadata['job_work_invoice_id']);
        $this->assertSame(1, SalesInvoice::query()->count());
        $this->assertSame(1, SalesInvoiceItem::query()->count());
    }

    public function test_draft_dispatch_plan_cannot_generate_invoice(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'JW Branch 3', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $this->makeVendor($company, 'Draft Sizing Co.');
        $plan = $this->buildDispatchPlan($company, $branch, 'Draft Sizing Co.', 'yarn_dispatch', 500, 'draft');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Dispatch plan must be approved before generating the job-work invoice.');

        app(TextileSalesService::class)->createJobWorkInvoiceFromDispatchPlan((int) $plan->id);
    }

    public function test_challan_source_dispatch_plan_cannot_generate_job_work_invoice(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'JW Branch 4', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $plan = $this->buildDispatchPlan($company, $branch, 'Metro Fashions', 'challan', 120, 'approved');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Job-work invoices can only be generated for yarn dispatch or job-work outward dispatches.');

        app(TextileSalesService::class)->createJobWorkInvoiceFromDispatchPlan((int) $plan->id);
    }

    public function test_job_work_outward_plan_uses_processing_service_item(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'JW Branch 5', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $this->makeVendor($company, 'Silver Dyeing Works');
        $plan = $this->buildDispatchPlan($company, $branch, 'Silver Dyeing Works', 'job_work_outward', 300, 'released');

        $result = app(TextileSalesService::class)->createJobWorkInvoiceFromDispatchPlan((int) $plan->id, 20.00);

        $invoice = SalesInvoice::query()->findOrFail((int) $result->metadata['job_work_invoice_id']);
        $item = $invoice->items()->first();
        $serviceItem = ProductServiceItem::query()->findOrFail((int) $item->product_id);
        $this->assertSame('Job Work - Processing', $serviceItem->name);
        $this->assertSame('service', $invoice->type);
    }

    public function test_controller_generates_invoice_and_returns_success(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'JW Branch 6', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $this->makeVendor($company, 'Controller Sizing Co.');
        $plan = $this->buildDispatchPlan($company, $branch, 'Controller Sizing Co.', 'yarn_dispatch', 400, 'approved');

        $this->actingAs($company)
            ->post(route('textile.dispatch.job-work-invoices.generate'), ['dispatch_plan_id' => $plan->id, 'rate' => '18.50'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', __('Job-work invoice generated successfully.'));

        $this->assertSame(1, SalesInvoice::query()->count());

        $invoice = SalesInvoice::query()->first();
        $this->assertSame('service', $invoice->type);
        $this->assertEquals(18.50 * 400, (float) $invoice->subtotal);
    }

    public function test_job_work_invoice_is_tenant_scoped(): void
    {
        $company = $this->enableTextileModule();
        $branch = Branch::create(['branch_name' => 'JW Branch 7', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->actingAs($company)->withSession(['active_branch_id' => $branch->id]);

        $plan = $this->buildDispatchPlan($company, $branch, 'Tenant Sizing Co.', 'yarn_dispatch', 250, 'approved');

        // Another tenant must not be able to generate an invoice for this plan.
        $other = User::factory()->create(['type' => 'company']);
        $this->actingAs($other)->withSession(['active_branch_id' => $branch->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Document not found for tenant context.');

        app(TextileSalesService::class)->createJobWorkInvoiceFromDispatchPlan((int) $plan->id);
    }

    /**
     * Creates an approved/released/closed dispatch plan document linked to the
     * given party for the given source type.
     */
    private function buildDispatchPlan(User $company, Branch $branch, string $partyName, string $sourceType, float $quantity, string $status): TextileWorkflowDocument
    {
        return TextileWorkflowDocument::create([
            'document_type' => 'dispatch_plan',
            'document_number' => 'DPLN-' . $sourceType . '-' . $company->id . '-' . $branch->id . '-' . str()->random(4),
            'party_name' => $partyName,
            'lot_reference' => 'JW-LOT-' . str()->random(6),
            'quantity' => $quantity,
            'unit' => 'kg',
            'status' => $status,
            'metadata' => ['source_type' => $sourceType],
            'creator_id' => $company->id,
            'created_by' => $company->id,
            'branch_id' => $branch->id,
        ]);
    }

    private function makeVendor(User $company, string $companyName): Vendor
    {
        return Vendor::create([
            'supplier_type' => 'sizing',
            'company_name' => $companyName,
            'contact_person_name' => 'Rajesh',
            'contact_person_email' => str()->slug($companyName) . '@example.test',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
    }

    private function linkVendorUser(Vendor $vendor): User
    {
        $user = User::create([
            'name' => $vendor->company_name,
            'email' => $vendor->contact_person_email,
            'password' => bcrypt('secret'),
            'type' => 'client',
            'creator_id' => $vendor->created_by,
            'created_by' => $vendor->created_by,
            'lang' => 'en',
            'email_verified_at' => now(),
        ]);

        $vendor->update(['user_id' => $user->id]);

        return $user;
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
            'name' => 'Textile JW Plan',
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
