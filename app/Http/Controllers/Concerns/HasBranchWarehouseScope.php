<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait HasBranchWarehouseScope
{
    protected function canManageAllBranches($user): bool
    {
        return in_array($user->type, ['company', 'superadmin'], true)
            || $user->can('manage-any-branches');
    }

    protected function currentUserBranchId($user): ?int
    {
        if ($this->canManageAllBranches($user) && Schema::hasTable('branches')) {
            $activeBranchId = session('active_branch_id');
            if (is_numeric($activeBranchId)) {
                $exists = DB::table('branches')
                    ->where('created_by', creatorId())
                    ->where('id', (int) $activeBranchId)
                    ->exists();

                return $exists ? (int) $activeBranchId : null;
            }
        }

        if (!Schema::hasTable('employees')) {
            return null;
        }

        $branchId = DB::table('employees')
            ->where('user_id', $user->id)
            ->value('branch_id');

        return $branchId ? (int) $branchId : null;
    }

    protected function scopedWarehouseQuery($user): Builder
    {
        $query = Warehouse::query()
            ->where('created_by', creatorId())
            ->where('is_active', true);

        if ($this->canManageAllBranches($user)) {
            $activeBranchId = $this->currentUserBranchId($user);
            if ($activeBranchId !== null) {
                return $query->where('branch_id', $activeBranchId);
            }

            return $query;
        }

        $branchId = $this->currentUserBranchId($user);
        if ($branchId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $branchId);
    }

    protected function scopedWarehouseIds($user): array
    {
        return $this->scopedWarehouseQuery($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    protected function isWarehouseAccessible($warehouseId, $user, bool $allowNull = false): bool
    {
        if ($warehouseId === null) {
            return $allowNull;
        }

        return $this->scopedWarehouseQuery($user)
            ->where('id', (int) $warehouseId)
            ->exists();
    }

    protected function applyWarehouseScope(Builder $query, string $column, $user, bool $allowNull = false): Builder
    {
        if ($this->canManageAllBranches($user)) {
            return $query;
        }

        $warehouseIds = $this->scopedWarehouseIds($user);
        if (empty($warehouseIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($scoped) use ($column, $warehouseIds, $allowNull) {
            $scoped->whereIn($column, $warehouseIds);

            if ($allowNull) {
                $scoped->orWhereNull($column);
            }
        });
    }
}
