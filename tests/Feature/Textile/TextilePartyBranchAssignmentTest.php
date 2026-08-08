<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextilePartyBranchAssignment;
use DigitalFuzed\TextileCore\Services\TextilePartyBranchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Hrm\Models\Branch;

class TextilePartyBranchAssignmentTest extends TestCase
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
    }

    private function makeVendor(User $company, string $name = 'Yarn Supplier Co'): Vendor
    {
        return Vendor::create([
            'company_name' => $name,
            'contact_person_name' => 'Supplier Contact',
            'supplier_type' => 'yarn',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
    }

    private function makeCustomer(User $company, string $name = 'Fabric Buyer Co'): Customer
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

    public function test_party_with_no_assignments_is_visible_in_all_branches(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $branchA = Branch::create(['branch_name' => 'Main Office', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $branchB = Branch::create(['branch_name' => 'Branch B', 'creator_id' => $company->id, 'created_by' => $company->id]);

        $vendor = $this->makeVendor($company, 'Global Yarn Co');

        $this->assertCount(0, TextilePartyBranchAssignment::query()->get());

        $this->assertTrue(TextilePartyBranchService::partyVisibleToBranch(
            TextilePartyBranchService::PARTY_VENDOR,
            (int) $vendor->id,
            (int) $branchA->id,
            (int) $company->id
        ));
        $this->assertTrue(TextilePartyBranchService::partyVisibleToBranch(
            TextilePartyBranchService::PARTY_VENDOR,
            (int) $vendor->id,
            (int) $branchB->id,
            (int) $company->id
        ));
    }

    public function test_assigned_party_is_only_visible_in_assigned_branch(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $branchA = Branch::create(['branch_name' => 'Main Office', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $branchB = Branch::create(['branch_name' => 'Branch B', 'creator_id' => $company->id, 'created_by' => $company->id]);

        $vendor = $this->makeVendor($company, 'Restricted Yarn Co');

        TextilePartyBranchService::assignToBranches(
            TextilePartyBranchService::PARTY_VENDOR,
            [(int) $vendor->id],
            [(int) $branchA->id],
            (int) $company->id,
            (int) $company->id
        );

        $this->assertTrue(TextilePartyBranchService::partyVisibleToBranch(
            TextilePartyBranchService::PARTY_VENDOR,
            (int) $vendor->id,
            (int) $branchA->id,
            (int) $company->id
        ));
        $this->assertFalse(TextilePartyBranchService::partyVisibleToBranch(
            TextilePartyBranchService::PARTY_VENDOR,
            (int) $vendor->id,
            (int) $branchB->id,
            (int) $company->id
        ));
    }

    public function test_query_scope_filters_dropdown_by_active_branch(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $branchA = Branch::create(['branch_name' => 'Main Office', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $branchB = Branch::create(['branch_name' => 'Branch B', 'creator_id' => $company->id, 'created_by' => $company->id]);

        $globalVendor = $this->makeVendor($company, 'Global Yarn Co');
        $restrictedVendor = $this->makeVendor($company, 'Restricted Yarn Co');

        TextilePartyBranchService::assignToBranches(
            TextilePartyBranchService::PARTY_VENDOR,
            [(int) $restrictedVendor->id],
            [(int) $branchA->id],
            (int) $company->id,
            (int) $company->id
        );

        // Branch B active → only the global vendor is visible.
        $idsInBranchB = TextilePartyBranchService::visiblePartyIds(
            TextilePartyBranchService::PARTY_VENDOR,
            'vendors',
            (int) $branchB->id,
            (int) $company->id
        );
        $this->assertContains((int) $globalVendor->id, $idsInBranchB);
        $this->assertNotContains((int) $restrictedVendor->id, $idsInBranchB);

        // Branch A active → both visible.
        $idsInBranchA = TextilePartyBranchService::visiblePartyIds(
            TextilePartyBranchService::PARTY_VENDOR,
            'vendors',
            (int) $branchA->id,
            (int) $company->id
        );
        $this->assertContains((int) $globalVendor->id, $idsInBranchA);
        $this->assertContains((int) $restrictedVendor->id, $idsInBranchA);
    }

    public function test_admin_can_bulk_assign_and_bulk_remove_via_routes(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        $branchA = Branch::create(['branch_name' => 'Main Office', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $branchB = Branch::create(['branch_name' => 'Branch B', 'creator_id' => $company->id, 'created_by' => $company->id]);

        $vendor1 = $this->makeVendor($company, 'Vendor One');
        $vendor2 = $this->makeVendor($company, 'Vendor Two');

        // Admin assigns both vendors to both branches.
        $this->actingAs($company)
            ->post(route('textile.party-branches.assign'), [
                'party_type' => 'vendor',
                'party_ids' => [(int) $vendor1->id, (int) $vendor2->id],
                'branch_ids' => [(int) $branchA->id, (int) $branchB->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('textile_party_branch_assignments', [
            'party_type' => 'vendor',
            'party_id' => $vendor1->id,
            'branch_id' => $branchA->id,
            'created_by' => $company->id,
        ]);
        $this->assertDatabaseHas('textile_party_branch_assignments', [
            'party_type' => 'vendor',
            'party_id' => $vendor2->id,
            'branch_id' => $branchB->id,
            'created_by' => $company->id,
        ]);

        // Admin bulk-removes vendor1 from branch A only.
        $this->actingAs($company)
            ->post(route('textile.party-branches.remove'), [
                'party_type' => 'vendor',
                'party_ids' => [(int) $vendor1->id],
                'branch_ids' => [(int) $branchA->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('textile_party_branch_assignments', [
            'party_type' => 'vendor',
            'party_id' => $vendor1->id,
            'branch_id' => $branchA->id,
            'created_by' => $company->id,
        ]);
        $this->assertDatabaseHas('textile_party_branch_assignments', [
            'party_type' => 'vendor',
            'party_id' => $vendor1->id,
            'branch_id' => $branchB->id,
            'created_by' => $company->id,
        ]);
    }

    public function test_assignments_are_isolated_between_tenants(): void
    {
        $this->enableTextileModule();

        $companyA = $this->company();
        $companyB = $this->company();

        $branchA1 = Branch::create(['branch_name' => 'A Main', 'creator_id' => $companyA->id, 'created_by' => $companyA->id]);
        $branchB1 = Branch::create(['branch_name' => 'B Main', 'creator_id' => $companyB->id, 'created_by' => $companyB->id]);

        $vendorA = $this->makeVendor($companyA, 'Tenant A Vendor');
        $vendorB = $this->makeVendor($companyB, 'Tenant B Vendor');

        TextilePartyBranchService::assignToBranches(
            TextilePartyBranchService::PARTY_VENDOR,
            [(int) $vendorA->id],
            [(int) $branchA1->id],
            (int) $companyA->id,
            (int) $companyA->id
        );

        // Tenant B sees vendor A as global (no assignment under tenant B).
        $this->assertTrue(TextilePartyBranchService::partyVisibleToBranch(
            TextilePartyBranchService::PARTY_VENDOR,
            (int) $vendorA->id,
            (int) $branchB1->id,
            (int) $companyB->id
        ));

        // Tenant B cannot read or remove tenant A's assignment rows.
        $this->assertCount(0, TextilePartyBranchService::assignedBranchIds(
            TextilePartyBranchService::PARTY_VENDOR,
            (int) $vendorA->id,
            (int) $companyB->id
        ));

        $removed = TextilePartyBranchService::removeFromBranches(
            TextilePartyBranchService::PARTY_VENDOR,
            [(int) $vendorA->id],
            [(int) $branchA1->id],
            (int) $companyB->id
        );
        $this->assertSame(0, $removed);
        $this->assertDatabaseHas('textile_party_branch_assignments', [
            'party_type' => 'vendor',
            'party_id' => $vendorA->id,
            'branch_id' => $branchA1->id,
            'created_by' => $companyA->id,
        ]);
    }

    public function test_admin_page_lists_vendors_customers_and_branches(): void
    {
        $this->enableTextileModule();

        $company = $this->company();
        Branch::create(['branch_name' => 'Main Office', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $vendor = $this->makeVendor($company, 'Listed Vendor');
        $customer = $this->makeCustomer($company, 'Listed Customer');

        $this->actingAs($company)
            ->get(route('textile.party-branches.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('vendors', 1)
                ->has('customers', 1)
                ->has('branchOptions', 1)
                ->where('vendors.0.name', 'Listed Vendor')
                ->where('customers.0.name', 'Listed Customer'));
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Party Branch Plan',
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
