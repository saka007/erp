<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileBreakdown;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceSparePartUsage;
use DigitalFuzed\TextileCore\Models\TextilePmSchedule;
use DigitalFuzed\TextileCore\Models\TextileServiceSchedule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileMaintenanceAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_log_maintenance_workflow_with_tenant_isolation(): void
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
            'metadata' => ['loom_status' => 'running', 'operator_name' => 'Ravi'],
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Preventive maintenance schedule
        $this->actingAs($companyA)
            ->post(route('textile.maintenance.pm-schedules.store'), [
                'pm_code' => 'PM-001',
                'scheduled_date' => now()->toDateString(),
                'next_due_date' => now()->addMonth()->toDateString(),
                'machine_id' => $machine->id,
                'machine_type' => 'loom',
                'maintenance_type' => 'lubrication',
                'frequency_type' => 'days',
                'frequency_value' => 30,
                'task_description' => 'Lubricate loom gearbox',
                'status' => 'planned',
                'notes' => 'Monthly lubrication',
            ])
            ->assertSessionHasNoErrors();

        $pm = TextilePmSchedule::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($pm);
        $this->assertSame('LM-0001', $pm->machine_name);
        $this->assertSame('lubrication', $pm->maintenance_type);

        // Breakdown with downtime
        $this->actingAs($companyA)
            ->post(route('textile.maintenance.breakdowns.store'), [
                'breakdown_code' => 'BD-001',
                'breakdown_date' => now()->toDateString(),
                'machine_id' => $machine->id,
                'machine_type' => 'loom',
                'fault_description' => 'Shuttle jam detected',
                'symptom' => 'mechanical_failure',
                'downtime_minutes' => 120,
                'impact' => 'Weaving stopped on shift B',
                'status' => 'reported',
                'notes' => 'Waiting for spare part',
            ])
            ->assertSessionHasNoErrors();

        $breakdown = TextileBreakdown::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($breakdown);
        $this->assertSame('LM-0001', $breakdown->machine_name);
        $this->assertSame(120, $breakdown->downtime_minutes);

        // Service schedule linked to the PM schedule
        $this->actingAs($companyA)
            ->post(route('textile.maintenance.service-schedules.store'), [
                'schedule_code' => 'SVC-001',
                'scheduled_date' => now()->addWeek()->toDateString(),
                'pm_schedule_id' => $pm->id,
                'machine_id' => $machine->id,
                'machine_type' => 'loom',
                'technician_name' => 'Technician Amit',
                'status' => 'scheduled',
                'completion_notes' => '',
            ])
            ->assertSessionHasNoErrors();

        $service = TextileServiceSchedule::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($service);
        $this->assertSame('LM-0001', $service->machine_name);
        $this->assertSame($pm->id, (int) $service->pm_schedule_id);

        // Spare part usage linked to the breakdown
        $this->actingAs($companyA)
            ->post(route('textile.maintenance.spare-part-usages.store'), [
                'usage_code' => 'SP-001',
                'usage_date' => now()->toDateString(),
                'maintenance_ref_type' => 'breakdown',
                'maintenance_ref_id' => $breakdown->id,
                'part_name' => 'Shuttle',
                'part_code' => 'SH-101',
                'quantity' => 2,
                'unit_cost' => 450,
                'notes' => 'Replaced shuttle assembly',
            ])
            ->assertSessionHasNoErrors();

        $usage = TextileMaintenanceSparePartUsage::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($usage);
        $this->assertSame('900.00', (string) $usage->total_cost);
        $this->assertSame('LM-0001', $usage->machine_name);

        // Maintenance cost
        $this->actingAs($companyA)
            ->post(route('textile.maintenance.maintenance-costs.store'), [
                'cost_code' => 'MCOST-001',
                'cost_date' => now()->toDateString(),
                'machine_id' => $machine->id,
                'machine_type' => 'loom',
                'labor_cost' => 2000,
                'parts_cost' => 900,
                'external_cost' => 600,
                'notes' => 'Breakdown repair cost',
            ])
            ->assertSessionHasNoErrors();

        $cost = TextileMaintenanceCost::query()
            ->where('created_by', $companyA->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame('3500.00', (string) $cost->total_cost);
        $this->assertSame('LM-0001', $cost->machine_name);

        // Tenant isolation: companyB sees nothing
        $this->actingAs($companyB)
            ->get(route('textile.maintenance.index'))
            ->assertOk()
            ->assertDontSee('PM-001')
            ->assertDontSee('BD-001')
            ->assertDontSee('SVC-001')
            ->assertDontSee('SP-001')
            ->assertDontSee('MCOST-001');

        $this->assertSame(1, TextilePmSchedule::where('created_by', $companyA->id)->count());
        $this->assertSame(0, TextilePmSchedule::where('created_by', $companyB->id)->count());
        $this->assertSame(0, TextileBreakdown::where('created_by', $companyB->id)->count());
        $this->assertSame(0, TextileServiceSchedule::where('created_by', $companyB->id)->count());
        $this->assertSame(0, TextileMaintenanceSparePartUsage::where('created_by', $companyB->id)->count());
        $this->assertSame(0, TextileMaintenanceCost::where('created_by', $companyB->id)->count());
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Maintenance Plan',
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
