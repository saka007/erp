<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileOperatingProfile;
use DigitalFuzed\TextileCore\Models\TextileOperatingPolicy;
use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileOperatingPolicyAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_set_company_policy_and_capabilities_gate_modules(): void
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
        $superadmin = User::factory()->create([
            'type' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->post(route('textile.operating-policy.update'), [
                'company_id' => $company->id,
                'operating_model' => TextileOperatingPolicyService::MODEL_JOBWORK_WEAVING,
                'operating_profiles' => [TextileOperatingPolicyService::MODEL_JOBWORK_WEAVING],
                'material_ownership' => 'customer_owned',
                'billing_mode' => 'conversion_charge',
                'settings' => [
                    TextileOperatingPolicyService::SETTING_HAS_OWN_LOOMS,
                    TextileOperatingPolicyService::SETTING_HAS_WEAVING_PRODUCTION,
                ],
            ])
            ->assertSessionHasNoErrors();

        $policy = TextileOperatingPolicy::query()->where('created_by', $company->id)->first();
        $this->assertNotNull($policy);
        $this->assertSame(TextileOperatingPolicyService::MODEL_JOBWORK_WEAVING, $policy->operating_model);
        $this->assertTrue((bool) ($policy->settings[TextileOperatingPolicyService::SETTING_HAS_OWN_LOOMS] ?? false));
        $this->assertFalse((bool) ($policy->settings[TextileOperatingPolicyService::SETTING_HAS_SIZING] ?? false));

        $this->actingAs($company)
            ->post(route('textile.manufacturing.loom-masters.store'), [
                'source_reference_type' => 'factory',
                'source_reference_id' => 9001,
                'source_action' => 'loom_register',
                'party_name' => 'Policy Loom A',
                'lot_reference' => 'rapier',
                'quantity' => 500,
                'unit' => 'rpm',
                'shed_type' => 'open',
                'width' => 108,
                'loom_status' => 'running',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($company)
            ->post(route('textile.manufacturing.sizing-recipes.store'), [
                'warp_production_id' => 1,
            ])
            ->assertSessionHasErrors('warp_production_id');

        $this->actingAs($company)
            ->get(route('textile.procurement.index'))
            ->assertForbidden();

        $this->actingAs($company)
            ->get(route('textile.sales.index'))
            ->assertForbidden();

        $this->actingAs($company)
            ->get(route('textile.processing.index'))
            ->assertForbidden();

        $this->actingAs($company)
            ->get(route('textile.quality.index'))
            ->assertForbidden();

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => 'Blocked Supplier',
                'lot_reference' => 'LOT-BLOCK-1',
                'quantity' => 10,
                'unit' => 'kg',
            ])
            ->assertSessionHasErrors('party_name');
    }

    public function test_company_can_manage_multiple_operating_profiles_with_history(): void
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

        $this->actingAs($company)
            ->post(route('textile.operating-policy.update'), [
                'operating_model' => TextileOperatingPolicyService::MODEL_TRADER_BULK,
                'operating_profiles' => [
                    TextileOperatingPolicyService::MODEL_TRADER_BULK,
                    TextileOperatingPolicyService::MODEL_EXPORT_COMPLIANCE,
                ],
                'material_ownership' => 'mixed',
                'billing_mode' => 'hybrid',
                'settings' => [
                    TextileOperatingPolicyService::SETTING_HAS_JOBWORK_PROCESSING,
                ],
            ])
            ->assertSessionHasNoErrors();

        $activeProfiles = TextileOperatingProfile::query()
            ->where('created_by', $company->id)
            ->where('is_active', true)
            ->whereNull('effective_to')
            ->pluck('profile_key')
            ->toArray();

        $this->assertEqualsCanonicalizing([
            TextileOperatingPolicyService::MODEL_TRADER_BULK,
            TextileOperatingPolicyService::MODEL_EXPORT_COMPLIANCE,
        ], $activeProfiles);

        $this->actingAs($company)
            ->post(route('textile.operating-policy.update'), [
                'operating_model' => TextileOperatingPolicyService::MODEL_EXPORT_COMPLIANCE,
                'operating_profiles' => [
                    TextileOperatingPolicyService::MODEL_EXPORT_COMPLIANCE,
                ],
                'material_ownership' => 'mixed',
                'billing_mode' => 'hybrid',
                'settings' => [
                    TextileOperatingPolicyService::SETTING_HAS_JOBWORK_PROCESSING,
                    TextileOperatingPolicyService::SETTING_HAS_SHIFT_PLANNING,
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('textile_operating_profiles', [
            'created_by' => $company->id,
            'profile_key' => TextileOperatingPolicyService::MODEL_TRADER_BULK,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('textile_operating_profiles', [
            'created_by' => $company->id,
            'profile_key' => TextileOperatingPolicyService::MODEL_EXPORT_COMPLIANCE,
            'is_active' => true,
            'effective_to' => null,
        ]);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Policy Plan',
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
