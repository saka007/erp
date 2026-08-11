<?php

namespace Workdo\Account\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerCategory;
use Workdo\Account\Models\CustomerPriceList;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\Account\Http\Requests\StoreCustomerRequest;
use Workdo\Account\Http\Requests\UpdateCustomerRequest;
use Workdo\Account\Events\CreateCustomer;
use Workdo\Account\Events\UpdateCustomer;
use Workdo\Account\Events\DestroyCustomer;

class CustomerController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-customers')){
            $customers = Customer::query()
                ->with(['user:id,name,avatar,is_disable', 'customerCategory:id,name', 'priceLists'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-customers')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-customers')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('company_name'), fn($q) => $q->where('company_name', 'like', '%' . request('company_name') . '%'))
                ->when(request('customer_code'), fn($q) => $q->where('customer_code', 'like', '%' . request('customer_code') . '%'))
                ->when(request('tax_number'), fn($q) => $q->where('tax_number', 'like', '%' . request('tax_number') . '%'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $users = User::where('type', 'client')
                ->where('created_by', creatorId())
                ->whereNotIn('id', Customer::pluck('user_id')->filter())
                ->select('id', 'name', 'email', 'mobile_no')
                ->get();

            return Inertia::render('Account/Customers/Index', [
                'customers' => $customers,
                'users' => $users,
                'customerCategories' => CustomerCategory::query()
                    ->where('created_by', creatorId())
                    ->where('is_active', true)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'items' => ProductServiceItem::query()
                    ->where('created_by', creatorId())
                    ->where('is_active', true)
                    ->select('id', 'name', 'sku', 'unit', 'purchase_price', 'sale_price')
                    ->orderBy('name')
                    ->get(),
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function store(StoreCustomerRequest $request)
    {
        if(Auth::user()->can('create-customers')){
            $validated = $request->validated();

            $customer = new Customer();
            $customer->user_id = $validated['user_id'] ?? null;
            $customer->company_name = $validated['company_name'];
            $customer->contact_person_name = $validated['contact_person_name'];
            $customer->contact_person_email = $validated['contact_person_email'] ?? null;
            $customer->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $customer->tax_number = $validated['tax_number'] ?? null;
            $customer->payment_terms = $validated['payment_terms'] ?? null;
            $customer->credit_limit = isset($validated['credit_limit']) ? (float) $validated['credit_limit'] : null;
            $customer->credit_days = isset($validated['credit_days']) ? (int) $validated['credit_days'] : null;
            $customer->credit_enabled = (bool) ($validated['credit_enabled'] ?? false);
            $customer->reminder_enabled = (bool) ($validated['reminder_enabled'] ?? false);
            $customer->default_rate = isset($validated['default_rate']) ? (float) $validated['default_rate'] : null;
            $customer->currency_code = isset($validated['currency_code']) ? strtoupper((string) $validated['currency_code']) : null;
            $customer->customer_category_id = $this->resolveCustomerCategoryId($validated['customer_category_id'] ?? null);
            $customer->operating_model = $validated['operating_model'] ?? 'full_package_buyer';
            $customer->material_ownership = $validated['material_ownership'] ?? 'company_owned';
            $customer->billing_mode = $validated['billing_mode'] ?? 'sale_value';
            $customer->billing_address = $validated['billing_address'];
            $customer->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $customer->same_as_billing = $validated['same_as_billing'] ?? false;
            $customer->notes = $validated['notes'] ?? null;
            $customer->creator_id = Auth::id();
            $customer->created_by = creatorId();
            $customer->save();

            $this->syncPriceLists($request, $customer);

            CreateCustomer::dispatch($request, $customer);

            return redirect()->route('account.customers.index')->with('success', __('The customer has been created successfully.'));
        }
        return redirect()->route('account.customers.index')->with('error', __('Permission denied'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        if(Auth::user()->can('edit-customers')){
            $validated = $request->validated();

            $customer->company_name = $validated['company_name'];
            $customer->contact_person_name = $validated['contact_person_name'];
            $customer->contact_person_email = $validated['contact_person_email'] ?? null;
            $customer->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $customer->tax_number = $validated['tax_number'] ?? null;
            $customer->payment_terms = $validated['payment_terms'] ?? null;
            $customer->credit_limit = isset($validated['credit_limit']) ? (float) $validated['credit_limit'] : null;
            $customer->credit_days = isset($validated['credit_days']) ? (int) $validated['credit_days'] : null;
            $customer->credit_enabled = (bool) ($validated['credit_enabled'] ?? false);
            $customer->reminder_enabled = (bool) ($validated['reminder_enabled'] ?? false);
            $customer->default_rate = isset($validated['default_rate']) ? (float) $validated['default_rate'] : null;
            $customer->currency_code = isset($validated['currency_code']) ? strtoupper((string) $validated['currency_code']) : null;
            $customer->customer_category_id = $this->resolveCustomerCategoryId($validated['customer_category_id'] ?? null);
            $customer->operating_model = $validated['operating_model'] ?? $customer->operating_model ?? 'full_package_buyer';
            $customer->material_ownership = $validated['material_ownership'] ?? $customer->material_ownership ?? 'company_owned';
            $customer->billing_mode = $validated['billing_mode'] ?? $customer->billing_mode ?? 'sale_value';
            $customer->billing_address = $validated['billing_address'];
            $customer->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $customer->same_as_billing = $validated['same_as_billing'] ?? false;
            $customer->notes = $validated['notes'] ?? null;
            $customer->save();

            $this->syncPriceLists($request, $customer);

            UpdateCustomer::dispatch($request, $customer);

            return back()->with('success', __('The customer details are updated successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Customer $customer)
    {
        if(Auth::user()->can('delete-customers')){
            DestroyCustomer::dispatch($customer);
            $customer->delete();
            return back()->with('success', __('The customer has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    private function resolveCustomerCategoryId($categoryId): ?int
    {
        if (!$categoryId) {
            return null;
        }

        return CustomerCategory::query()
            ->where('id', (int) $categoryId)
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->value('id');
    }

    /**
     * Sync per-product pricing rows sent with the customer form.
     * Rows sent are upserted; rows omitted are removed for this customer.
     */
    private function syncPriceLists($request, Customer $customer): void
    {
        $rows = $request->input('price_lists', []);

        if (!is_array($rows)) {
            return;
        }

        $keptItemIds = [];

        foreach ($rows as $row) {
            $itemId = (int) ($row['product_service_item_id'] ?? 0);
            $unitPrice = (float) ($row['unit_price'] ?? 0);

            if ($itemId <= 0 || $unitPrice < 0) {
                continue;
            }

            // Only allow products that belong to this tenant
            $resolved = ProductServiceItem::query()
                ->where('id', $itemId)
                ->where('created_by', creatorId())
                ->value('id');

            if (!$resolved) {
                continue;
            }

            CustomerPriceList::updateOrCreate(
                [
                    'created_by' => creatorId(),
                    'customer_id' => $customer->id,
                    'product_service_item_id' => $resolved,
                ],
                [
                    'unit_price' => $unitPrice,
                    'currency_code' => strtoupper((string) ($row['currency_code'] ?? 'INR')),
                    'min_quantity' => (float) ($row['min_quantity'] ?? 1),
                    'is_active' => true,
                    'notes' => isset($row['notes']) && trim((string) $row['notes']) !== '' ? trim((string) $row['notes']) : null,
                    'creator_id' => Auth::id(),
                ]
            );

            $keptItemIds[] = $resolved;
        }

        // Remove rows the user deleted from the form
        if (count($keptItemIds) > 0) {
            CustomerPriceList::query()
                ->where('created_by', creatorId())
                ->where('customer_id', $customer->id)
                ->whereNotIn('product_service_item_id', $keptItemIds)
                ->delete();
        } else {
            CustomerPriceList::query()
                ->where('created_by', creatorId())
                ->where('customer_id', $customer->id)
                ->delete();
        }
    }
}