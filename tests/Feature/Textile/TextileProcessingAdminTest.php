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

class TextileProcessingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_run_job_work_processing_lifecycle_and_tenant_data_is_isolated(): void
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
            ->post(route('textile.processing.outward.store'), [
                'source_reference_type' => 'processing_order',
                'source_reference_id' => 9001,
                'source_action' => 'job_work_issue',
                'party_name' => 'Dye House Alpha',
                'lot_reference' => 'LOT-P-1',
                'quantity' => 100,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $outward = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'job_work_outward')
            ->latest('id')
            ->first();

        $this->assertNotNull($outward);
        $this->assertSame('draft', $outward->status);

        $this->actingAs($companyA)
            ->post(route('textile.processing.outward.release'), [
                'outward_id' => $outward->id,
            ])
            ->assertSessionHasNoErrors();

        $outward->refresh();
        $this->assertSame('released', $outward->status);

        $this->actingAs($companyA)
            ->post(route('textile.processing.batches.store'), [
                'outward_id' => $outward->id,
            ])
            ->assertSessionHasNoErrors();

        $batch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'processing_batch')
            ->latest('id')
            ->first();

        $this->assertNotNull($batch);
        $this->assertSame('draft', $batch->status);

        $this->actingAs($companyA)
            ->post(route('textile.processing.batches.release'), [
                'batch_id' => $batch->id,
            ])
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('released', $batch->status);

        $this->actingAs($companyA)
            ->post(route('textile.processing.inward.store'), [
                'batch_id' => $batch->id,
                'quantity' => 96,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $inward = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'job_work_inward')
            ->latest('id')
            ->first();

        $this->assertNotNull($inward);
        $this->assertSame('draft', $inward->status);

        $this->actingAs($companyA)
            ->post(route('textile.processing.inward.finalize'), [
                'inward_id' => $inward->id,
                'decision' => 'pass',
            ])
            ->assertSessionHasNoErrors();

        $inward->refresh();
        $this->assertSame('approved', $inward->status);

        $this->actingAs($companyA)
            ->post(route('textile.processing.reconcile'), [
                'outward_id' => $outward->id,
                'inward_id' => $inward->id,
                'notes' => 'Loss adjusted in reconciliation',
            ])
            ->assertSessionHasNoErrors();

        $reconciliation = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'job_work_reconciliation')
            ->latest('id')
            ->first();

        $this->assertNotNull($reconciliation);
        $this->assertSame('approved', $reconciliation->status);
        $this->assertSame((float) $outward->quantity - (float) $inward->quantity, (float) ($reconciliation->metadata['balance_quantity'] ?? -1));

        $this->actingAs($companyB)
            ->get(route('textile.processing.index'))
            ->assertOk()
            ->assertDontSee('LOT-P-1')
            ->assertDontSee('Dye House Alpha');

        $this->actingAs($companyA)
            ->get(route('textile.processing.index'))
            ->assertOk()
            ->assertSee('LOT-P-1')
            ->assertSee('Dye House Alpha');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Processing Plan',
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
