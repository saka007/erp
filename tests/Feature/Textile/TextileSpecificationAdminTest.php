<?php

namespace Tests\Feature\Textile;

use App\Models\User;
use App\Models\Plan;
use App\Models\AddOn;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileSpecification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileSpecificationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_only_its_own_textile_specifications(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'package_name' => 'textile-core',
        ]);
        $companyA = $this->company();
        $companyB = $this->company();

        TextileSpecification::create([
            'name' => 'Other tenant fabric',
            'is_active' => true,
            'created_by' => $companyB->id,
            'creator_id' => $companyB->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.specifications.store'), [
                'name' => 'Cotton Poplin',
                'code' => 'POP-001',
                'yarn_type' => 'Ring Spun',
                'yarn_count' => '30/1',
                'denier' => '120',
                'blend' => '100% Cotton',
                'mill' => 'Apex Mills',
                'brand' => 'Apex',
                'net_weight' => '25 kg',
                'gross_weight' => '26 kg',
                'moisture' => '8%',
                'quality_grade' => 'A',
                'yarn_cost' => '120.50',
                'composition' => '100% Cotton',
                'width' => '58 in',
                'gsm' => '120',
            ])
            ->assertRedirect();

        $ownSpecificationId = TextileSpecification::query()->where('name', 'Cotton Poplin')->value('id');
        $otherSpecificationId = TextileSpecification::query()->where('name', 'Other tenant fabric')->value('id');

        $this->actingAs($companyA)
            ->post(route('textile.specifications.update'), [
                'specification_id' => $ownSpecificationId,
                'name' => 'Cotton Poplin Updated',
                'code' => 'POP-002',
                'yarn_type' => 'Ring Spun',
                'yarn_count' => '32/1',
                'denier' => '130',
                'blend' => '100% Cotton',
                'mill' => 'Apex Mills',
                'brand' => 'Apex',
                'net_weight' => '26 kg',
                'gross_weight' => '27 kg',
                'moisture' => '7%',
                'quality_grade' => 'A+',
                'yarn_cost' => '125.75',
                'composition' => '100% Cotton',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('textile_specifications', [
            'id' => $ownSpecificationId,
            'name' => 'Cotton Poplin Updated',
            'code' => 'POP-002',
            'yarn_type' => 'Ring Spun',
            'yarn_count' => '32/1',
            'denier' => '130',
            'blend' => '100% Cotton',
            'mill' => 'Apex Mills',
            'brand' => 'Apex',
            'net_weight' => '26 kg',
            'gross_weight' => '27 kg',
            'moisture' => '7%',
            'quality_grade' => 'A+',
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.specifications.update'), [
                'specification_id' => $otherSpecificationId,
                'name' => 'Should Not Update',
            ])
            ->assertNotFound();

        $this->actingAs($companyA)
            ->post(route('textile.specifications.archive'), [
                'specification_id' => $ownSpecificationId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('textile_specifications', [
            'id' => $ownSpecificationId,
            'is_active' => false,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.specifications.archive'), [
                'specification_id' => $otherSpecificationId,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('textile_specifications', [
            'name' => 'Cotton Poplin Updated',
            'created_by' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->get(route('textile.specifications.index'))
            ->assertOk()
            ->assertDontSee('Cotton Poplin Updated')
            ->assertDontSee('Other tenant fabric');
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Test Plan',
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