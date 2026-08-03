<?php

namespace Workdo\ProductService\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceItemDocument;
use Workdo\ProductService\Models\ProductServiceItemImage;
use Workdo\ProductService\Models\ProductServiceItemVariant;

class ProductMasterExtensionController extends Controller
{
    public function variantsIndex()
    {
        if (!Auth::user()->can('manage-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = creatorId();

        $variants = ProductServiceItemVariant::query()
            ->with(['product:id,name,sku,type'])
            ->where('created_by', $tenantId)
            ->latest('id')
            ->get();

        return Inertia::render('ProductService/ProductMaster/Variants', [
            'variants' => $variants,
            'items' => $this->itemOptions(),
            'stats' => [
                'total' => $variants->count(),
                'active' => $variants->where('is_active', true)->count(),
                'inactive' => $variants->where('is_active', false)->count(),
                'yarnScoped' => $variants->filter(fn ($row) => ($row->product?->type ?? null) === 'yarn')->count(),
            ],
        ]);
    }

    public function variantsStore(Request $request)
    {
        if (!Auth::user()->can('create-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = creatorId();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_type' => ['required', 'in:count,denier,shade,width,generic'],
            'variant_label' => ['required', 'string', 'max:120'],
            'variant_value' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:30'],
            'sku_suffix' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $productId = ProductServiceItem::query()
            ->where('id', (int) $validated['product_id'])
            ->where('created_by', $tenantId)
            ->value('id');

        if (!$productId) {
            return back()->withErrors(['product_id' => __('Selected item is not available for this company.')]);
        }

        ProductServiceItemVariant::query()->create([
            'product_id' => $productId,
            'variant_type' => $validated['variant_type'],
            'variant_label' => trim((string) $validated['variant_label']),
            'variant_value' => trim((string) $validated['variant_value']),
            'unit' => isset($validated['unit']) ? trim((string) $validated['unit']) : null,
            'sku_suffix' => isset($validated['sku_suffix']) ? trim((string) $validated['sku_suffix']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'creator_id' => Auth::id(),
            'created_by' => $tenantId,
        ]);

        return back()->with('success', __('Product variant added successfully.'));
    }

    public function variantsUpdate(Request $request, ProductServiceItemVariant $variant)
    {
        if (!Auth::user()->can('edit-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $variant->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'variant_type' => ['required', 'in:count,denier,shade,width,generic'],
            'variant_label' => ['required', 'string', 'max:120'],
            'variant_value' => ['required', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:30'],
            'sku_suffix' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $variant->update([
            'variant_type' => $validated['variant_type'],
            'variant_label' => trim((string) $validated['variant_label']),
            'variant_value' => trim((string) $validated['variant_value']),
            'unit' => isset($validated['unit']) ? trim((string) $validated['unit']) : null,
            'sku_suffix' => isset($validated['sku_suffix']) ? trim((string) $validated['sku_suffix']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('Product variant updated successfully.'));
    }

    public function variantsDestroy(ProductServiceItemVariant $variant)
    {
        if (!Auth::user()->can('delete-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $variant->created_by === (int) creatorId(), 403);

        $variant->delete();

        return back()->with('success', __('Product variant deleted successfully.'));
    }

    public function imagesIndex()
    {
        if (!Auth::user()->can('manage-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = creatorId();

        $images = ProductServiceItemImage::query()
            ->with(['product:id,name,sku,type'])
            ->where('created_by', $tenantId)
            ->latest('id')
            ->get();

        return Inertia::render('ProductService/ProductMaster/Images', [
            'images' => $images,
            'items' => $this->itemOptions(),
            'stats' => [
                'total' => $images->count(),
                'primary' => $images->where('is_primary', true)->count(),
                'active' => $images->where('is_active', true)->count(),
                'inactive' => $images->where('is_active', false)->count(),
            ],
        ]);
    }

    public function imagesStore(Request $request)
    {
        if (!Auth::user()->can('create-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = creatorId();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'image_path' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $productId = ProductServiceItem::query()
            ->where('id', (int) $validated['product_id'])
            ->where('created_by', $tenantId)
            ->value('id');

        if (!$productId) {
            return back()->withErrors(['product_id' => __('Selected item is not available for this company.')]);
        }

        if ((bool) ($validated['is_primary'] ?? false)) {
            ProductServiceItemImage::query()
                ->where('created_by', $tenantId)
                ->where('product_id', $productId)
                ->update(['is_primary' => false]);
        }

        ProductServiceItemImage::query()->create([
            'product_id' => $productId,
            'image_path' => trim((string) $validated['image_path']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'creator_id' => Auth::id(),
            'created_by' => $tenantId,
        ]);

        return back()->with('success', __('Product image added successfully.'));
    }

    public function imagesUpdate(Request $request, ProductServiceItemImage $image)
    {
        if (!Auth::user()->can('edit-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $image->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'image_path' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ((bool) ($validated['is_primary'] ?? false)) {
            ProductServiceItemImage::query()
                ->where('created_by', creatorId())
                ->where('product_id', $image->product_id)
                ->where('id', '!=', $image->id)
                ->update(['is_primary' => false]);
        }

        $image->update([
            'image_path' => trim((string) $validated['image_path']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('Product image updated successfully.'));
    }

    public function imagesDestroy(ProductServiceItemImage $image)
    {
        if (!Auth::user()->can('delete-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $image->created_by === (int) creatorId(), 403);

        $image->delete();

        return back()->with('success', __('Product image deleted successfully.'));
    }

    public function documentsIndex()
    {
        if (!Auth::user()->can('manage-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = creatorId();

        $documents = ProductServiceItemDocument::query()
            ->with(['product:id,name,sku,type'])
            ->where('created_by', $tenantId)
            ->latest('id')
            ->get();

        return Inertia::render('ProductService/ProductMaster/Documents', [
            'documents' => $documents,
            'items' => $this->itemOptions(),
            'stats' => [
                'total' => $documents->count(),
                'active' => $documents->where('is_active', true)->count(),
                'inactive' => $documents->where('is_active', false)->count(),
                'expiringSoon' => $documents->filter(fn ($row) => $row->expires_on !== null && $row->expires_on->between(now(), now()->addDays(30)))->count(),
            ],
        ]);
    }

    public function documentsStore(Request $request)
    {
        if (!Auth::user()->can('create-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = creatorId();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'document_type' => ['required', 'in:spec_sheet,test_certificate,compliance_certificate,msds,other'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_path' => ['required', 'string', 'max:255'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $productId = ProductServiceItem::query()
            ->where('id', (int) $validated['product_id'])
            ->where('created_by', $tenantId)
            ->value('id');

        if (!$productId) {
            return back()->withErrors(['product_id' => __('Selected item is not available for this company.')]);
        }

        ProductServiceItemDocument::query()->create([
            'product_id' => $productId,
            'document_type' => $validated['document_type'],
            'document_number' => isset($validated['document_number']) ? trim((string) $validated['document_number']) : null,
            'document_path' => trim((string) $validated['document_path']),
            'issued_on' => $validated['issued_on'] ?? null,
            'expires_on' => $validated['expires_on'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'creator_id' => Auth::id(),
            'created_by' => $tenantId,
        ]);

        return back()->with('success', __('Product document added successfully.'));
    }

    public function documentsUpdate(Request $request, ProductServiceItemDocument $document)
    {
        if (!Auth::user()->can('edit-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $document->created_by === (int) creatorId(), 403);

        $validated = $request->validate([
            'document_type' => ['required', 'in:spec_sheet,test_certificate,compliance_certificate,msds,other'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_path' => ['required', 'string', 'max:255'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $document->update([
            'document_type' => $validated['document_type'],
            'document_number' => isset($validated['document_number']) ? trim((string) $validated['document_number']) : null,
            'document_path' => trim((string) $validated['document_path']),
            'issued_on' => $validated['issued_on'] ?? null,
            'expires_on' => $validated['expires_on'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', __('Product document updated successfully.'));
    }

    public function documentsDestroy(ProductServiceItemDocument $document)
    {
        if (!Auth::user()->can('delete-product-service-item')) {
            return back()->with('error', __('Permission denied'));
        }

        abort_unless((int) $document->created_by === (int) creatorId(), 403);

        $document->delete();

        return back()->with('success', __('Product document deleted successfully.'));
    }

    private function itemOptions()
    {
        return ProductServiceItem::query()
            ->where('created_by', creatorId())
            ->select('id', 'name', 'sku', 'type')
            ->orderBy('name')
            ->get();
    }
}
