<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerDocument;

class CustomerDocumentController extends Controller
{
    private const DOCUMENT_TYPES = ['gst', 'pan', 'contract', 'compliance', 'other'];
    private const DOCUMENT_STATUSES = ['active', 'expired', 'revoked'];

    public function index()
    {
        if (!Auth::user()->can('manage-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/CustomerDocuments/Index', [
            'documents' => CustomerDocument::query()
                ->with(['customer:id,company_name'])
                ->where('created_by', creatorId())
                ->latest('id')
                ->get(),
            'customers' => Customer::query()
                ->where('created_by', creatorId())
                ->select('id', 'company_name')
                ->orderBy('company_name')
                ->get(),
            'documentTypes' => self::DOCUMENT_TYPES,
            'statusOptions' => self::DOCUMENT_STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('create-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'document_name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:'.implode(',', self::DOCUMENT_TYPES)],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::DOCUMENT_STATUSES)],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerId = $this->resolveCustomerId((int) $validated['customer_id']);

        if (!$customerId) {
            return back()->withErrors(['customer_id' => __('Selected customer is not available in this company.')]);
        }

        CustomerDocument::create([
            'customer_id' => $customerId,
            'document_name' => trim((string) $validated['document_name']),
            'document_type' => $validated['document_type'],
            'document_reference' => isset($validated['document_reference']) ? trim((string) $validated['document_reference']) : null,
            'status' => $validated['status'] ?? 'active',
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Customer document created successfully.'));
    }

    public function update(Request $request, CustomerDocument $customerDocument)
    {
        if (!Auth::user()->can('edit-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerDocument->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'document_name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:'.implode(',', self::DOCUMENT_TYPES)],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:'.implode(',', self::DOCUMENT_STATUSES)],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerDocument->update([
            'document_name' => trim((string) $validated['document_name']),
            'document_type' => $validated['document_type'],
            'document_reference' => isset($validated['document_reference']) ? trim((string) $validated['document_reference']) : null,
            'status' => $validated['status'],
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
        ]);

        return back()->with('success', __('Customer document updated successfully.'));
    }

    public function destroy(CustomerDocument $customerDocument)
    {
        if (!Auth::user()->can('delete-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerDocument->created_by === (int) creatorId(), 403);

        $customerDocument->delete();

        return back()->with('success', __('Customer document deleted successfully.'));
    }

    private function resolveCustomerId(int $customerId): ?int
    {
        return Customer::query()
            ->where('id', $customerId)
            ->where('created_by', creatorId())
            ->value('id');
    }
}
