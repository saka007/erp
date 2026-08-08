<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use DigitalFuzed\TextileCore\Models\TextilePartyBranchAssignment;
use DigitalFuzed\TextileCore\Services\TextilePartyBranchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;

class TextilePartyBranchController extends Controller
{
    public function index()
    {
        $this->authorizeTextileAccess();

        $tenantId = (int) creatorId();

        $branchOptions = $this->branchOptions($tenantId);

        $vendors = $this->partyRows($tenantId, TextilePartyBranchService::PARTY_VENDOR);
        $customers = $this->partyRows($tenantId, TextilePartyBranchService::PARTY_CUSTOMER);

        return Inertia::render('DigitalFuzedTextileCore/PartyBranches/Index', [
            'vendors' => $vendors,
            'customers' => $customers,
            'branchOptions' => $branchOptions,
        ]);
    }

    /**
     * Bulk-assign parties to branches.
     */
    public function assign(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'party_type' => ['required', 'in:vendor,customer'],
            'party_ids' => ['required', 'array', 'min:1'],
            'party_ids.*' => ['required', 'integer', 'min:1'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $tenantId = (int) creatorId();

        // Only allow branches that belong to the tenant.
        $allowedBranchIds = collect($this->branchOptions($tenantId))->pluck('id')->all();
        $branchIds = array_values(array_intersect($validated['branch_ids'], $allowedBranchIds));

        if (empty($branchIds)) {
            return back()->withErrors(['branch_ids' => __('No valid branches selected for assignment.')]);
        }

        TextilePartyBranchService::assignToBranches(
            $validated['party_type'],
            $validated['party_ids'],
            $branchIds,
            $tenantId,
            (int) Auth::id()
        );

        return back()->with('success', __('Parties assigned to branches successfully.'));
    }

    /**
     * Bulk-remove parties from branches.
     */
    public function remove(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'party_type' => ['required', 'in:vendor,customer'],
            'party_ids' => ['required', 'array', 'min:1'],
            'party_ids.*' => ['required', 'integer', 'min:1'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'min:1'],
        ]);

        $tenantId = (int) creatorId();

        $allowedBranchIds = collect($this->branchOptions($tenantId))->pluck('id')->all();
        $branchIds = array_values(array_intersect($validated['branch_ids'], $allowedBranchIds));

        if (empty($branchIds)) {
            return back()->withErrors(['branch_ids' => __('No valid branches selected for removal.')]);
        }

        TextilePartyBranchService::removeFromBranches(
            $validated['party_type'],
            $validated['party_ids'],
            $branchIds,
            $tenantId
        );

        return back()->with('success', __('Parties removed from branches successfully.'));
    }

    /**
     * Parties with their explicitly assigned branches ([] = global/all branches).
     */
    private function partyRows(int $tenantId, string $partyType): array
    {
        $model = $partyType === TextilePartyBranchService::PARTY_VENDOR
            ? Vendor::query()
            : Customer::query();

        $table = $partyType === TextilePartyBranchService::PARTY_VENDOR ? 'vendors' : 'customers';
        $hasActiveColumn = Schema::hasColumn($table, 'is_active');

        $rows = $model->where('created_by', $tenantId)
            ->orderBy('company_name')
            ->get($hasActiveColumn
                ? ['id', 'company_name', 'is_active']
                : ['id', 'company_name']);

        return $rows->map(function ($party) use ($partyType, $tenantId, $hasActiveColumn) {
            return [
                'id' => (int) $party->id,
                'name' => (string) ($party->company_name ?? '-'),
                'is_active' => $hasActiveColumn ? (bool) $party->is_active : true,
                'assigned_branch_ids' => TextilePartyBranchService::assignedBranchIds($partyType, (int) $party->id, $tenantId),
            ];
        })->values()->all();
    }

    private function branchOptions(int $tenantId): array
    {
        if ($tenantId <= 0 || ! Schema::hasTable('branches')) {
            return [];
        }

        return DB::table('branches')
            ->where('created_by', $tenantId)
            ->orderBy('branch_name')
            ->get(['id', 'branch_name'])
            ->map(fn ($branch) => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->branch_name,
            ])
            ->values()
            ->all();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
    }
}
