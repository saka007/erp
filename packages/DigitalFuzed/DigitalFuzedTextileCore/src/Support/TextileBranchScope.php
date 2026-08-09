<?php

namespace DigitalFuzed\TextileCore\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TextileBranchScope
{
    /**
     * Scope a workflow-document query to the current user's branch.
     * Kept for backward compatibility; prefer applyScope() for non-document tables.
     */
    public static function applyWorkflowScope(Builder $query): Builder
    {
        return self::applyScope($query, 'textile_workflow_documents', 'branch_id');
    }

    /**
     * Scope a query to the current user's branch.
     *
     * Works for any table that carries a branch_id column (workflow documents,
     * movements, lots, reservations, ...). No-op when the column is missing so
     * the same code path is safe before/after schema migrations.
     */
    public static function applyScope(Builder $query, string $table, string $column = 'branch_id'): Builder
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return $query;
        }

        $user = Auth::user();
        if (! $user) {
            return $query;
        }

        $branchId = self::currentBranchId($user);

        if (self::canManageAllBranches($user)) {
            if ($branchId !== null) {
                return $query->where($column, $branchId);
            }

            return $query;
        }

        if ($branchId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, $branchId);
    }

    public static function branchIdForCreate(): ?int
    {
        if (! self::workflowBranchColumnExists()) {
            return null;
        }

        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return self::currentBranchId($user);
    }

    public static function requireBranchIdForCreate(): ?int
    {
        $branchId = self::branchIdForCreate();
        $user = Auth::user();

        if (! self::workflowBranchColumnExists() || ! $user || ! Schema::hasTable('branches')) {
            return $branchId;
        }

        $tenantId = self::tenantId($user);
        $tenantHasBranches = $tenantId !== null
            && DB::table('branches')->where('created_by', $tenantId)->exists();

        if ($tenantHasBranches && $branchId === null) {
            throw new RuntimeException('Select an active branch before recording textile operations.');
        }

        return $branchId;
    }

    private static function currentBranchId($user): ?int
    {
        $tenantId = self::tenantId($user);
        $assignedBranchIds = \DigitalFuzed\TextileCore\Services\TextileUserBranchService::branchIdsForUser($user->id, $tenantId);

        // Users with explicit branch assignments are scoped to those branches.
        if (! self::canManageAllBranches($user) && count($assignedBranchIds) > 0) {
            if (count($assignedBranchIds) === 1) {
                return $assignedBranchIds[0];
            }

            $activeBranchId = session('active_branch_id');
            if (is_numeric($activeBranchId) && in_array((int) $activeBranchId, $assignedBranchIds, true)) {
                return (int) $activeBranchId;
            }

            return $assignedBranchIds[0];
        }

        if (self::canManageAllBranches($user)) {
            return self::activeBranchIdForTenant($tenantId);
        }

        return self::employeeBranchId($user);
    }

    private static function canManageAllBranches($user): bool
    {
        return in_array($user->type, ['company', 'superadmin'], true)
            || (method_exists($user, 'can') && $user->can('manage-any-branches'));
    }

    private static function activeBranchIdForTenant(?int $tenantId): ?int
    {
        if ($tenantId === null || $tenantId <= 0 || ! Schema::hasTable('branches')) {
            return null;
        }

        $activeBranchId = session('active_branch_id');
        if (! is_numeric($activeBranchId)) {
            return null;
        }

        return DB::table('branches')
            ->where('created_by', $tenantId)
            ->where('id', (int) $activeBranchId)
            ->exists()
            ? (int) $activeBranchId
            : null;
    }

    private static function employeeBranchId($user): ?int
    {
        if (! Schema::hasTable('employees')) {
            return null;
        }

        $branchId = DB::table('employees')
            ->where('user_id', $user->id)
            ->value('branch_id');

        return $branchId ? (int) $branchId : null;
    }

    private static function tenantId($user): ?int
    {
        if ($user->type === 'company') {
            return (int) $user->id;
        }

        if (! empty($user->created_by)) {
            return (int) $user->created_by;
        }

        if (function_exists('creatorId')) {
            return (int) creatorId();
        }

        return $user->id ? (int) $user->id : null;
    }

    private static function workflowBranchColumnExists(): bool
    {
        return Schema::hasTable('textile_workflow_documents')
            && Schema::hasColumn('textile_workflow_documents', 'branch_id');
    }
}
