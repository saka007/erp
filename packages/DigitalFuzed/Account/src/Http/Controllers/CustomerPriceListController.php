<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerPriceList;
use Workdo\ProductService\Models\ProductServiceItem;

class CustomerPriceListController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/CustomerPriceLists/Index', [
            'priceLists' => CustomerPriceList::query()
                ->with(['customer:id,company_name', 'item:id,name,sku,unit'])
                ->where('created_by', creatorId())
                ->latest('id')
                ->get(),
            'customers' => Customer::query()
                ->where('created_by', creatorId())
                ->select('id', 'company_name')
                ->orderBy('company_name')
                ->get(),
            'items' => ProductServiceItem::query()
                ->where('created_by', creatorId())
                ->where('is_active', true)
                ->select('id', 'name', 'sku', 'unit')
                ->orderBy('name')
                ->get(),
            'currencyOptions' => collect(config('default_currency.currencies', []))
                ->map(fn ($currency) => [
                    'value' => $currency['code'] ?? 'USD',
                    'label' => ($currency['code'] ?? 'USD').' - '.($currency['name'] ?? 'Currency'),
                ])
                ->values(),
            'defaultCurrency' => admin_setting('defaultCurrency') ?? 'USD',
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('create-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'product_service_item_id' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'max:10'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerId = $this->resolveCustomerId((int) $validated['customer_id']);
        $itemId = $this->resolveItemId((int) $validated['product_service_item_id']);

        if (!$customerId || !$itemId) {
            return back()->withErrors(['customer_id' => __('Selected customer or item is not available in this company.')]);
        }

        CustomerPriceList::updateOrCreate([
            'created_by' => creatorId(),
            'customer_id' => $customerId,
            'product_service_item_id' => $itemId,
            'currency_code' => strtoupper((string) $validated['currency_code']),
        ], [
            'unit_price' => (float) $validated['unit_price'],
            'min_quantity' => (float) ($validated['min_quantity'] ?? 1),
            'is_active' => true,
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Customer price added successfully.'));
    }

    public function update(Request $request, CustomerPriceList $customerPriceList)
    {
        if (!Auth::user()->can('edit-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerPriceList->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'unit_price' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'max:10'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerPriceList->update([
            'unit_price' => (float) $validated['unit_price'],
            'currency_code' => strtoupper((string) $validated['currency_code']),
            'min_quantity' => (float) ($validated['min_quantity'] ?? 1),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
        ]);

        return back()->with('success', __('Customer price updated successfully.'));
    }

    public function destroy(CustomerPriceList $customerPriceList)
    {
        if (!Auth::user()->can('delete-customers')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $customerPriceList->created_by === (int) creatorId(), 403);

        $customerPriceList->delete();

        return back()->with('success', __('Customer price deleted successfully.'));
    }

    private function resolveCustomerId(int $customerId): ?int
    {
        return Customer::query()
            ->where('id', $customerId)
            ->where('created_by', creatorId())
            ->value('id');
    }

    private function resolveItemId(int $itemId): ?int
    {
        return ProductServiceItem::query()
            ->where('id', $itemId)
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->value('id');
    }
}
