<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;

class TextileCostingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_capture_costing_and_finalize_margin_with_tenant_isolation(): void
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

        $sourceA = TextileWorkflowDocument::create([
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
            'document_type' => 'job_work_inward',
            'document_number' => 'SRC-A-0001',
            'lot_reference' => 'LOT-C-1',
            'quantity' => 100,
            'unit' => 'mtr',
            'status' => 'approved',
        ]);

        TextileWorkflowDocument::create([
            'created_by' => $companyB->id,
            'creator_id' => $companyB->id,
            'document_type' => 'job_work_inward',
            'document_number' => 'SRC-B-0001',
            'lot_reference' => 'LOT-C-B',
            'quantity' => 50,
            'unit' => 'mtr',
            'status' => 'approved',
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.costing.entries.store'), [
                'source_document_id' => $sourceA->id,
                'party_name' => 'Buyer Alpha',
                'lot_reference' => 'LOT-C-1',
                'quantity' => 100,
                'unit' => 'mtr',
                'material_cost' => 1000,
                'conversion_cost' => 200,
                'overhead_cost' => 100,
                'variance_value' => 50,
                'revenue_value' => 1800,
                'notes' => 'Costed for order margin review',
            ])
            ->assertSessionHasNoErrors();

        $entry = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'costing_entry')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('draft', $entry->status);

        $this->actingAs($companyA)
            ->post(route('textile.costing.entries.finalize'), [
                'costing_entry_id' => $entry->id,
            ])
            ->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame('approved', $entry->status);
        $this->assertSame(1350.0, (float) ($entry->metadata['total_cost'] ?? 0));
        $this->assertSame(450.0, (float) ($entry->metadata['margin_value'] ?? 0));

        $snapshot = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'margin_snapshot')
            ->latest('id')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame('approved', $snapshot->status);
        $this->assertSame(25.0, (float) ($snapshot->metadata['margin_percent'] ?? -1));

        $this->actingAs($companyB)
            ->get(route('textile.costing.index'))
            ->assertOk()
            ->assertDontSee('LOT-C-1')
            ->assertDontSee('Buyer Alpha');

        $this->actingAs($companyA)
            ->get(route('textile.costing.index'))
            ->assertOk()
            ->assertSee('LOT-C-1')
            ->assertSee('Buyer Alpha')
            ->assertDontSee('LOT-C-B');
    }

    public function test_customer_owned_profile_uses_conversion_only_costing_mode(): void
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company(['TextileCore', 'Account']);

        Customer::create([
            'company_name' => 'Powerloom Works',
            'contact_person_name' => 'Operator One',
            'contact_person_email' => 'ops@powerloom.test',
            'contact_person_mobile' => '9999999999',
            'operating_model' => 'jobwork_weaving_beam_supplied',
            'material_ownership' => 'customer_owned',
            'billing_mode' => 'conversion_charge',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $source = TextileWorkflowDocument::create([
            'created_by' => $company->id,
            'creator_id' => $company->id,
            'document_type' => 'weaving_output',
            'document_number' => 'SRC-CUS-0001',
            'party_name' => 'Powerloom Works',
            'lot_reference' => 'LOT-CUS-1',
            'quantity' => 120,
            'unit' => 'mtr',
            'status' => 'approved',
        ]);

        $this->actingAs($company)
            ->post(route('textile.costing.entries.store'), [
                'source_document_id' => $source->id,
                'party_name' => 'Powerloom Works',
                'lot_reference' => 'LOT-CUS-1',
                'quantity' => 120,
                'unit' => 'mtr',
                'material_cost' => 900,
                'conversion_cost' => 300,
                'overhead_cost' => 120,
                'variance_value' => 30,
                'revenue_value' => 1600,
            ])
            ->assertSessionHasNoErrors();

        $entry = TextileWorkflowDocument::query()
            ->where('created_by', $company->id)
            ->where('document_type', 'costing_entry')
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(0.0, (float) ($entry->metadata['material_cost'] ?? -1));
        $this->assertSame(900.0, (float) ($entry->metadata['entered_material_cost'] ?? -1));
        $this->assertSame('conversion_only', (string) ($entry->metadata['costing_mode'] ?? ''));

        $this->actingAs($company)
            ->post(route('textile.costing.entries.finalize'), [
                'costing_entry_id' => $entry->id,
            ])
            ->assertSessionHasNoErrors();

        $entry->refresh();
        $this->assertSame(450.0, (float) ($entry->metadata['total_cost'] ?? 0));
        $this->assertSame(1150.0, (float) ($entry->metadata['margin_value'] ?? 0));
    }

    private function company(array $modules = ['TextileCore']): User
    {
        $plan = Plan::create([
            'name' => 'Textile Costing Plan',
            'modules' => $modules,
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

        foreach ($modules as $module) {
            UserActiveModule::create([
                'user_id' => $company->id,
                'module' => $module,
            ]);
        }

        return $company;
    }
}
