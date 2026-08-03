<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\VendorPerformanceSnapshot;
use Workdo\Account\Models\VendorRating;

class VendorPerformanceController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-vendors')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/VendorPerformance/Index', [
            'snapshots' => VendorPerformanceSnapshot::query()
                ->with(['vendor:id,company_name,vendor_code'])
                ->where('created_by', creatorId())
                ->latest('period_month')
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
            'period_month' => ['required', 'date_format:Y-m'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $vendorId = $this->resolveVendorId((int) $validated['vendor_id']);
        if (!$vendorId) {
            return back()->withErrors(['vendor_id' => __('Selected vendor is not available in this company.')]);
        }

        $monthStart = $validated['period_month'] . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $aggregate = VendorRating::query()
            ->where('created_by', creatorId())
            ->where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->whereBetween('rating_date', [$monthStart, $monthEnd])
            ->selectRaw('COUNT(*) as rating_count')
            ->selectRaw('AVG(quality_score) as avg_quality_score')
            ->selectRaw('AVG(delivery_score) as avg_delivery_score')
            ->selectRaw('AVG(service_score) as avg_service_score')
            ->selectRaw('AVG(price_score) as avg_price_score')
            ->selectRaw('AVG(overall_score) as avg_overall_score')
            ->first();

        if (!$aggregate || (int) $aggregate->rating_count === 0) {
            return back()->withErrors(['period_month' => __('No active vendor ratings found for selected month.')]);
        }

        VendorPerformanceSnapshot::query()->updateOrCreate(
            [
                'vendor_id' => $vendorId,
                'period_month' => $validated['period_month'],
                'created_by' => creatorId(),
            ],
            [
                'rating_count' => (int) $aggregate->rating_count,
                'avg_quality_score' => round((float) $aggregate->avg_quality_score, 2),
                'avg_delivery_score' => round((float) $aggregate->avg_delivery_score, 2),
                'avg_service_score' => round((float) $aggregate->avg_service_score, 2),
                'avg_price_score' => round((float) $aggregate->avg_price_score, 2),
                'avg_overall_score' => round((float) $aggregate->avg_overall_score, 2),
                'remarks' => isset($validated['remarks']) ? trim((string) $validated['remarks']) : null,
                'creator_id' => Auth::id(),
            ]
        );

        return back()->with('success', __('Vendor performance snapshot generated successfully.'));
    }

    public function destroy(VendorPerformanceSnapshot $vendorPerformance)
    {
        if (!Auth::user()->can('delete-vendors')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $vendorPerformance->created_by === (int) creatorId(), 403);
        $vendorPerformance->delete();

        return back()->with('success', __('Vendor performance snapshot deleted successfully.'));
    }

    private function resolveVendorId(int $vendorId): ?int
    {
        return Vendor::query()
            ->where('id', $vendorId)
            ->where('created_by', creatorId())
            ->value('id');
    }
}
