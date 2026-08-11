<?php

namespace DigitalFuzed\TextileCore\Support;

use App\Models\Warehouse;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the warehouse for textile-generated invoices (GRN purchase
 * invoices and challan sales invoices).
 *
 * These workflow invoices are created from document metadata which may not
 * carry a warehouse_id. Posting them through the core Post*Invoice listeners
 * requires a warehouse for warehouse_stocks updates, so we resolve one in this
 * order:
 *
 * 1. Explicit warehouse_id from the workflow metadata.
 * 2. The tenant's active branch warehouse (session active_branch_id).
 * 3. The tenant's first active warehouse.
 * 4. null — callers decide (fail-open in the stock listeners).
 */
class TextileWarehouseResolver
{
    public static function resolve(?int $warehouseId, ?int $tenantId = null): ?int
    {
        if ($warehouseId !== null && $warehouseId > 0) {
            return $warehouseId;
        }

        if ($tenantId === null || $tenantId < 1) {
            $tenantId = auth()->check() && function_exists('creatorId') ? (int) creatorId() : (int) auth()->id();
        }

        if ($tenantId < 1 || ! Schema::hasTable('warehouses')) {
            return null;
        }

        $query = Warehouse::query()
            ->where('created_by', $tenantId)
            ->where('is_active', true);

        $activeBranchId = session('active_branch_id');
        if (is_numeric($activeBranchId)) {
            $branchWarehouse = (clone $query)->where('branch_id', (int) $activeBranchId)->first();
            if ($branchWarehouse) {
                return (int) $branchWarehouse->id;
            }
        }

        $warehouse = $query->first();

        return $warehouse ? (int) $warehouse->id : null;
    }
}
