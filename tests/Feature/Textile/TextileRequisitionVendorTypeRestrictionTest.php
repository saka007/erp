<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Http\Controllers\TextileProcurementController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;

class TextileRequisitionVendorTypeRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_yarn_requisition_accepts_yarn_vendor(): void
    {
        $company = $this->enableTextileModule();
        $vendor = $this->makeVendor($company, 'Shree Yarn Traders', 'yarn');

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'requisition_type' => 'yarn',
                'quantity' => 100,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_yarn_requisition_rejects_chemical_vendor(): void
    {
        $company = $this->enableTextileModule();
        $vendor = $this->makeVendor($company, 'Chem Corp', 'chemical');

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'requisition_type' => 'yarn',
                'quantity' => 100,
                'unit' => 'kg',
            ])
            ->assertSessionHasErrors('party_name');
    }

    public function test_chemical_requisition_rejects_yarn_vendor(): void
    {
        $company = $this->enableTextileModule();
        $vendor = $this->makeVendor($company, 'Shree Yarn Traders', 'yarn');

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'requisition_type' => 'chemical',
                'quantity' => 50,
                'unit' => 'kg',
            ])
            ->assertSessionHasErrors('party_name');
    }

    public function test_grey_fabric_requisition_accepts_powerloom_vendor(): void
    {
        $company = $this->enableTextileModule();
        $vendor = $this->makeVendor($company, 'Loom House', 'powerloom');

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'requisition_type' => 'grey_fabric',
                'quantity' => 200,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_general_requisition_accepts_any_vendor(): void
    {
        $company = $this->enableTextileModule();
        $vendor = $this->makeVendor($company, 'Chem Corp', 'chemical');

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'requisition_type' => 'general',
                'quantity' => 30,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_vendor_without_type_is_rejected_when_type_is_restricted(): void
    {
        $company = $this->enableTextileModule();
        $vendor = $this->makeVendor($company, 'No Type Traders', null);

        $this->actingAs($company)
            ->post(route('textile.procurement.requisitions.store'), [
                'party_name' => $vendor->company_name,
                'vendor_id' => $vendor->id,
                'requisition_type' => 'yarn',
                'quantity' => 10,
                'unit' => 'kg',
            ])
            ->assertSessionHasErrors('party_name');
    }

    public function test_mapping_covers_all_requisition_types(): void
    {
        $requisitionTypes = ['yarn', 'beam', 'grey_fabric', 'finished_fabric', 'chemical', 'packing_material', 'spare_part', 'service', 'general'];

        foreach ($requisitionTypes as $requisitionType) {
            $this->assertArrayHasKey($requisitionType, TextileProcurementController::REQUISITION_SUPPLIER_TYPE_MAP);
        }
    }

    private function makeVendor(User $company, string $name, ?string $supplierType): Vendor
    {
        return Vendor::create([
            'company_name' => $name,
            'supplier_type' => $supplierType,
            'contact_person_name' => 'Test Person',
            'contact_person_email' => 'test@example.test',
            'credit_limit' => 100000,
            'credit_days' => 30,
            'credit_enabled' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
    }

    private function enableTextileModule(): User
    {
        AddOn::firstOrCreate(
            ['module' => 'TextileCore'],
            ['name' => 'Textile Core', 'package_name' => 'textile-core', 'is_enable' => true, 'monthly_price' => 0, 'yearly_price' => 0]
        );

        $company = $this->company();

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        return $company;
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Textile Requisition Type Plan',
            'modules' => ['TextileCore', 'Account'],
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

        return $company;
    }
}
