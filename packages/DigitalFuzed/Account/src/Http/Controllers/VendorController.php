<?php

namespace Workdo\Account\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\VendorPriceList;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\Account\Http\Requests\StoreVendorRequest;
use Workdo\Account\Http\Requests\UpdateVendorRequest;
use Workdo\Account\Events\CreateVendor;
use Workdo\Account\Events\UpdateVendor;
use Workdo\Account\Events\DestroyVendor;

class VendorController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-vendors')){
            $vendors = Vendor::query()
                ->with(['user:id,name,avatar,is_disable', 'priceLists'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-vendors')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-vendors')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('company_name'), fn($q) => $q->where('company_name', 'like', '%' . request('company_name') . '%'))
                ->when(request('vendor_code'), fn($q) => $q->where('vendor_code', 'like', '%' . request('vendor_code') . '%'))
                ->when(request('supplier_type'), fn($q) => $q->where('supplier_type', request('supplier_type')))
                ->when(request('tax_number'), fn($q) => $q->where('tax_number', 'like', '%' . request('tax_number') . '%'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $users = User::where('type', 'vendor')
                ->where('created_by', creatorId())
                ->whereNotIn('id', Vendor::pluck('user_id')->filter())
                ->select('id', 'name', 'email', 'mobile_no')
                ->get();

            return Inertia::render('Account/Vendors/Index', [
                'vendors' => $vendors,
                'users' => $users,
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



    public function store(StoreVendorRequest $request)
    {
        if(Auth::user()->can('create-vendors')){
            $validated = $request->validated();

            $vendor = new Vendor();
            $vendor->user_id = $validated['user_id'] ?? null;
            $vendor->company_name = $validated['company_name'];
            $vendor->supplier_type = $validated['supplier_type'];
            $vendor->contact_person_name = $validated['contact_person_name'];
            $vendor->contact_person_email = $validated['contact_person_email'] ?? null;
            $vendor->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $vendor->tax_number = $validated['tax_number'] ?? null;
            $vendor->payment_terms = $validated['payment_terms'] ?? null;
            $vendor->credit_limit = isset($validated['credit_limit']) ? (float) $validated['credit_limit'] : null;
            $vendor->credit_days = isset($validated['credit_days']) ? (int) $validated['credit_days'] : null;
            $vendor->credit_enabled = (bool) ($validated['credit_enabled'] ?? false);
            $vendor->billing_address = $validated['billing_address'];
            $vendor->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $vendor->same_as_billing = $validated['same_as_billing'] ?? false;
            $vendor->notes = $validated['notes'] ?? null;
            $vendor->creator_id = Auth::id();
            $vendor->created_by = creatorId();
            $vendor->save();

            $this->syncPriceLists($request, $vendor);

            CreateVendor::dispatch($request, $vendor);

            return redirect()->route('account.vendors.index')->with('success', __('The vendor has been created successfully.'));
        }
        return redirect()->route('account.vendors.index')->with('error', __('Permission denied'));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor)
    {
        if(Auth::user()->can('edit-vendors')){
            $validated = $request->validated();

            $vendor->company_name = $validated['company_name'];
            $vendor->supplier_type = $validated['supplier_type'];
            $vendor->contact_person_name = $validated['contact_person_name'];
            $vendor->contact_person_email = $validated['contact_person_email'] ?? null;
            $vendor->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $vendor->tax_number = $validated['tax_number'] ?? null;
            $vendor->payment_terms = $validated['payment_terms'] ?? null;
            $vendor->credit_limit = isset($validated['credit_limit']) ? (float) $validated['credit_limit'] : null;
            $vendor->credit_days = isset($validated['credit_days']) ? (int) $validated['credit_days'] : null;
            $vendor->credit_enabled = (bool) ($validated['credit_enabled'] ?? false);
            $vendor->billing_address = $validated['billing_address'];
            $vendor->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $vendor->same_as_billing = $validated['same_as_billing'] ?? false;
            $vendor->notes = $validated['notes'] ?? null;
            $vendor->save();

            $this->syncPriceLists($request, $vendor);

            UpdateVendor::dispatch($request, $vendor);

            return back()->with('success', __('The vendor details are updated successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Vendor $vendor)
    {
        if(Auth::user()->can('delete-vendors')){
            DestroyVendor::dispatch($vendor);
            $vendor->delete();
            return back()->with('success', __('The vendor has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    /**
     * Sync per-product pricing rows sent with the vendor form.
     * Rows sent are upserted; rows omitted are removed for this vendor.
     */
    private function syncPriceLists($request, Vendor $vendor): void
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

            VendorPriceList::updateOrCreate(
                [
                    'created_by' => creatorId(),
                    'vendor_id' => $vendor->id,
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
            VendorPriceList::query()
                ->where('created_by', creatorId())
                ->where('vendor_id', $vendor->id)
                ->whereNotIn('product_service_item_id', $keptItemIds)
                ->delete();
        } else {
            VendorPriceList::query()
                ->where('created_by', creatorId())
                ->where('vendor_id', $vendor->id)
                ->delete();
        }
    }
}
