<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextilePartyBranchAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Branch-restricted parties (vendors + customers).
 *
 * A party with NO assignments is visible in ALL branches (global default).
 * A party WITH assignments is visible ONLY in the assigned branches.
 *
 * Usage (list/dropdown queries):
 *   TextilePartyBranchService::applyPartyScope($query, 'vendor', 'vendors')
 *   TextilePartyBranchService::applyPartyScope($query, 'customer', 'customers')
 * The scope is only applied when a specific active branch is resolved; when the
 * user is in "All Branches" view (or the schema is missing) the query is untouched.
 */
class TextilePartyBranchService
{
    public const PARTY_VENDOR = 'vendor';
    public const PARTY_CUSTOMER = 'customer';

    /**
     * Table that stores the assignments.
     */
    public const TABLE = 'textile_party_branch_assignments';

    /**
     * Apply the branch restriction to a vendor/customer Eloquent query.
     *
     * @param  Builder  $query     the query being scoped (vendors/customers)
     * @param  string   $partyType 'vendor' | 'customer'
     * @param  string   $partyTable 'vendors' | 'customers' (table alias for the id column)
     */
    public static function applyPartyScope(Builder $query, string $partyType, string $partyTable): Builder
    {
        if (! self::schemaReady()) {
            return $query;
        }

        $branchId = self::activeBranchId();
        $tenantId = self::tenantId();

        if ($branchId === null || $tenantId === null) {
            // "All Branches" view (company/superadmin without a selected branch)
            // or no tenant context → show all parties.
            return $query;
        }

        return self::scopeQuery($query, $partyType, $partyTable, $branchId, $tenantId);
    }

    /**
     * Apply the restriction with explicit branch/tenant (used by services and tests).
     */
    public static function scopeQuery(Builder $query, string $partyType, string $partyTable, int $branchId, int $tenantId): Builder
    {
        $table = self::TABLE;

        return $query->where(function ($q) use ($partyType, $partyTable, $branchId, $tenantId, $table) {
            // No assignments at all → global (visible everywhere).
            $q->whereNotExists(function ($sub) use ($partyType, $partyTable, $tenantId, $table) {
                $sub->selectRaw('1')
                    ->from($table)
                    ->whereColumn($table.'.party_id', $partyTable.'.id')
                    ->where($table.'.party_type', $partyType)
                    ->where($table.'.created_by', $tenantId);
            });

            // Has assignments AND this branch is among them.
            $q->orWhereExists(function ($sub) use ($partyType, $partyTable, $branchId, $tenantId, $table) {
                $sub->selectRaw('1')
                    ->from($table)
                    ->whereColumn($table.'.party_id', $partyTable.'.id')
                    ->where($table.'.party_type', $partyType)
                    ->where($table.'.branch_id', $branchId)
                    ->where($table.'.created_by', $tenantId);
            });
        });
    }

