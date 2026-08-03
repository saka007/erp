<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileCore\Services\TextileReportsService;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileReportsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_aggregate_tenant_data_and_isolate_companies(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();

        // Company A: production batch, weaving output, margin snapshot, power cost, dispatch plan, grey roll
        $this->document($companyA, 'production_batch', 'PB-0001', 'released', 300, null);
        $this->document($companyA, 'weaving_output', 'WO-0001', 'approved', 280, null);
        $this->document($companyA, 'margin_snapshot', 'MS-0001', 'approved', 280, [
            'revenue_value' => 50000,
            'total_cost' => 30000,
        ]);
        $this->document($companyA, 'dispatch_plan', 'DP-0001', 'released', 280, [
            'dispatch_mode' => 'truck',
            'truck_number' => 'MH-12-1234',
            'freight_amount' => 2500,
        ]);
        $this->document($companyA, 'grey_fabric_roll', 'GR-0001', 'approved', 55, [
            'roll_number' => 'ROLL-1',
            'roll_weight' => 8.5,
            'roll_length' => 55,
            'grade' => 'A',
        ]);
        $this->document($companyA, 'loom_efficiency', 'LE-0001', 'approved', 260, [
            'planned_quantity' => 300,
            'runtime_hours' => 6,
            'downtime_hours' => 1,
            'efficiency_percent' => 86.67,
            'operator_name' => 'Ravi',
        ]);
        $this->document($companyA, 'waste', 'WS-0001', 'approved', 20, null);

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

        TextileLot::create([
            'lot_reference' => 'LOT-A-001',
            'batch_number' => 'B-1',
            'received_quantity' => 100,
            'available_quantity' => 90,
            'status' => 'available',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        TextileMovement::create([
            'movement_type' => 'issue',
            'lot_reference' => 'LOT-A-001',
            'location_from' => 'Store',
            'location_to' => 'Dispatch',
            'quantity' => 10,
            'status' => 'completed',
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Company B: a separate production batch only
        $this->document($companyB, 'production_batch', 'PB-0002', 'released', 500, null);

        // Reports page renders for company A
        $this->actingAs($companyA)
            ->get(route('textile.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('production.kpis', 4)
                ->has('production.rows', 2)
                ->has('loom.rows', 0)
                ->has('operator.rows', 0)
                ->has('yarnConsumption.rows', 0)
                ->has('beam.rows', 0)
                ->has('greyFabric.rows', 1)
                ->has('finishedFabric.rows', 1)
                ->has('dispatch.rows', 1)
                ->has('purchase.rows', 0)
                ->has('sales.rows', 0)
                ->has('stock.rows', 1)
                ->has('profit.rows', 1)
                ->has('machineEfficiency.rows', 1)
                ->has('wasteAnalysis.rows', 1)
                ->has('powerConsumption.rows', 1)
                ->has('dailyMis.rows', 1));

        // Aggregations (as company A)
        $this->actingAs($companyA);
        $service = app(TextileReportsService::class);
        $production = $service->production();
        $this->assertSame(300.0, (float) $production['rows'][0]['quantity']);
        $this->assertSame('1', (string) $production['kpis'][0]['value']);

        $profit = $service->profit();
        $this->assertSame('50,000.00', (string) $profit['kpis'][1]['value']);
        $this->assertSame('20,000.00', (string) $profit['kpis'][3]['value']);

        $power = $service->powerConsumption();
        $this->assertSame('750.00', (string) $power['kpis'][1]['value']);
        $this->assertSame('6,000.00', (string) $power['kpis'][2]['value']);

        $mis = $service->dailyMis();
        $this->assertSame(1, count($mis['rows']));
        $this->assertSame(1, (int) $mis['rows'][0]['dispatches']);

        // Company B sees none of company A's rows
        $this->actingAs($companyB)
            ->get(route('textile.reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('production.rows', 1)
                ->has('greyFabric.rows', 0)
                ->has('dispatch.rows', 0)
                ->has('profit.rows', 0)
                ->has('powerConsumption.rows', 0));
    }

    public function test_reports_respect_date_filters(): void
    {
        $company = $this->company();

        $this->document($company, 'production_batch', 'PB-1001', 'released', 300, null, '2026-07-01 10:00:00');
        $this->document($company, 'production_batch', 'PB-1002', 'released', 400, null, '2026-08-10 10:00:00');

        $this->actingAs($company);
        $service = app(TextileReportsService::class);
        $filtered = $service->production(['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertSame(1, count($filtered['rows']));
        $this->assertSame('PB-1002', $filtered['rows'][0]['document_number']);
    }

    public function test_reports_export_xlsx_and_pdf_respect_section_and_tenant(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();

        $this->document($companyA, 'production_batch', 'PB-2001', 'released', 300, null);
        $this->document($companyB, 'production_batch', 'PB-2002', 'released', 500, null);

        // Excel export: correct content type, company A data only
        $this->actingAs($companyA)
            ->get(route('textile.reports.export', ['section' => 'production', 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // PDF export via dompdf
        $this->actingAs($companyA)
            ->get(route('textile.reports.export', ['section' => 'production', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf', true);

        // Defaults: invalid section falls back to production, invalid format falls back to xlsx
        $this->actingAs($companyA)
            ->get(route('textile.reports.export', ['section' => 'bogus', 'format' => 'docx']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Tenant isolation: company B export contains only its own document
        $response = $this->actingAs($companyB)
            ->get(route('textile.reports.export', ['section' => 'production', 'format' => 'xlsx']))
            ->assertOk();

        $zip = new \ZipArchive();
        $zip->open($response->getFile()->getPathname());
        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
        $this->assertStringContainsString('PB-2002', $sharedStrings);
        $this->assertStringNotContainsString('PB-2001', $sharedStrings);
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
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $plan = Plan::create([
            'name' => 'Textile Reports Plan',
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
