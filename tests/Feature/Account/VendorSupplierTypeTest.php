<?php

namespace Tests\Feature\Account;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;

class VendorSupplierTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_crud_supports_supplier_types_and_filtering(): void
    {
        AddOn::create([
            'module' => 'Account',
            'name' => 'Account',
            'package_name' => 'account',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $company = $this->company();

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'Account',
        ]);

        $this->actingAs($company)
            ->post(route('account.vendors.store'), [
                'supplier_type' => 'yarn',
                'company_name' => 'Sunrise Yarns',
                'contact_person_name' => 'Amit Shah',
                'contact_person_email' => 'amit@example.test',
                'contact_person_mobile' => '9999999999',
                'tax_number' => 'TAX-Y-001',
                'payment_terms' => 'Net 30',
                'billing_address' => [
                    'name' => 'Sunrise Yarns Billing',
                    'address_line_1' => 'Line 1',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395001',
                ],
                'shipping_address' => [
                    'name' => 'Sunrise Yarns Shipping',
                    'address_line_1' => 'Line 1',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395001',
                ],
                'same_as_billing' => false,
                'notes' => 'Yarn supplier',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $vendor = Vendor::query()->where('created_by', $company->id)->first();
        $this->assertNotNull($vendor);
        $this->assertSame('yarn', $vendor->supplier_type);

        $this->actingAs($company)
            ->put(route('account.vendors.update', $vendor->id), [
                'supplier_type' => 'chemical',
                'company_name' => 'Sunrise Yarns',
                'contact_person_name' => 'Amit Shah',
                'contact_person_email' => 'amit@example.test',
                'contact_person_mobile' => '9999999999',
                'tax_number' => 'TAX-C-001',
                'payment_terms' => 'Net 45',
                'billing_address' => [
                    'name' => 'Sunrise Yarns Billing',
                    'address_line_1' => 'Line 1',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395001',
                ],
                'shipping_address' => [
                    'name' => 'Sunrise Yarns Shipping',
                    'address_line_1' => 'Line 1',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395001',
                ],
                'same_as_billing' => false,
                'notes' => 'Chemical supplier',
            ])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame('chemical', $vendor->supplier_type);

        $this->actingAs($company)
            ->get(route('account.vendors.index', ['supplier_type' => 'chemical']))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->where('vendors.data.0.supplier_type', 'chemical');

                $this->assertSame('chemical', $page->toArray()['props']['vendors']['data'][0]['supplier_type'] ?? null);
            });
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Vendor Supplier Type Plan',
            'modules' => ['Account'],
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

        foreach (['manage-vendors', 'manage-any-vendors', 'create-vendors', 'edit-vendors', 'delete-vendors'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'vendors',
                'label' => $permissionName,
                'add_on' => 'Account',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);

        return $company;
    }
}
