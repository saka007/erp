<?php

namespace Tests\Feature\Textile;

use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileIndustryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_assign_and_remove_textile_access_for_a_company(): void
    {
        $superadmin = User::factory()->create([
            'type' => 'superadmin',
            'email_verified_at' => now(),
        ]);
        $superadminRole = Role::create([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'label' => 'Super Admin',
            'created_by' => $superadmin->id,
        ]);
        $superadmin->assignRole($superadminRole);

        $company = User::factory()->create([
            'type' => 'company',
            'created_by' => $superadmin->id,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->put(route('users.industry.update', $company), ['industry_type' => 'textile'])
            ->assertRedirect();

        $this->assertDatabaseHas('user_active_modules', [
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);
        $this->assertDatabaseHas('user_active_modules', [
            'user_id' => $company->id,
            'module' => 'TextileInventory',
        ]);

        $this->actingAs($superadmin)
            ->put(route('users.industry.update', $company), ['industry_type' => 'standard'])
            ->assertRedirect();

        $this->assertSame(0, UserActiveModule::where('user_id', $company->id)
            ->whereIn('module', ['TextileCore', 'TextileInventory'])
            ->count());
    }
}