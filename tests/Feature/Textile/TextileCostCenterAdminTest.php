<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileCostCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileCostCenterAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_cost_centers_with_tenant_isolation(): void
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
            ->post(route('textile.cost-centers.store'), [
                'name' => 'Sizing Department',
                'code' => 'SZ-01',
                'notes' => 'Warp preparation operations',
            ])
            ->assertSessionHasNoErrors();

        $costCenter = TextileCostCenter::query()
            ->where('created_by', $companyA->id)
            ->where('name', 'Sizing Department')
            ->first();

        $this->assertNotNull($costCenter);

        $this->actingAs($companyA)
            ->post(route('textile.cost-centers.update'), [
                'cost_center_id' => $costCenter->id,
                'name' => 'Sizing and Warping',
                'code' => 'SZ-01',
                'notes' => 'Updated name',
            ])
            ->assertSessionHasNoErrors();

        $costCenter->refresh();
        $this->assertSame('Sizing and Warping', $costCenter->name);

        $this->actingAs($companyB)
            ->get(route('textile.cost-centers.index'))
            ->assertOk()
            ->assertDontSee('Sizing and Warping')
            ->assertDontSee('SZ-01');

        $this->actingAs($companyA)
            ->get(route('textile.cost-centers.index'))
            ->assertOk()
            ->assertSee('Sizing and Warping')
            ->assertSee('SZ-01');

        $this->actingAs($companyA)
            ->post(route('textile.cost-centers.archive'), [
                'cost_center_id' => $costCenter->id,
            ])
            ->assertSessionHasNoErrors();

        $costCenter->refresh();
        $this->assertFalse((bool) $costCenter->is_active);

        $this->actingAs($companyA)
            ->get(route('textile.cost-centers.index'))
            ->assertOk()
            ->assertDontSee('Sizing and Warping');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Core Plan',
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
