<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return back();
        }

        $canManageAll = $this->canManageAllBranches($user);
        $isTenantRoot = in_array($user->type, ['company', 'superadmin'], true);
        $assignedBranchIds = $isTenantRoot
            ? []
            : \DigitalFuzed\TextileCore\Services\TextileUserBranchService::branchIdsForUser($user->id, (int) creatorId());

        // Users with assignments may only switch within their assigned branches;
        // the switcher is only available when they have multiple assignments.
        $canSwitch = count($assignedBranchIds) > 0
            ? count($assignedBranchIds) > 1
            : $canManageAll;

        if (! $canSwitch) {
            return back()->with('error', __('You are not allowed to change branch context.'));
        }

        if (! Schema::hasTable('branches')) {
            return back()->with('error', __('Branches are not available in this environment.'));
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
        ]);

        $branchId = $validated['branch_id'] ?? null;

        if ($branchId === null) {
            $request->session()->forget('active_branch_id');
            return back()->with('success', __('Branch context cleared.'));
        }

        $exists = count($assignedBranchIds) > 0
            ? in_array((int) $branchId, $assignedBranchIds, true)
            : DB::table('branches')
                ->where('created_by', creatorId())
                ->where('id', (int) $branchId)
                ->exists();

        if (! $exists) {
            return back()->with('error', __('Selected branch is invalid.'));
        }

        $request->session()->put('active_branch_id', (int) $branchId);

        return back()->with('success', __('Branch context updated.'));
    }

    private function canManageAllBranches($user): bool
    {
        return in_array($user->type, ['company', 'superadmin'], true)
            || (method_exists($user, 'can') && $user->can('manage-any-branches'));
    }
}
