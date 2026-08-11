<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\CustomerPriceList;
use Workdo\Account\Models\VendorPriceList;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;

/**
 * Seeds demo textile pricing data for the company:
 *  - units (kg, mtr, piece)
 *  - yarn & fabric products (default purchase_price + sale_price)
 *  - per-customer price lists (different sell rate per customer)
 *  - per-vendor price lists (different buy rate per vendor)
 *
 * Idempotent: existing SKUs / party-price rows are skipped.
 */
class TextileProductPricingSeeder extends Seeder
{
    public function run(): void
    {
        $company = User::query()
            ->where('type', 'company')
            ->orderBy('id')
            ->first();

        if (! $company) {
            $this->command?->warn('No company user found. Skipping textile product pricing seeding.');
            return;
        }

        $userId = (int) $company->id;

        $units = $this->seedUnits($userId);
        $products = $this->seedProducts($userId, $units);
        $this->seedCustomerPriceLists($userId, $products);
        $this->seedVendorPriceLists($userId, $products);

        $this->command?->info(
            sprintf(
                'Textile pricing seeded: %d units, %d products, %d customer prices, %d vendor prices (company %d).',
                count($units),
                count($products),
                CustomerPriceList::where('created_by', $userId)->count(),
                VendorPriceList::where('created_by', $userId)->count(),
                $userId,
            )
        );
    }

    private function seedUnits(int $userId): array
    {
        $wanted = ['kg' => 'Kilogram', 'mtr' => 'Meter', 'pc' => 'Piece', 'cone' => 'Cone'];

        $map = [];
        foreach ($wanted as $code => $name) {
            $unit = ProductServiceUnit::query()
                ->where('created_by', $userId)
                ->where('unit_name', $name)
                ->first();

            if (! $unit) {
                $unit = ProductServiceUnit::query()->create([
                    'unit_name' => $name,
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]);
            }

            $map[$code] = (int) $unit->id;
        }

        return $map;
    }

    private function seedProducts(int $userId, array $units): array
    {
        $products = [
            ['name' => 'Combed Yarn 40s', 'sku' => 'YRN-40S-COM', 'type' => 'yarn', 'unit' => 'kg', 'purchase_price' => 175.00, 'sale_price' => 205.00, 'description' => 'Combed cotton yarn 40s count, combed quality for weaving'],
            ['name' => 'Carded Yarn 40s', 'sku' => 'YRN-40S-CRD', 'type' => 'yarn', 'unit' => 'kg', 'purchase_price' => 168.00, 'sale_price' => 198.00, 'description' => 'Carded cotton yarn 40s count, standard weaving quality'],
            ['name' => 'Combed Yarn 30s', 'sku' => 'YRN-30S-COM', 'type' => 'yarn', 'unit' => 'kg', 'purchase_price' => 155.00, 'sale_price' => 182.00, 'description' => 'Combed cotton yarn 30s count, for medium count weaving'],
            ['name' => 'Carded Yarn 30s', 'sku' => 'YRN-30S-CRD', 'type' => 'yarn', 'unit' => 'kg', 'purchase_price' => 148.00, 'sale_price' => 175.00, 'description' => 'Carded cotton yarn 30s count'],
            ['name' => 'Combed Yarn 20s', 'sku' => 'YRN-20S-COM', 'type' => 'yarn', 'unit' => 'kg', 'purchase_price' => 140.00, 'sale_price' => 165.00, 'description' => 'Combed cotton yarn 20s count, heavy count for towels/denim'],
            ['name' => 'Carded Yarn 20s', 'sku' => 'YRN-20S-CRD', 'type' => 'yarn', 'unit' => 'kg', 'purchase_price' => 133.00, 'sale_price' => 158.00, 'description' => 'Carded cotton yarn 20s count'],
            ['name' => 'Grey Fabric 44"', 'sku' => 'GRY-44-40S', 'type' => 'grey_fabric', 'unit' => 'mtr', 'purchase_price' => 42.00, 'sale_price' => 52.00, 'description' => 'Grey fabric 44 inch width, 40s warp & weft, plain weave'],
            ['name' => 'Grey Fabric 58"', 'sku' => 'GRY-58-40S', 'type' => 'grey_fabric', 'unit' => 'mtr', 'purchase_price' => 55.00, 'sale_price' => 68.00, 'description' => 'Grey fabric 58 inch width, 40s, plain weave'],
            ['name' => 'Grey Fabric 68"', 'sku' => 'GRY-68-40S', 'type' => 'grey_fabric', 'unit' => 'mtr', 'purchase_price' => 68.00, 'sale_price' => 84.00, 'description' => 'Grey fabric 68 inch width, 40s, plain weave'],
        ];

        $seeded = [];
        foreach ($products as $data) {
            $exists = ProductServiceItem::query()
                ->where('created_by', $userId)
                ->where('sku', $data['sku'])
                ->exists();

            if ($exists) {
                continue;
            }

            $product = ProductServiceItem::query()->create([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'type' => $data['type'],
                'unit' => $units[$data['unit']] ?? null,
                'purchase_price' => $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'description' => $data['description'],
                'is_active' => 1,
                'creator_id' => $userId,
                'created_by' => $userId,
            ]);

            $seeded[$data['sku']] = (int) $product->id;
        }

        // Also include pre-existing textile products belonging to this company
        foreach (ProductServiceItem::query()->where('created_by', $userId)->get() as $item) {
            if (! in_array((int) $item->id, $seeded, true)) {
                $seeded[$item->sku ?: 'product-' . $item->id] = (int) $item->id;
            }
        }

        return $seeded;
    }

