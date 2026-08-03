<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileQualityProfile;
use DigitalFuzed\TextileCore\Models\TextileReferenceMaster;
use DigitalFuzed\TextileCore\Models\TextileRouteRecipe;
use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileMasterDataAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_its_quality_routes_and_unit_conversions(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);
        $company = $this->company();
        $otherCompany = $this->company();
        TextileQualityProfile::create(['name' => 'Other profile', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileRouteRecipe::create(['name' => 'Other route', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileUnitConversion::create(['from_unit' => 'meter', 'to_unit' => 'yard', 'factor' => 1.09361, 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileReferenceMaster::create(['master_type' => 'source_type', 'master_domain' => 'sales', 'name' => 'Other source type', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileReferenceMaster::create(['master_type' => 'source_action', 'master_domain' => 'sales', 'name' => 'Other source action', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileReferenceMaster::create(['master_type' => 'machine_type', 'master_domain' => 'manufacturing', 'name' => 'Other machine type', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileReferenceMaster::create(['master_type' => 'shed_type', 'master_domain' => 'manufacturing', 'name' => 'Other shed type', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileReferenceMaster::create(['master_type' => 'loom_status', 'master_domain' => 'manufacturing', 'name' => 'Other loom status', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileReferenceMaster::create(['master_type' => 'breakdown_reason', 'master_domain' => 'manufacturing', 'name' => 'Other breakdown reason', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);
        TextileReferenceMaster::create(['master_type' => 'maintenance_type', 'master_domain' => 'manufacturing', 'name' => 'Other maintenance type', 'is_active' => true, 'created_by' => $otherCompany->id, 'creator_id' => $otherCompany->id]);

        $this->actingAs($company)
            ->post(route('textile.quality-profiles.store'), ['name' => 'Grey Fabric QC', 'code' => 'QC-01', 'grade' => 'A', 'parameters' => 'GSM and width'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.route-recipes.store'), ['name' => 'Weaving Route', 'code' => 'RT-01', 'steps' => "Warping\nWeaving\nInspection"])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.unit-conversions.store'), ['from_unit' => 'kg', 'to_unit' => 'lb', 'factor' => '2.20462'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-types.store', ['domain' => 'sales']), ['name' => 'Sales Order', 'code' => 'SO', 'description' => 'Sales order source'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-actions.store', ['domain' => 'sales']), ['name' => 'Convert', 'code' => 'CNV', 'description' => 'Sales conversion action'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.machine-types.store', ['domain' => 'manufacturing']), ['name' => 'Rapier', 'code' => 'RAP', 'description' => 'Rapier loom'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.shed-types.store', ['domain' => 'manufacturing']), ['name' => 'Dobby', 'code' => 'DOB', 'description' => 'Dobby shed type'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.loom-statuses.store', ['domain' => 'manufacturing']), ['name' => 'Running', 'code' => 'RUN', 'description' => 'Loom currently running'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.breakdown-reasons.store', ['domain' => 'manufacturing']), ['name' => 'Mechanical', 'code' => 'MEC', 'description' => 'Mechanical failure'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.maintenance-types.store', ['domain' => 'manufacturing']), ['name' => 'Preventive', 'code' => 'PRE', 'description' => 'Preventive maintenance'])
            ->assertRedirect();

        $qualityId = TextileQualityProfile::query()->where('name', 'Grey Fabric QC')->value('id');
        $routeId = TextileRouteRecipe::query()->where('name', 'Weaving Route')->value('id');
        $conversionId = TextileUnitConversion::query()->where('from_unit', 'kg')->value('id');
        $sourceTypeId = TextileReferenceMaster::query()->where('master_type', 'source_type')->where('master_domain', 'sales')->where('name', 'Sales Order')->value('id');
        $sourceActionId = TextileReferenceMaster::query()->where('master_type', 'source_action')->where('master_domain', 'sales')->where('name', 'Convert')->value('id');
        $machineTypeId = TextileReferenceMaster::query()->where('master_type', 'machine_type')->where('master_domain', 'manufacturing')->where('name', 'Rapier')->value('id');
        $shedTypeId = TextileReferenceMaster::query()->where('master_type', 'shed_type')->where('master_domain', 'manufacturing')->where('name', 'Dobby')->value('id');
        $loomStatusId = TextileReferenceMaster::query()->where('master_type', 'loom_status')->where('master_domain', 'manufacturing')->where('name', 'Running')->value('id');
        $breakdownReasonId = TextileReferenceMaster::query()->where('master_type', 'breakdown_reason')->where('master_domain', 'manufacturing')->where('name', 'Mechanical')->value('id');
        $maintenanceTypeId = TextileReferenceMaster::query()->where('master_type', 'maintenance_type')->where('master_domain', 'manufacturing')->where('name', 'Preventive')->value('id');
        $otherQualityId = TextileQualityProfile::query()->where('name', 'Other profile')->value('id');
        $otherRouteId = TextileRouteRecipe::query()->where('name', 'Other route')->value('id');
        $otherConversionId = TextileUnitConversion::query()->where('from_unit', 'meter')->value('id');
        $otherSourceTypeId = TextileReferenceMaster::query()->where('master_type', 'source_type')->where('master_domain', 'sales')->where('name', 'Other source type')->value('id');
        $otherSourceActionId = TextileReferenceMaster::query()->where('master_type', 'source_action')->where('master_domain', 'sales')->where('name', 'Other source action')->value('id');
        $otherMachineTypeId = TextileReferenceMaster::query()->where('master_type', 'machine_type')->where('master_domain', 'manufacturing')->where('name', 'Other machine type')->value('id');
        $otherShedTypeId = TextileReferenceMaster::query()->where('master_type', 'shed_type')->where('master_domain', 'manufacturing')->where('name', 'Other shed type')->value('id');
        $otherLoomStatusId = TextileReferenceMaster::query()->where('master_type', 'loom_status')->where('master_domain', 'manufacturing')->where('name', 'Other loom status')->value('id');
        $otherBreakdownReasonId = TextileReferenceMaster::query()->where('master_type', 'breakdown_reason')->where('master_domain', 'manufacturing')->where('name', 'Other breakdown reason')->value('id');
        $otherMaintenanceTypeId = TextileReferenceMaster::query()->where('master_type', 'maintenance_type')->where('master_domain', 'manufacturing')->where('name', 'Other maintenance type')->value('id');

        $this->actingAs($company)
            ->post(route('textile.quality-profiles.update'), ['record_id' => $qualityId, 'name' => 'Grey Fabric QC Updated', 'code' => 'QC-02', 'grade' => 'A+', 'parameters' => 'GSM, width, shade'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.route-recipes.update'), ['record_id' => $routeId, 'name' => 'Weaving Route Updated', 'code' => 'RT-02', 'steps' => "Warping\nSizing\nWeaving"])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.unit-conversions.update'), ['record_id' => $conversionId, 'from_unit' => 'kg', 'to_unit' => 'lb', 'factor' => '2.20'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-types.update', ['domain' => 'sales']), ['record_id' => $sourceTypeId, 'name' => 'Sales Quotation', 'code' => 'SQ', 'description' => 'Updated source type'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-actions.update', ['domain' => 'sales']), ['record_id' => $sourceActionId, 'name' => 'Allocate For Dispatch', 'code' => 'AFD', 'description' => 'Updated source action'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.machine-types.update', ['domain' => 'manufacturing']), ['record_id' => $machineTypeId, 'name' => 'Airjet', 'code' => 'AIR', 'description' => 'Updated machine type'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.shed-types.update', ['domain' => 'manufacturing']), ['record_id' => $shedTypeId, 'name' => 'Open', 'code' => 'OPN', 'description' => 'Updated shed type'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.loom-statuses.update', ['domain' => 'manufacturing']), ['record_id' => $loomStatusId, 'name' => 'Idle', 'code' => 'IDL', 'description' => 'Updated loom status'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.breakdown-reasons.update', ['domain' => 'manufacturing']), ['record_id' => $breakdownReasonId, 'name' => 'Electrical', 'code' => 'ELE', 'description' => 'Updated breakdown reason'])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.maintenance-types.update', ['domain' => 'manufacturing']), ['record_id' => $maintenanceTypeId, 'name' => 'Corrective', 'code' => 'COR', 'description' => 'Updated maintenance type'])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.quality-profiles.update'), ['record_id' => $otherQualityId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.route-recipes.update'), ['record_id' => $otherRouteId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.unit-conversions.update'), ['record_id' => $otherConversionId, 'from_unit' => 'x', 'to_unit' => 'y', 'factor' => '1'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-types.update', ['domain' => 'sales']), ['record_id' => $otherSourceTypeId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-actions.update', ['domain' => 'sales']), ['record_id' => $otherSourceActionId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.machine-types.update', ['domain' => 'manufacturing']), ['record_id' => $otherMachineTypeId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.shed-types.update', ['domain' => 'manufacturing']), ['record_id' => $otherShedTypeId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.loom-statuses.update', ['domain' => 'manufacturing']), ['record_id' => $otherLoomStatusId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.breakdown-reasons.update', ['domain' => 'manufacturing']), ['record_id' => $otherBreakdownReasonId, 'name' => 'Should Not Update'])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.maintenance-types.update', ['domain' => 'manufacturing']), ['record_id' => $otherMaintenanceTypeId, 'name' => 'Should Not Update'])
            ->assertNotFound();

        $this->actingAs($company)
            ->post(route('textile.quality-profiles.archive'), ['record_id' => $qualityId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.route-recipes.archive'), ['record_id' => $routeId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.unit-conversions.archive'), ['record_id' => $conversionId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-types.archive', ['domain' => 'sales']), ['record_id' => $sourceTypeId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-actions.archive', ['domain' => 'sales']), ['record_id' => $sourceActionId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.machine-types.archive', ['domain' => 'manufacturing']), ['record_id' => $machineTypeId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.shed-types.archive', ['domain' => 'manufacturing']), ['record_id' => $shedTypeId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.loom-statuses.archive', ['domain' => 'manufacturing']), ['record_id' => $loomStatusId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.breakdown-reasons.archive', ['domain' => 'manufacturing']), ['record_id' => $breakdownReasonId])
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('textile.master-domains.maintenance-types.archive', ['domain' => 'manufacturing']), ['record_id' => $maintenanceTypeId])
            ->assertRedirect();

        $this->actingAs($company)
            ->post(route('textile.quality-profiles.archive'), ['record_id' => $otherQualityId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.route-recipes.archive'), ['record_id' => $otherRouteId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.unit-conversions.archive'), ['record_id' => $otherConversionId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-types.archive', ['domain' => 'sales']), ['record_id' => $otherSourceTypeId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.source-actions.archive', ['domain' => 'sales']), ['record_id' => $otherSourceActionId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.machine-types.archive', ['domain' => 'manufacturing']), ['record_id' => $otherMachineTypeId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.shed-types.archive', ['domain' => 'manufacturing']), ['record_id' => $otherShedTypeId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.loom-statuses.archive', ['domain' => 'manufacturing']), ['record_id' => $otherLoomStatusId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.breakdown-reasons.archive', ['domain' => 'manufacturing']), ['record_id' => $otherBreakdownReasonId])
            ->assertNotFound();
        $this->actingAs($company)
            ->post(route('textile.master-domains.maintenance-types.archive', ['domain' => 'manufacturing']), ['record_id' => $otherMaintenanceTypeId])
            ->assertNotFound();

        $this->assertDatabaseHas('textile_quality_profiles', ['id' => $qualityId, 'name' => 'Grey Fabric QC Updated', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_route_recipes', ['id' => $routeId, 'name' => 'Weaving Route Updated', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_unit_conversions', ['id' => $conversionId, 'factor' => 2.2, 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_reference_masters', ['id' => $sourceTypeId, 'master_type' => 'source_type', 'master_domain' => 'sales', 'name' => 'Sales Quotation', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_reference_masters', ['id' => $sourceActionId, 'master_type' => 'source_action', 'master_domain' => 'sales', 'name' => 'Allocate For Dispatch', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_reference_masters', ['id' => $machineTypeId, 'master_type' => 'machine_type', 'master_domain' => 'manufacturing', 'name' => 'Airjet', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_reference_masters', ['id' => $shedTypeId, 'master_type' => 'shed_type', 'master_domain' => 'manufacturing', 'name' => 'Open', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_reference_masters', ['id' => $loomStatusId, 'master_type' => 'loom_status', 'master_domain' => 'manufacturing', 'name' => 'Idle', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_reference_masters', ['id' => $breakdownReasonId, 'master_type' => 'breakdown_reason', 'master_domain' => 'manufacturing', 'name' => 'Electrical', 'is_active' => false, 'created_by' => $company->id]);
        $this->assertDatabaseHas('textile_reference_masters', ['id' => $maintenanceTypeId, 'master_type' => 'maintenance_type', 'master_domain' => 'manufacturing', 'name' => 'Corrective', 'is_active' => false, 'created_by' => $company->id]);

        $this->actingAs($company)
            ->get(route('textile.quality-profiles.index'))
            ->assertOk()
            ->assertDontSee('Grey Fabric QC Updated')
            ->assertDontSee('Other profile');

        $this->actingAs($company)
            ->get(route('textile.route-recipes.index'))
            ->assertOk()
            ->assertDontSee('Weaving Route Updated')
            ->assertDontSee('Other route');

        $this->actingAs($company)
            ->get(route('textile.unit-conversions.index'))
            ->assertOk();

        $this->actingAs($company)
            ->get(route('textile.master-domains.source-types.index', ['domain' => 'sales']))
            ->assertOk()
            ->assertDontSee('Sales Quotation')
            ->assertDontSee('Other source type');

        $this->actingAs($company)
            ->get(route('textile.master-domains.source-actions.index', ['domain' => 'sales']))
            ->assertOk()
            ->assertDontSee('Allocate For Dispatch')
            ->assertDontSee('Other source action');

        $this->actingAs($company)
            ->get(route('textile.master-domains.machine-types.index', ['domain' => 'manufacturing']))
            ->assertOk()
            ->assertDontSee('Airjet')
            ->assertDontSee('Other machine type');

        $this->actingAs($company)
            ->get(route('textile.master-domains.shed-types.index', ['domain' => 'manufacturing']))
            ->assertOk()
            ->assertDontSee('Open')
            ->assertDontSee('Other shed type');

        $this->actingAs($company)
            ->get(route('textile.master-domains.loom-statuses.index', ['domain' => 'manufacturing']))
            ->assertOk()
            ->assertDontSee('Idle')
            ->assertDontSee('Other loom status');

        $this->actingAs($company)
            ->get(route('textile.master-domains.breakdown-reasons.index', ['domain' => 'manufacturing']))
            ->assertOk()
            ->assertDontSee('Electrical')
            ->assertDontSee('Other breakdown reason');

        $this->actingAs($company)
            ->get(route('textile.master-domains.maintenance-types.index', ['domain' => 'manufacturing']))
            ->assertOk()
            ->assertDontSee('Corrective')
            ->assertDontSee('Other maintenance type');
    }

    private function company(): User
    {
        $plan = Plan::create(['name' => 'Textile Test Plan', 'modules' => ['TextileCore']]);
        $company = User::factory()->create(['type' => 'company', 'active_plan' => $plan->id, 'email_verified_at' => now()]);
        $role = Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web'], ['label' => 'Company', 'created_by' => $company->id]);
        $company->assignRole($role);
        UserActiveModule::create(['user_id' => $company->id, 'module' => 'TextileCore']);

        return $company;
    }
}