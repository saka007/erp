<?php

namespace Workdo\ProductService\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductServiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255',
            'tax_ids' => 'required|array',
            'category_id' => 'required|exists:product_service_categories,id',
            'description' => 'nullable|string',
            'long_description' => 'nullable|string',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'quantity' => 'nullable|integer|min:0',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'cone_number' => 'nullable|string|max:100',
            'cone_weight' => 'nullable|numeric|min:0',
            'yarn_barcode' => 'nullable|string|max:140',
            'yarn_qr_code' => 'nullable|string|max:255',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'type' => 'nullable|string|max:255',
        ];
    }
}