    private function seedCustomerPriceLists(int $userId, array $products): void
    {
        $customers = DB::table('customers')
            ->where('created_by', $userId)
            ->get(['id', 'company_name']);

        if ($customers->isEmpty() || empty($products)) {
            return;
        }

        // Different margins per customer to demonstrate per-customer pricing.
        $marginByIndex = [1.08, 1.04, 1.12]; // +8%, +4%, +12%

        foreach ($customers as $index => $customer) {
            $margin = $marginByIndex[$index % count($marginByIndex)];

            foreach ($products as $sku => $productId) {
                $product = ProductServiceItem::find($productId);
                $base = $product ? (float) $product->sale_price : 50.00;

                $exists = CustomerPriceList::query()
                    ->where('created_by', $userId)
                    ->where('customer_id', $customer->id)
                    ->where('product_service_item_id', $productId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                CustomerPriceList::query()->create([
                    'customer_id' => $customer->id,
                    'product_service_item_id' => $productId,
                    'unit_price' => round($base * $margin, 2),
                    'currency_code' => 'INR',
                    'min_quantity' => 1,
                    'is_active' => 1,
                    'notes' => 'Seeded demo rate for ' . $customer->company_name,
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]);
            }
        }
    }

    private function seedVendorPriceLists(int $userId, array $products): void
    {
        $vendors = DB::table('vendors')
            ->where('created_by', $userId)
            ->get(['id', 'company_name']);

        if ($vendors->isEmpty() || empty($products)) {
            return;
        }

        // Yarn purchase prices vary per vendor. Different discounts per vendor.
        $discountByIndex = [0.97, 1.0, 0.94, 0.985, 0.96]; // -3%, 0%, -6%, -1.5%, -4%

        foreach ($vendors as $index => $vendor) {
            $discount = $discountByIndex[$index % count($discountByIndex)];

            foreach ($products as $sku => $productId) {
                // Only yarn products get per-vendor pricing (vendors sell yarn / processing)
                $product = ProductServiceItem::find($productId);
                if (! $product) {
                    continue;
                }
                if ($product->type !== 'yarn') {
                    continue;
                }

                $exists = VendorPriceList::query()
                    ->where('created_by', $userId)
                    ->where('vendor_id', $vendor->id)
                    ->where('product_service_item_id', $productId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                VendorPriceList::query()->create([
                    'vendor_id' => $vendor->id,
                    'product_service_item_id' => $productId,
                    'unit_price' => round((float) $product->purchase_price * $discount, 2),
                    'currency_code' => 'INR',
                    'min_quantity' => 1,
                    'is_active' => 1,
                    'notes' => 'Seeded demo rate for ' . $vendor->company_name,
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]);
            }
        }
    }
}
