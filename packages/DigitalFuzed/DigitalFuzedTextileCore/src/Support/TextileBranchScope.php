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
    public static function applyWorkflowScope(Builder $query): Builder
    {
        if (! self::workflowBranchColumnExists()) {
            return $query;
        }

        $user = Auth::user();
        if (! $user) {
            return $query;
        }

        $branchId = self::currentBranchId($user);

        if (self::canManageAllBranches($user)) {
            if ($branchId !== null) {
                return $query->where('branch_id', $branchId);
            }

            return $query;
        }

        if ($branchId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $branchId);
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
        if (self::canManageAllBranches($user)) {
            return self::activeBranchIdForTenant(self::tenantId($user));
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
