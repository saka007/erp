<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\LoginHistory;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileAuditLog;
use DigitalFuzed\TextileCore\Models\TextileBreakdown;
use DigitalFuzed\TextileCore\Models\TextileChemicalCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceCost;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Hrm\Models\Attendance;
use Workdo\Hrm\Models\Department;
use Workdo\Hrm\Models\Employee;

class TextileDashboardAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_view_tenant_scoped_dashboard_and_reports(): void
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

        TextileWorkflowDocument::create([
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'document_type' => 'sales_order',
            'document_number' => 'SO-A-0001',
            'party_name' => 'Alpha Customer',
            'lot_reference' => 'LOT-R-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
        ]);

        TextileWorkflowDocument::create([
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'document_type' => 'margin_snapshot',
            'document_number' => 'MS-A-0001',
            'party_name' => 'Alpha Customer',
            'lot_reference' => 'LOT-R-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => [
                'revenue_value' => 3000,
                'total_cost' => 2400,
                'margin_value' => 600,
                'margin_percent' => 20,
            ],
        ]);

        TextileWorkflowDocument::create([
            'created_by' => $companyB->id,
            'creator_id' => $companyB->id,
            'document_type' => 'margin_snapshot',
            'document_number' => 'MS-B-0001',
            'party_name' => 'Beta Customer',
            'lot_reference' => 'LOT-R-B',
            'quantity' => 90,
            'unit' => 'mtr',
            'status' => 'approved',
            'metadata' => [
                'revenue_value' => 9999,
                'total_cost' => 9000,
                'margin_value' => 999,
                'margin_percent' => 9.99,
            ],
        ]);

        LoginHistory::create([
            'user_id' => $companyA->id,
            'ip' => '10.0.0.1',
            'date' => now()->toDateString(),
            'details' => ['source' => 'dashboard-test'],
            'type' => 'login',
            'created_by' => $companyA->id,
        ]);

        TextileAuditLog::create([
            'event_type' => 'textile.workflow.created',
            'payload' => ['document_number' => 'AUD-A-0001', 'action' => 'created'],
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->get(route('textile.dashboard.index'))
            ->assertOk()
            ->assertSee('LOT-R-1')
            ->assertSee('Alpha Customer')
            ->assertSee('MS-A-0001')
            ->assertDontSee('LOT-R-B')
            ->assertDontSee('Beta Customer')
            ->assertDontSee('MS-B-0001')
            ->assertInertia(function (Assert $page): void {
                $page->where('loginHistoryCount', 1)
                    ->where('auditLogCount', 1)
                    ->has('recentLoginHistory', 1)
                    ->has('recentAuditLogs', 1);

                $this->assertSame('10.0.0.1', $page->toArray()['props']['recentLoginHistory'][0]['ip'] ?? null);
                $this->assertSame('AUD-A-0001', $page->toArray()['props']['recentAuditLogs'][0]['payload']['document_number'] ?? null);
            });
    }

    public function test_dashboard_provides_chart_series_and_kpis_with_tenant_isolation(): void
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

        $this->document($companyA, 'weaving_output', 'WO-T-1', 'approved', 100, null, '2026-08-01 10:00:00');
        $this->document($companyA, 'weaving_output', 'WO-T-2', 'approved', 150, null, '2026-08-02 10:00:00');
        $this->document($companyA, 'dispatch_plan', 'DP-T-1', 'released', 120, null, '2026-08-02 11:00:00');
        $this->document($companyA, 'margin_snapshot', 'MS-T-1', 'approved', 100, [
            'revenue_value' => 5000,
            'total_cost' => 3000,
            'margin_value' => 2000,
            'margin_percent' => 40,
        ], '2026-08-01 12:00:00');
        $this->document($companyA, 'loom_efficiency', 'LE-T-1', 'approved', 100, [
            'machine_name' => 'Loom-1',
            'efficiency_percent' => 88.5,
        ], '2026-08-01 13:00:00');
        $this->document($companyA, 'sales_order', 'SO-T-1', 'draft', 50, null, '2026-08-03 10:00:00');

        TextilePowerCost::create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'meter_reading_start' => 1000,
            'meter_reading_end' => 1750,
            'units_consumed' => 750,
            'rate_per_unit' => 8,
            'total_cost' => 6000,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $this->document($companyB, 'weaving_output', 'WO-T-9', 'approved', 999, null, '2026-08-01 10:00:00');

        $props = [];
        $this->actingAs($companyA)
            ->get(route('textile.dashboard.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props'];

                $page->has('kpis', 6)
                    ->has('productionTrend', 14)
                    ->has('dispatchTrend', 14)
                    ->has('financialTrend', 14)
                    ->has('machineEfficiency', 1)
                    ->has('powerTrend', 1)
                    ->has('statusDistribution', 3)
                    ->has('typeDistribution', 5)
                    ->where('statusDistribution', [
                        ['name' => 'approved', 'value' => 4],
                        ['name' => 'draft', 'value' => 1],
                        ['name' => 'released', 'value' => 1],
                    ]);
            });

        $production = collect($props['productionTrend']);
        $this->assertSame(250.0, (float) $production->sum('quantity'));
        $this->assertSame(150.0, (float) $production->firstWhere('date', '2026-08-02')['quantity']);

        $dispatch = collect($props['dispatchTrend']);
        $this->assertSame(120.0, (float) $dispatch->firstWhere('date', '2026-08-02')['quantity']);

        $financial = collect($props['financialTrend']);
        $this->assertSame(5000.0, (float) $financial->firstWhere('date', '2026-08-01')['revenue']);
        $this->assertSame(3000.0, (float) $financial->firstWhere('date', '2026-08-01')['cost']);
        $this->assertSame(2000.0, (float) $financial->firstWhere('date', '2026-08-01')['margin']);

        $this->assertSame('Loom-1', $props['machineEfficiency'][0]['name']);
        $this->assertSame(88.5, (float) $props['machineEfficiency'][0]['efficiency']);

        $this->assertSame(750.0, (float) $props['powerTrend'][0]['units']);
        $this->assertSame(6000.0, (float) $props['powerTrend'][0]['cost']);

        $this->assertSame('5,000.00', (string) $props['kpis'][0]['value']);
        $this->assertSame('2,000.00', (string) $props['kpis'][2]['value']);
        $this->assertSame('40.00', (string) $props['kpis'][3]['value']);
        $this->assertSame(6, (int) $props['kpis'][4]['value']);
        $this->assertSame(1, (int) $props['kpis'][5]['value']);

        // Company B sees none of company A's chart data
        $propsB = [];
        $this->actingAs($companyB)
            ->get(route('textile.dashboard.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$propsB) {
                $propsB = $page->toArray()['props'];
            });
        $this->assertSame(999.0, (float) collect($propsB['productionTrend'])->sum('quantity'));
        $this->assertSame(0.0, (float) collect($propsB['financialTrend'])->sum('revenue'));
        $this->assertSame([], $propsB['machineEfficiency']);
        $this->assertSame([], $propsB['powerTrend']);
    }

    public function test_domain_dashboards_provide_tenant_scoped_data(): void
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

        // Purchase
        $this->document($companyA, 'purchase_order', 'PO-0001', 'approved', 100, null, '2026-08-01 10:00:00');
        $this->document($companyA, 'grn', 'GRN-0001', 'approved', 90, null, '2026-08-02 10:00:00');

        // Sales
        $this->document($companyA, 'sales_order', 'SO-0001', 'approved', 120, null, '2026-08-01 10:00:00');

        // Inventory
        TextileLot::create([
            'lot_reference' => 'LOT-D-001',
            'batch_number' => 'B-D1',
            'received_quantity' => 100,
            'available_quantity' => 80,
            'status' => 'available',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);
        TextileMovement::create([
            'movement_type' => 'receipt',
            'lot_reference' => 'LOT-D-001',
            'location_from' => 'Supplier',
            'location_to' => 'Store',
            'quantity' => 50,
            'status' => 'completed',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'created_at' => '2026-08-01 10:00:00',
        ]);
        TextileMovement::create([
            'movement_type' => 'issue',
            'lot_reference' => 'LOT-D-001',
            'location_from' => 'Store',
            'location_to' => 'Dispatch',
            'quantity' => 10,
            'status' => 'completed',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'created_at' => '2026-08-02 10:00:00',
        ]);

        // Finance costs
        TextilePowerCost::create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'meter_reading_start' => 1000,
            'meter_reading_end' => 1750,
            'units_consumed' => 750,
            'rate_per_unit' => 8,
            'total_cost' => 6000,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);
        TextileChemicalCost::create([
            'chemical_date' => '2026-08-01',
            'chemical_name' => 'Reactive Dye',
            'total_cost' => 1200,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Maintenance
        TextileBreakdown::create([
            'breakdown_code' => 'BD-0001',
            'breakdown_date' => '2026-08-01',
            'machine_name' => 'Loom-1',
            'machine_type' => 'power_loom',
            'fault_description' => 'Shuttle jam',
            'status' => 'reported',
            'downtime_minutes' => 90,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'created_at' => '2026-08-01 10:00:00',
        ]);
        TextileMaintenanceCost::create([
            'cost_code' => 'MC-0001',
            'cost_date' => '2026-08-01',
            'machine_name' => 'Loom-1',
            'labor_cost' => 2000,
            'parts_cost' => 2500,
            'external_cost' => 500,
            'total_cost' => 5000,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // HR
        $department = Department::create([
            'department_name' => 'Weaving',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);
        Employee::create([
            'employee_id' => 'EMP-A-001',
            'department_id' => $department->id,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);
        $shift = \Workdo\Hrm\Models\Shift::create([
            'shift_name' => 'Day Shift',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);
        Attendance::create([
            'employee_id' => 1,
            'shift_id' => $shift->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
            'total_hour' => 8,
            'overtime_hours' => 1.5,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);
        Attendance::create([
            'employee_id' => 1,
            'shift_id' => $shift->id,
            'date' => now()->toDateString(),
            'status' => 'absent',
            'clock_in' => '09:00:00',
            'clock_out' => '10:00:00',
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Company B: only its own sales order
        $this->document($companyB, 'sales_order', 'SO-B-0001', 'approved', 999, null, '2026-08-01 10:00:00');

        $props = [];
        $this->actingAs($companyA)
            ->get(route('textile.dashboard.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props'];

                $page->has('purchase.kpis', 6)
                    ->has('inventory.kpis', 6)
                    ->has('sales.kpis', 6)
                    ->has('finance.kpis', 6)
                    ->has('maintenance.kpis', 6)
                    ->has('hr.kpis', 6)
                    ->has('purchase.trend', 14)
                    ->has('inventory.movementTrend', 14)
                    ->has('sales.trend', 14)
                    ->has('finance.financialTrend', 14)
                    ->has('maintenance.trend', 14)
                    ->has('hr.attendanceTrend', 14);
            });

        $purchase = $props['purchase'];
        $this->assertSame(2, (int) $purchase['kpis'][0]['value']);
        $this->assertSame(190.0, (float) collect($purchase['trend'])->sum('quantity'));
        $this->assertSame(90.0, (float) collect($purchase['trend'])->firstWhere('date', '2026-08-02')['quantity']);

        $inventory = $props['inventory'];
        $this->assertSame(1, (int) $inventory['kpis'][0]['value']);
        $this->assertSame('80.00', (string) $inventory['kpis'][1]['value']);
        $this->assertSame(50.0, (float) collect($inventory['movementTrend'])->firstWhere('date', '2026-08-01')['receipt']);
        $this->assertSame(10.0, (float) collect($inventory['movementTrend'])->firstWhere('date', '2026-08-02')['issue']);

        $sales = $props['sales'];
        $this->assertSame(1, (int) $sales['kpis'][0]['value']);
        $this->assertSame(120.0, (float) collect($sales['trend'])->sum('quantity'));

        $finance = $props['finance'];
        $chemical = collect($finance['costBreakdown'])->firstWhere('name', 'Chemicals');
        $this->assertSame(1200.0, (float) $chemical['value']);
        $this->assertSame(6000.0, (float) collect($finance['costBreakdown'])->firstWhere('name', 'Power')['value']);

        $maintenance = $props['maintenance'];
        $this->assertSame(1, (int) $maintenance['kpis'][0]['value']);
        $this->assertSame(1, (int) $maintenance['kpis'][1]['value']);
        $this->assertSame('1.50', (string) $maintenance['kpis'][2]['value']);
        $this->assertSame('5,000.00', (string) $maintenance['kpis'][3]['value']);
        $this->assertSame(1.5, (float) $maintenance['downtimeByMachine'][0]['hours']);

        $hr = $props['hr'];
        $this->assertSame(1, (int) $hr['kpis'][0]['value']);
        $this->assertSame(1, (int) $hr['kpis'][1]['value']);
        $this->assertSame(2, (int) $hr['kpis'][2]['value']);
        $this->assertSame('Weaving', $hr['employeesByDepartment'][0]['name']);
        $this->assertSame(2, (int) collect($hr['attendanceTrend'])->sum('present'));

        // Company B sees none of company A's domain data
        $propsB = [];
        $this->actingAs($companyB)
            ->get(route('textile.dashboard.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$propsB) {
                $propsB = $page->toArray()['props'];
            });
        $this->assertSame(0, (int) $propsB['purchase']['kpis'][0]['value']);
        $this->assertSame([], $propsB['inventory']['lotStatus']);
        $this->assertSame(0.0, (float) collect($propsB['finance']['costBreakdown'])->sum('value'));
        $this->assertSame([], $propsB['maintenance']['downtimeByMachine']);
        $this->assertSame(0, (int) $propsB['hr']['kpis'][0]['value']);
    }

    private function document(User $user, string $type, string $number, string $status, float $quantity, ?array $metadata, ?string $createdAt = null): TextileWorkflowDocument
    {
        $attributes = [
            'document_type' => $type,
            'document_number' => $number,
            'source_reference_type' => 'textile_workflow_document',
            'source_reference_id' => 1,
            'party_name' => 'Test Party',
            'lot_reference' => 'LOT-' . $type,
            'quantity' => $quantity,
            'unit' => 'mtr',
            'status' => $status,
            'metadata' => $metadata,
            'created_by' => $user->id,
            'creator_id' => $user->id,
        ];

        if ($createdAt !== null) {
            $attributes['created_at'] = $createdAt;
            $attributes['updated_at'] = $createdAt;
        }

        return TextileWorkflowDocument::create($attributes);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Dashboard Plan',
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
