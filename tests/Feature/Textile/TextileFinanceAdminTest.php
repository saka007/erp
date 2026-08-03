<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileChemicalCost;
use DigitalFuzed\TextileCore\Models\TextileCostCenter;
use DigitalFuzed\TextileCore\Models\TextileLabourCost;
use DigitalFuzed\TextileCore\Models\TextileMachineCost;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileFinanceAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_record_finance_costs_with_tenant_isolation(): void
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

        $machine = TextileWorkflowDocument::create([
            'document_type' => 'loom_master',
            'document_number' => 'LM-0001',
            'source_reference_type' => 'textile_reference_master',
            'source_reference_id' => 1,
            'source_action' => 'loom_register',
            'quantity' => 0,
            'status' => 'approved',
            'metadata' => ['machine_type' => 'loom', 'operator_name' => 'Ravi'],
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $costCenter = TextileCostCenter::create([
            'name' => 'Weaving',
            'code' => 'WEAVE',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Machine cost with computed total
        $this->actingAs($companyA)
            ->post(route('textile.finance.machine-costs.store'), [
                'machine_id' => $machine->id,
                'machine_type' => 'loom',
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'depreciation_cost' => 1000,
                'maintenance_cost' => 500,
                'power_cost' => 300,
                'labor_cost' => 200,
                'other_cost' => 100,
                'notes' => 'August allocation',
            ])
            ->assertSessionHasNoErrors();

        $machineCost = TextileMachineCost::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($machineCost);
        $this->assertSame('LM-0001', $machineCost->machine_name);
        $this->assertSame('2100.00', (string) $machineCost->total_cost);

        // Power cost: units from readings, total = units x rate
        $this->actingAs($companyA)
            ->post(route('textile.finance.power-costs.store'), [
                'period_start' => '2026-08-01',
                'period_end' => '2026-08-31',
                'meter_reading_start' => 1000,
                'meter_reading_end' => 1750,
                'rate_per_unit' => 8,
                'allocation_notes' => 'Main meter',
            ])
            ->assertSessionHasNoErrors();

        $powerCost = TextilePowerCost::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($powerCost);
        $this->assertSame('750.00', (string) $powerCost->units_consumed);
        $this->assertSame('6000.00', (string) $powerCost->total_cost);

        // Chemical cost: total = quantity x unit cost
        $this->actingAs($companyA)
            ->post(route('textile.finance.chemical-costs.store'), [
                'chemical_date' => '2026-08-05',
                'chemical_name' => 'Reactive Dye',
                'process_stage' => 'dyeing',
                'quantity' => 50,
                'unit' => 'kg',
                'unit_cost' => 120,
                'batch_reference' => 'BATCH-01',
                'notes' => 'Dyeing batch',
            ])
            ->assertSessionHasNoErrors();

        $chemicalCost = TextileChemicalCost::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($chemicalCost);
        $this->assertSame('6000.00', (string) $chemicalCost->total_cost);
        $this->assertSame('dyeing', $chemicalCost->process_stage);

        // Labour cost: total = workers x hours x rate, denormalized cost center
        $this->actingAs($companyA)
            ->post(route('textile.finance.labour-costs.store'), [
                'labour_date' => '2026-08-06',
                'cost_center_id' => $costCenter->id,
                'shift_name' => 'A',
                'worker_count' => 10,
                'hours_worked' => 8,
                'rate_per_hour' => 50,
                'notes' => 'Shift A weaving',
            ])
            ->assertSessionHasNoErrors();

        $labourCost = TextileLabourCost::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($labourCost);
        $this->assertSame('Weaving', $labourCost->cost_center_name);
        $this->assertSame('4000.00', (string) $labourCost->total_cost);

        // Tenant isolation: companyB sees nothing
        $this->actingAs($companyB)
            ->get(route('textile.finance.index'))
            ->assertOk()
            ->assertDontSee('LM-0001')
            ->assertDontSee('Reactive Dye')
            ->assertDontSee('BATCH-01')
            ->assertDontSee('Weaving');

        $this->assertSame(1, TextileMachineCost::where('created_by', $companyA->id)->count());
        $this->assertSame(0, TextileMachineCost::where('created_by', $companyB->id)->count());
        $this->assertSame(0, TextilePowerCost::where('created_by', $companyB->id)->count());
        $this->assertSame(0, TextileChemicalCost::where('created_by', $companyB->id)->count());
        $this->assertSame(0, TextileLabourCost::where('created_by', $companyB->id)->count());
    }

    public function test_cost_per_roll_is_computed_from_costing_entry_rolls_count(): void
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

        $source = TextileWorkflowDocument::create([
            'document_type' => 'production_batch',
            'document_number' => 'PB-001',
            'source_reference_type' => 'textile_reference_master',
            'source_reference_id' => 1,
            'source_action' => 'batch_register',
            'quantity' => 1000,
            'unit' => 'mtr',
            'status' => 'approved',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.costing.entries.store'), [
                'source_document_id' => $source->id,
                'party_name' => 'Fabric Buyer',
                'quantity' => 1000,
                'unit' => 'mtr',
                'rolls_count' => 10,
                'material_cost' => 20000,
                'conversion_cost' => 5000,
                'overhead_cost' => 3000,
                'variance_value' => 0,
                'revenue_value' => 40000,
            ])
            ->assertSessionHasNoErrors();

        $entry = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'costing_entry')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(10.0, (float) ($entry->metadata['rolls_count'] ?? 0));

        // Finalize -> approved -> finance computes cost per roll
        $this->actingAs($companyA)
            ->post(route('textile.costing.entries.finalize'), [
                'costing_entry_id' => $entry->id,
            ])
            ->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame('approved', $entry->status);
        $this->assertSame(28000.0, (float) ($entry->metadata['total_cost'] ?? 0));

        $this->actingAs($companyA)
            ->get(route('textile.finance.index'))
            ->assertOk();

        $service = app(\DigitalFuzed\TextileCore\Services\TextileFinanceService::class);
        $costPerRoll = $service->costPerRoll();

        $this->assertSame(10.0, (float) $costPerRoll['total_rolls']);
        $this->assertSame(2800.0, (float) $costPerRoll['average_cost_per_roll']);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Finance Plan',
            'modules' => ['TextileCore'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web'], ['label' => 'Company', 'created_by' => $company->id]);
        $company->assignRole($role);

        UserActiveModule::create(['user_id' => $company->id, 'module' => 'TextileCore']);

        return $company;
    }
}
