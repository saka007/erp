<?php

namespace Workdo\ProductService\Listeners;

use App\Events\PostPurchaseInvoice;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Schema;
use Workdo\ProductService\Models\WarehouseStock;

class PostPurchaseInvoiceListener
{
    public function handle(PostPurchaseInvoice $event)
    {
        $purchaseInvoice = $event->purchaseInvoice;

        // Textile-generated invoices (GRN flow) may not carry a warehouse_id.
        // Resolve the tenant's warehouse so stock posting never crashes.
        $warehouseId = (int) $purchaseInvoice->warehouse_id;
        if ($warehouseId < 1) {
            $warehouseId = $this->resolveDefaultWarehouseId((int) ($purchaseInvoice->created_by ?? 0));
        }

        // No warehouse for the tenant: skip stock update (fail-open) so posting
        // still succeeds without inventory tracking.
        if ($warehouseId < 1) {
            return;
        }

        foreach ($purchaseInvoice->items()->get() as $item) {
            $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('product_id', $item->product_id)
                ->first();
            if ($stock) {
                $stock->increment('quantity', $item->quantity);
            } else {
                WarehouseStock::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity
                ]);
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
