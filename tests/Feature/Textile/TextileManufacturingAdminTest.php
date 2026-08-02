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

class TextileManufacturingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_run_manufacturing_lifecycle_and_tenant_data_is_isolated(): void
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
            ->post(route('textile.manufacturing.beams.store'), [
                'source_reference_type' => 'sales_order',
                'source_reference_id' => 8001,
                'source_action' => 'beam_prepare',
                'party_name' => 'Mill Unit A',
                'lot_reference' => 'LOT-M-1',
                'quantity' => 250,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->latest('id')
            ->first();

        $this->assertNotNull($beam);
        $this->assertSame('draft', $beam->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.approve'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $beam->refresh();
        $this->assertSame('approved', $beam->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.store'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $batch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_batch')
            ->latest('id')
            ->first();

        $this->assertNotNull($batch);
        $this->assertSame('draft', $batch->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.release'), [
                'batch_id' => $batch->id,
            ])
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('released', $batch->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.weaving-output.store'), [
                'batch_id' => $batch->id,
                'quantity' => 240,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $weavingOutput = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'weaving_output')
            ->latest('id')
            ->first();

        $this->assertNotNull($weavingOutput);
        $this->assertSame('approved', $weavingOutput->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.waste.store'), [
                'batch_id' => $batch->id,
                'quantity' => 8,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $waste = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'waste')
            ->latest('id')
            ->first();

        $this->assertNotNull($waste);
        $this->assertSame('approved', $waste->status);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.rework.store'), [
                'weaving_output_id' => $weavingOutput->id,
                'quantity' => 2,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $rework = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'rework')
            ->latest('id')
            ->first();

        $this->assertNotNull($rework);
        $this->assertSame('approved', $rework->status);

        $this->actingAs($companyB)
            ->get(route('textile.manufacturing.index'))
            ->assertOk()
            ->assertDontSee('LOT-M-1')
            ->assertDontSee('Mill Unit A');

        $this->actingAs($companyA)
            ->get(route('textile.manufacturing.index'))
            ->assertOk()
            ->assertSee('LOT-M-1')
            ->assertSee('Mill Unit A');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Manufacturing Plan',
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
