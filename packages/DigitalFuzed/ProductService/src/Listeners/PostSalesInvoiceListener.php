<?php

namespace Workdo\ProductService\Listeners;

use App\Events\PostSalesInvoice;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Schema;
use Workdo\ProductService\Models\WarehouseStock;

class PostSalesInvoiceListener
{
    public function handle(PostSalesInvoice $event)
    {
        $salesInvoice = $event->salesInvoice;

        if ($salesInvoice->type !== 'product') {
            return;
        }

        // Textile-generated invoices (challan flow) may not carry a warehouse_id.
        // Resolve the tenant's warehouse so stock posting never silently no-ops
        // or crashes.
        $warehouseId = (int) $salesInvoice->warehouse_id;
        if ($warehouseId < 1) {
            $warehouseId = $this->resolveDefaultWarehouseId((int) ($salesInvoice->created_by ?? 0));
        }

        if ($warehouseId < 1) {
            return;
        }

        foreach ($salesInvoice->items()->get() as $item) {
            $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('product_id', $item->product_id)
                ->first();
            if ($stock) {
                $stock->decrement('quantity', $item->quantity);
            }
        }
    }

    /**
     * Pick the tenant's warehouse: prefer the one matching the active branch
     * session, otherwise the first active warehouse for the tenant.
     */
    private function resolveDefaultWarehouseId(int $tenantId): int
    {
        if (! Schema::hasTable('warehouses')) {
            return 0;
        }

        $query = Warehouse::query()->where('created_by', $tenantId)->where('is_active', true);

        $activeBranchId = session('active_branch_id');
        if (is_numeric($activeBranchId)) {
            $branchWarehouse = (clone $query)->where('branch_id', (int) $activeBranchId)->first();
            if ($branchWarehouse) {
                return (int) $branchWarehouse->id;
            }
        }

        $warehouse = $query->first();

        return $warehouse ? (int) $warehouse->id : 0;
    }
}
