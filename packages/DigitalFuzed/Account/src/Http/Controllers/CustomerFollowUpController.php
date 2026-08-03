<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerContact;
use Workdo\Account\Models\CustomerFollowUp;

class CustomerFollowUpController extends Controller
{
    private const CHANNELS = ['call', 'email', 'meeting', 'whatsapp'];
    private const STATUSES = ['pending', 'done', 'cancelled'];

    public function index()
    {
        if (!Auth::user()->can('manage-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/CustomerFollowUps/Index', [
            'followUps' => CustomerFollowUp::query()
                ->with(['customer:id,company_name', 'contact:id,name'])
                ->where('created_by', creatorId())
                ->latest('id')
                ->get(),
            'customers' => Customer::query()
                ->where('created_by', creatorId())
                ->select('id', 'company_name')
                ->orderBy('company_name')
                ->get(),
            'contacts' => CustomerContact::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->select('id', 'customer_id', 'name')
                ->orderBy('name')
                ->get(),
            'channelOptions' => self::CHANNELS,
            'statusOptions' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('create-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'customer_contact_id' => ['nullable', 'integer', 'min:1'],
            'follow_up_date' => ['required', 'date'],
            'next_follow_up_date' => ['nullable', 'date'],
            'channel' => ['required', 'string', 'in:'.implode(',', self::CHANNELS)],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerId = $this->resolveCustomerId((int) $validated['customer_id']);
        $contactId = $this->resolveContactId($validated['customer_contact_id'] ?? null, $customerId);

        if (!$customerId) {
            return back()->withErrors(['customer_id' => __('Selected customer is not available in this company.')]);
        }

        if (($validated['customer_contact_id'] ?? null) && !$contactId) {
            return back()->withErrors(['customer_contact_id' => __('Selected contact is not available for this customer.')]);
        }

        CustomerFollowUp::create([
            'customer_id' => $customerId,
            'customer_contact_id' => $contactId,
            'follow_up_date' => $validated['follow_up_date'],
            'next_follow_up_date' => $validated['next_follow_up_date'] ?? null,
            'channel' => $validated['channel'],
            'status' => $validated['status'] ?? 'pending',
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Customer follow up created successfully.'));
    }

    public function update(Request $request, CustomerFollowUp $customerFollowUp)
    {
        if (!Auth::user()->can('edit-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerFollowUp->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'customer_contact_id' => ['nullable', 'integer', 'min:1'],
            'follow_up_date' => ['required', 'date'],
            'next_follow_up_date' => ['nullable', 'date'],
            'channel' => ['required', 'string', 'in:'.implode(',', self::CHANNELS)],
            'status' => ['required', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $contactId = $this->resolveContactId($validated['customer_contact_id'] ?? null, (int) $customerFollowUp->customer_id);

        if (($validated['customer_contact_id'] ?? null) && !$contactId) {
            return back()->withErrors(['customer_contact_id' => __('Selected contact is not available for this customer.')]);
        }

        $customerFollowUp->update([
            'customer_contact_id' => $contactId,
            'follow_up_date' => $validated['follow_up_date'],
            'next_follow_up_date' => $validated['next_follow_up_date'] ?? null,
            'channel' => $validated['channel'],
            'status' => $validated['status'],
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
        ]);

        return back()->with('success', __('Customer follow up updated successfully.'));
    }

    public function destroy(CustomerFollowUp $customerFollowUp)
    {
        if (!Auth::user()->can('delete-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerFollowUp->created_by === (int) creatorId(), 403);

        $customerFollowUp->delete();

        return back()->with('success', __('Customer follow up deleted successfully.'));
    }

    private function resolveCustomerId(int $customerId): ?int
    {
        return Customer::query()
            ->where('id', $customerId)
            ->where('created_by', creatorId())
            ->value('id');
    }

    private function resolveContactId($contactId, ?int $customerId): ?int
    {
        if (!$contactId || !$customerId) {
            return null;
        }

        return CustomerContact::query()
            ->where('id', (int) $contactId)
            ->where('customer_id', $customerId)
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->value('id');
    }
}