    /**
     * Resolve the party ids visible to a given branch (used for validation / Rule::in).
     */
    public static function visiblePartyIds(string $partyType, string $partyTable, int $branchId, int $tenantId): array
    {
        if (! self::schemaReady()) {
            return [];
        }

        $table = self::TABLE;

        $ids = DB::table($partyTable)
            ->where($partyTable.'.created_by', $tenantId)
            ->where(function ($q) use ($partyType, $partyTable, $branchId, $tenantId, $table) {
                $q->whereNotExists(function ($sub) use ($partyType, $partyTable, $tenantId, $table) {
                    $sub->selectRaw('1')
                        ->from($table)
                        ->whereColumn($table.'.party_id', $partyTable.'.id')
                        ->where($table.'.party_type', $partyType)
                        ->where($table.'.created_by', $tenantId);
                })->orWhereExists(function ($sub) use ($partyType, $partyTable, $branchId, $tenantId, $table) {
                    $sub->selectRaw('1')
                        ->from($table)
                        ->whereColumn($table.'.party_id', $partyTable.'.id')
                        ->where($table.'.party_type', $partyType)
                        ->where($table.'.branch_id', $branchId)
                        ->where($table.'.created_by', $tenantId);
                });
            })
            ->pluck($partyTable.'.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * Branches a party is explicitly assigned to (empty = global/all branches).
     */
    public static function assignedBranchIds(string $partyType, int $partyId, int $tenantId): array
    {
        return TextilePartyBranchAssignment::query()
            ->forTenant($tenantId)
            ->forParty($partyType, $partyId)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Whether a party is visible to a branch (no assignments → always visible).
     */
    public static function partyVisibleToBranch(string $partyType, int $partyId, int $branchId, int $tenantId): bool
    {
        $assigned = TextilePartyBranchAssignment::query()
            ->forTenant($tenantId)
            ->forParty($partyType, $partyId)
            ->exists();

        if (! $assigned) {
            return true;
        }

        return TextilePartyBranchAssignment::query()
            ->forTenant($tenantId)
            ->forParty($partyType, $partyId)
            ->forBranch($branchId)
            ->exists();
    }

    /**
     * Bulk-assign parties to branches. Returns the number of rows created.
     */
    public static function assignToBranches(string $partyType, array $partyIds, array $branchIds, int $tenantId, ?int $creatorId = null): int
    {
        $partyIds = array_values(array_filter(array_map('intval', $partyIds), fn ($id) => $id > 0));
        $branchIds = array_values(array_filter(array_map('intval', $branchIds), fn ($id) => $id > 0));

        if (empty($partyIds) || empty($branchIds)) {
            return 0;
        }

        $now = now();

        $rows = [];
        foreach ($partyIds as $partyId) {
            foreach ($branchIds as $branchId) {
                $rows[] = [
                    'party_type' => $partyType,
                    'party_id' => $partyId,
                    'branch_id' => $branchId,
                    'creator_id' => $creatorId,
                    'created_by' => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return DB::table(self::TABLE)->insertOrIgnore($rows);
    }

    /**
     * Bulk-remove parties from branches. Returns the number of rows deleted.
     */
    public static function removeFromBranches(string $partyType, array $partyIds, array $branchIds, int $tenantId): int
    {
        $partyIds = array_values(array_filter(array_map('intval', $partyIds), fn ($id) => $id > 0));
        $branchIds = array_values(array_filter(array_map('intval', $branchIds), fn ($id) => $id > 0));

        if (empty($partyIds) || empty($branchIds)) {
            return 0;
        }

        return TextilePartyBranchAssignment::query()
            ->forTenant($tenantId)
            ->where('party_type', $partyType)
            ->whereIn('party_id', $partyIds)
            ->whereIn('branch_id', $branchIds)
            ->delete();
    }

    /**
     * Replace all branch assignments for a party (set operations).
     */
    public static function syncPartyBranches(string $partyType, int $partyId, array $branchIds, int $tenantId, ?int $creatorId = null): void
    {
        TextilePartyBranchAssignment::query()
            ->forTenant($tenantId)
            ->forParty($partyType, $partyId)
            ->delete();

        if (! empty($branchIds)) {
            self::assignToBranches($partyType, [$partyId], $branchIds, $tenantId, $creatorId);
        }
    }

    /**
     * Active branch for the current request (null = "All Branches" / no branch selected).
     * Mirrors TextileBranchScope::branchIdForCreate() so party dropdowns follow
     * exactly the same branch context as workflow documents.
     */
    private static function activeBranchId(): ?int
    {
        if (! self::schemaReady()) {
            return null;
        }

        return \DigitalFuzed\TextileCore\Support\TextileBranchScope::branchIdForCreate();
    }

    /**
     * Tenant id for the current user (mirrors creatorId() semantics).
     */
    private static function tenantId(): ?int
    {
        if (function_exists('creatorId')) {
            return (int) creatorId();
        }

        return null;
    }

    private static function schemaReady(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable(self::TABLE)
            && \Illuminate\Support\Facades\Schema::hasTable('branches');
    }
}
