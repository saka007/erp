<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Hrm\Models\Branch;

class TextilePartyAdminTest extends TestCase
{
    use RefreshDatabase;

    private function enableTextileModule(): void
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
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Party Admin Plan',
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
        UserActiveModule::create(['user_id' => $company->id, 'module' => 'Account']);

        return $company;
    }

    private function makeVendor(User $company, string $name, string $supplierType): Vendor
    {
        return Vendor::create([
            'company_name' => $name,
            'contact_person_name' => 'Supplier Contact',
            'supplier_type' => $supplierType,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
    }

    private function makeCustomer(User $company, string $name): Customer
    {
        return Customer::create([
            'company_name' => $name,
            'contact_person_name' => 'Buyer Contact',
            'contact_person_email' => 'buyer@example.test',
            'operating_model' => 'full_package_buyer',
            'material_ownership' => 'company_owned',
            'billing_mode' => 'sale_value',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
    }

    public function test_parties_page_lists_all_party_types(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $this->makeVendor($company, 'Yarn Traders', 'yarn');
        $this->makeVendor($company, 'Sizing Works', 'sizing');
        $this->makeVendor($company, 'Powerloom House', 'powerloom');
        $this->makeCustomer($company, 'Fabric Buyers Co');

        $this->actingAs($company)
            ->get(route('textile.parties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 4)
                ->has('categoryOptions', 6)
                ->where('selectedCategory', 'all')
                ->where('parties.0.party_type', 'supplier')
                ->where('parties.0.supplier_type', 'powerloom')
                ->where('parties.0.party_name', 'Powerloom House'));
    }

    public function test_parties_page_filters_by_category(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $this->makeVendor($company, 'Yarn Traders', 'yarn');
        $this->makeVendor($company, 'Sizing Works', 'sizing');
        $this->makeCustomer($company, 'Fabric Buyers Co');

        $this->actingAs($company)
            ->get(route('textile.parties.index', ['category' => 'sizing']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('selectedCategory', 'sizing')
                ->where('parties.0.party_name', 'Sizing Works')
                ->where('parties.0.supplier_type', 'sizing'));

        $this->actingAs($company)
            ->get(route('textile.parties.index', ['category' => 'customer']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('selectedCategory', 'customer')
                ->where('parties.0.party_type', 'buyer')
                ->where('parties.0.party_name', 'Fabric Buyers Co'));

        $this->actingAs($company)
            ->get(route('textile.parties.index', ['category' => 'yarn']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('parties.0.party_name', 'Yarn Traders'));
    }

    public function test_parties_page_filters_by_search(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $this->makeVendor($company, 'Yarn Traders', 'yarn');
        $this->makeVendor($company, 'Sizing Works', 'sizing');

        $this->actingAs($company)
            ->get(route('textile.parties.index', ['search' => 'Yarn']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('parties.0.party_name', 'Yarn Traders'));
    }

    public function test_parties_page_scopes_to_session_branch_automatically(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $mainOffice = Branch::create(['branch_name' => 'Main Office', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $branchB = Branch::create(['branch_name' => 'Branch B', 'creator_id' => $company->id, 'created_by' => $company->id]);

        $mainVendor = $this->makeVendor($company, 'Main Office Yarn', 'yarn');
        $mainVendor->update(['branch_id' => $mainOffice->id]);
        $branchBVendor = $this->makeVendor($company, 'Branch B Yarn', 'yarn');
        $branchBVendor->update(['branch_id' => $branchB->id]);

        $this->assertSame(2, Vendor::count());
        $this->assertSame(2, Vendor::query()->where('created_by', $company->id)->count());

        // Company users are always scoped to the tenant's first branch (auto-set
        // in session by the Inertia middleware when none is chosen) — Branch B
        // sorts first alphabetically, so only its parties are listed.
        $this->actingAs($company)
            ->get(route('textile.parties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('parties.0.party_name', 'Branch B Yarn'));

        // Choosing a different branch in the header dropdown (session) switches
        // the party scope to that branch only.
        $this->actingAs($company)
            ->withSession(['active_branch_id' => $mainOffice->id])
            ->get(route('textile.parties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('parties.0.party_name', 'Main Office Yarn'));

        $this->actingAs($company)
            ->withSession(['active_branch_id' => $branchB->id])
            ->get(route('textile.parties.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('parties.0.party_name', 'Branch B Yarn'));
    }

    public function test_parties_page_includes_credit_fields(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $vendor = $this->makeVendor($company, 'Credit Sizing Vendor', 'sizing');
        $vendor->update([
            'credit_enabled' => true,
            'credit_days' => 30,
            'credit_limit' => 100000,
            'reminder_enabled' => true,
        ]);

        $this->actingAs($company)
            ->get(route('textile.parties.index', ['category' => 'sizing']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('parties', 1)
                ->where('parties.0.credit_enabled', true)
                ->where('parties.0.credit_days', 30)
                ->where('parties.0.credit_limit', 100000)
                ->where('parties.0.reminder_enabled', true));
    }

    public function test_parties_page_rejects_non_company_user(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $staff = User::factory()->create([
            'type' => 'user',
            'created_by' => $company->id,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('textile.parties.index'))
            ->assertForbidden();
    }
}
