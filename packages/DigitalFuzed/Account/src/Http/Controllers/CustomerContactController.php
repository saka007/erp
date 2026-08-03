<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerContact;

class CustomerContactController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/CustomerContacts/Index', [
            'contacts' => CustomerContact::query()
                ->with(['customer:id,company_name'])
                ->where('created_by', creatorId())
                ->latest('id')
                ->get(),
            'customers' => Customer::query()
                ->where('created_by', creatorId())
                ->select('id', 'company_name')
                ->orderBy('company_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('create-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customerId = $this->resolveCustomerId((int) $validated['customer_id']);

        if (!$customerId) {
            return back()->withErrors(['customer_id' => __('Selected customer is not available in this company.')]);
        }

        CustomerContact::create([
            'customer_id' => $customerId,
            'name' => trim((string) $validated['name']),
            'email' => isset($validated['email']) ? trim((string) $validated['email']) : null,
            'mobile' => isset($validated['mobile']) ? trim((string) $validated['mobile']) : null,
            'designation' => isset($validated['designation']) ? trim((string) $validated['designation']) : null,
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Customer contact created successfully.'));
    }

    public function update(Request $request, CustomerContact $customerContact)
    {
        if (!Auth::user()->can('edit-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerContact->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customerContact->update([
            'name' => trim((string) $validated['name']),
            'email' => isset($validated['email']) ? trim((string) $validated['email']) : null,
            'mobile' => isset($validated['mobile']) ? trim((string) $validated['mobile']) : null,
            'designation' => isset($validated['designation']) ? trim((string) $validated['designation']) : null,
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('Customer contact updated successfully.'));
    }

    public function destroy(CustomerContact $customerContact)
    {
        if (!Auth::user()->can('delete-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerContact->created_by === (int) creatorId(), 403);

        $customerContact->delete();

        return back()->with('success', __('Customer contact deleted successfully.'));
    }

    private function resolveCustomerId(int $customerId): ?int
    {
        return Customer::query()
            ->where('id', $customerId)
            ->where('created_by', creatorId())
            ->value('id');
    }
}
