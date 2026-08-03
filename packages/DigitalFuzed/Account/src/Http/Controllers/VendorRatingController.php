<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\VendorRating;

class VendorRatingController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-vendors')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/VendorRatings/Index', [
            'ratings' => VendorRating::query()
                ->with(['vendor:id,company_name,vendor_code'])
                ->where('created_by', creatorId())
                ->latest('id')
                ->get(),
            'vendors' => Vendor::query()
                ->where('created_by', creatorId())
                ->select('id', 'company_name', 'vendor_code')
                ->orderBy('company_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('create-vendors')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'vendor_id' => ['required', 'integer', 'min:1'],
            'rating_date' => ['required', 'date'],
            'quality_score' => ['required', 'integer', 'between:1,5'],
            'delivery_score' => ['required', 'integer', 'between:1,5'],
            'service_score' => ['required', 'integer', 'between:1,5'],
            'price_score' => ['required', 'integer', 'between:1,5'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $vendorId = $this->resolveVendorId((int) $validated['vendor_id']);

        if (!$vendorId) {
            return back()->withErrors(['vendor_id' => __('Selected vendor is not available in this company.')]);
        }

        VendorRating::query()->create([
            'vendor_id' => $vendorId,
            'rating_date' => $validated['rating_date'],
            'quality_score' => (int) $validated['quality_score'],
            'delivery_score' => (int) $validated['delivery_score'],
            'service_score' => (int) $validated['service_score'],
            'price_score' => (int) $validated['price_score'],
            'overall_score' => $this->overallScore($validated),
            'remarks' => isset($validated['remarks']) ? trim((string) $validated['remarks']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Vendor rating created successfully.'));
    }

    public function update(Request $request, VendorRating $vendorRating)
    {
        if (!Auth::user()->can('edit-vendors')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $vendorRating->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'rating_date' => ['required', 'date'],
            'quality_score' => ['required', 'integer', 'between:1,5'],
            'delivery_score' => ['required', 'integer', 'between:1,5'],
            'service_score' => ['required', 'integer', 'between:1,5'],
            'price_score' => ['required', 'integer', 'between:1,5'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $vendorRating->update([
            'rating_date' => $validated['rating_date'],
            'quality_score' => (int) $validated['quality_score'],
            'delivery_score' => (int) $validated['delivery_score'],
            'service_score' => (int) $validated['service_score'],
            'price_score' => (int) $validated['price_score'],
            'overall_score' => $this->overallScore($validated),
            'remarks' => isset($validated['remarks']) ? trim((string) $validated['remarks']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('Vendor rating updated successfully.'));
    }

    public function destroy(VendorRating $vendorRating)
    {
        if (!Auth::user()->can('delete-vendors')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $vendorRating->created_by === (int) creatorId(), 403);

        $vendorRating->delete();

        return back()->with('success', __('Vendor rating deleted successfully.'));
    }

    private function resolveVendorId(int $vendorId): ?int
    {
        return Vendor::query()
            ->where('id', $vendorId)
            ->where('created_by', creatorId())
            ->value('id');
    }

    private function overallScore(array $validated): float
    {
        $sum = (int) $validated['quality_score'] + (int) $validated['delivery_score'] + (int) $validated['service_score'] + (int) $validated['price_score'];

        return round($sum / 4, 2);
    }
}
