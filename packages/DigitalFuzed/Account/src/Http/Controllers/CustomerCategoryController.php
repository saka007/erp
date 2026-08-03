<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\CustomerCategory;

class CustomerCategoryController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/CustomerCategories/Index', [
            'categories' => CustomerCategory::query()
                ->where('created_by', creatorId())
                ->latest('id')
                ->get(),
        ]);
    }

    public function store()
    {
        if (!Auth::user()->can('create-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        CustomerCategory::create([
            'name' => trim((string) $validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'is_active' => true,
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Customer category created successfully.'));
    }

    public function update(CustomerCategory $customerCategory)
    {
        if (!Auth::user()->can('edit-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerCategory->created_by === (int) creatorId(), 403);

        $validated = request()->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customerCategory->update([
            'name' => trim((string) $validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('Customer category updated successfully.'));
    }

    public function destroy(CustomerCategory $customerCategory)
    {
        if (!Auth::user()->can('delete-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerCategory->created_by === (int) creatorId(), 403);

        $customerCategory->delete();

        return back()->with('success', __('Customer category deleted successfully.'));
    }
}
