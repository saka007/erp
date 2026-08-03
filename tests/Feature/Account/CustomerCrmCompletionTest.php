<?php

namespace Tests\Feature\Account;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerContact;
use Workdo\Account\Models\CustomerDocument;
use Workdo\Account\Models\CustomerFollowUp;

class CustomerCrmCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_contacts_followups_documents_and_credit_limits(): void
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
        UserActiveModule::create(['user_id' => $company->id, 'module' => 'Account']);

        $customer = Customer::create([
            'company_name' => 'Blue Loom Exports',
            'contact_person_name' => 'Riya Shah',
            'contact_person_email' => 'riya@blueloom.test',
            'same_as_billing' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);

        $this->actingAs($company)
            ->put(route('account.customers.update', $customer->id), [
                'company_name' => 'Blue Loom Exports',
                'contact_person_name' => 'Riya Shah',
                'contact_person_email' => 'riya@blueloom.test',
                'contact_person_mobile' => '+919911223344',
                'tax_number' => 'GST-BLE-101',
                'payment_terms' => 'Net 30',
                'credit_limit' => 250000,
                'currency_code' => 'INR',
                'operating_model' => 'full_package_buyer',
                'material_ownership' => 'company_owned',
                'billing_mode' => 'sale_value',
                'billing_address' => [
                    'name' => 'Blue Loom Billing',
                    'address_line_1' => 'Textile Park A',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395003',
                ],
                'shipping_address' => [
                    'name' => 'Blue Loom Billing',
                    'address_line_1' => 'Textile Park A',
                    'address_line_2' => '',
                    'city' => 'Surat',
                    'state' => 'Gujarat',
                    'country' => 'India',
                    'zip_code' => '395003',
                ],
                'same_as_billing' => true,
                'notes' => 'credit profile update',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertEquals(250000.00, (float) $customer->credit_limit);
        $this->assertSame('INR', $customer->currency_code);

        $this->actingAs($company)
            ->post(route('account.customer-contacts.store'), [
                'customer_id' => $customer->id,
                'name' => 'Nikhil Patel',
                'email' => 'nikhil@blueloom.test',
                'mobile' => '+918881112222',
                'designation' => 'Purchase Manager',
                'is_primary' => true,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $contact = CustomerContact::query()->where('created_by', $company->id)->first();
        $this->assertNotNull($contact);

        $this->actingAs($company)
            ->post(route('account.customer-follow-ups.store'), [
                'customer_id' => $customer->id,
                'customer_contact_id' => $contact->id,
                'follow_up_date' => now()->toDateString(),
                'next_follow_up_date' => now()->addDays(3)->toDateString(),
                'channel' => 'call',
                'status' => 'pending',
                'notes' => 'Discuss revised MOQ',
            ])
            ->assertSessionHasNoErrors();

        $followUp = CustomerFollowUp::query()->where('created_by', $company->id)->first();
        $this->assertNotNull($followUp);

        $this->actingAs($company)
            ->post(route('account.customer-documents.store'), [
                'customer_id' => $customer->id,
                'document_name' => 'GST Certificate',
                'document_type' => 'gst',
                'document_reference' => 'GST-12345',
                'status' => 'active',
                'issue_date' => now()->subMonth()->toDateString(),
                'expiry_date' => now()->addYear()->toDateString(),
                'notes' => 'validated by finance',
            ])
            ->assertSessionHasNoErrors();

        $document = CustomerDocument::query()->where('created_by', $company->id)->first();
        $this->assertNotNull($document);

        $this->actingAs($company)
            ->put(route('account.customer-follow-ups.update', $followUp->id), [
                'customer_contact_id' => $contact->id,
                'follow_up_date' => now()->toDateString(),
                'next_follow_up_date' => now()->addDays(5)->toDateString(),
                'channel' => 'meeting',
                'status' => 'done',
                'notes' => 'Closed with visit',
            ])
            ->assertSessionHasNoErrors();

        $followUp->refresh();
        $this->assertSame('done', $followUp->status);

        $this->actingAs($company)
            ->delete(route('account.customer-documents.destroy', $document->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('account_customer_documents', ['id' => $document->id]);
    }

    private function company(): User
    {
        $plan = Plan::create([
            'name' => 'Customer CRM Completion Plan',
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

        foreach (['manage-customers', 'manage-any-customers', 'create-customers', 'edit-customers', 'delete-customers'] as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'module' => 'customers',
                'label' => $permissionName,
                'add_on' => 'Account',
            ]);

            $role->givePermissionTo($permission);
        }

        $company->assignRole($role);

        return $company;
    }
}
