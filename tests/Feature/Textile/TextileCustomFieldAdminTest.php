<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileCustomField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TextileCustomFieldAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_custom_fields_with_tenant_isolation(): void
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
            ->post(route('textile.custom-fields.store'), [
                'module_key' => 'textile_sales',
                'sub_module_key' => 'sales_order',
                'field_key' => 'customer_segment',
                'label' => 'Customer Segment',
                'field_type' => 'select',
                'options_csv' => 'A, B, C',
                'is_required' => true,
                'sort_order' => 10,
                'help_text' => 'Used for segmentation',
            ])
            ->assertSessionHasNoErrors();

        $record = TextileCustomField::query()
            ->where('created_by', $companyA->id)
            ->where('field_key', 'customer_segment')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(['A', 'B', 'C'], $record->options);

        $this->actingAs($companyA)
            ->post(route('textile.custom-fields.update'), [
                'custom_field_id' => $record->id,
                'module_key' => 'textile_sales',
                'sub_module_key' => 'sales_order',
                'field_key' => 'customer_segment',
                'label' => 'Buyer Segment',
                'field_type' => 'select',
                'options_csv' => 'A, B, C, D',
                'is_required' => false,
                'sort_order' => 20,
                'help_text' => 'Updated help text',
            ])
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('Buyer Segment', $record->label);
        $this->assertSame(['A', 'B', 'C', 'D'], $record->options);
        $this->assertFalse((bool) $record->is_required);

        $this->actingAs($companyB)
            ->get(route('textile.custom-fields.index'))
            ->assertOk()
            ->assertDontSee('Buyer Segment')
            ->assertDontSee('customer_segment');

        $this->actingAs($companyA)
            ->get(route('textile.custom-fields.index'))
            ->assertOk()
            ->assertSee('Buyer Segment')
            ->assertSee('customer_segment');

        $this->actingAs($companyA)
            ->post(route('textile.custom-fields.archive'), [
                'custom_field_id' => $record->id,
            ])
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertFalse((bool) $record->is_active);

        $this->actingAs($companyA)
            ->get(route('textile.custom-fields.index'))
            ->assertOk()
            ->assertDontSee('Buyer Segment');
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
