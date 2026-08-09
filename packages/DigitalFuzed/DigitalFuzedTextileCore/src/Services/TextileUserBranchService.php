<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileUserBranchAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TextileUserBranchService
{
    /**
     * Branch ids assigned to a user within a tenant.
     * Returns [] when the table is missing or the user has no assignments.
     */
    public static function branchIdsForUser(int $userId, ?int $tenantId = null): array
    {
        if (! Schema::hasTable('textile_user_branch_assignments')) {
            return [];
        }

        $query = TextileUserBranchAssignment::query()
            ->where('user_id', $userId);

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('created_by', $tenantId);
        }

        return $query->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Whether the user has access to more than one branch (needs the
     * header branch switcher). Company/superadmin are handled separately
     * by canManageAllBranches().
     */
    public static function hasMultipleBranchAccess(int $userId, ?int $tenantId = null): bool
    {
        return count(self::branchIdsForUser($userId, $tenantId)) > 1;
    }

    /**
     * Replace all branch assignments for a user.
     */
    public static function syncBranches(int $userId, array $branchIds, ?int $tenantId = null, ?int $creatorId = null): void
    {
        if (! Schema::hasTable('textile_user_branch_assignments')) {
            return;
        }

        $branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds))));

        DB::table('textile_user_branch_assignments')
            ->where('user_id', $userId)
            ->delete();

        if (empty($branchIds)) {
            return;
        }

        $tenantId = $tenantId ?: (int) creatorId();

        foreach ($branchIds as $branchId) {
            DB::table('textile_user_branch_assignments')->insert([
                'user_id' => $userId,
                'branch_id' => $branchId,
                'creator_id' => $creatorId ?: auth()->id(),
                'created_by' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Validate that all given branch ids belong to the tenant.
     */
    public static function validTenantBranchIds(array $branchIds, ?int $tenantId = null): array
    {
        if (empty($branchIds) || ! Schema::hasTable('branches')) {
            return [];
        }

        $tenantId = $tenantId ?: (int) creatorId();

        return DB::table('branches')
            ->where('created_by', $tenantId)
            ->whereIn('id', array_map('intval', $branchIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
