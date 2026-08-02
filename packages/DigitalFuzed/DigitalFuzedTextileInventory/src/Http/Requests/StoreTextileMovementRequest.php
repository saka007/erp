<?php

namespace DigitalFuzed\TextileInventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTextileMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', 'string', 'max:100'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'location_from' => ['nullable', 'string', 'max:100'],
            'location_to' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
