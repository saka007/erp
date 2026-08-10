<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $data = $this->all();
        if (($data['same_as_billing'] ?? false)) {
            $data['shipping_address'] = $data['billing_address'] ?? [];
            $this->merge($data);
        }
    }

    public function rules()
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'company_name' => 'required|string|max:255',
            'contact_person_name' => 'required|string|max:255',
            'contact_person_email' => 'required|email|max:255',
            'contact_person_mobile' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_days' => 'nullable|integer|min:0|max:3650',
            'credit_enabled' => 'nullable|boolean',
            'default_rate' => 'nullable|numeric|min:0',
            'currency_code' => 'nullable|string|max:10',
            'customer_category_id' => 'nullable|integer|min:1',
            'operating_model' => 'nullable|string|in:full_package_buyer,jobwork_weaving_beam_supplied,jobwork_processing_grey_supplied,trader_bulk,export_compliance',
            'material_ownership' => 'nullable|string|in:company_owned,customer_owned,mixed',
            'billing_mode' => 'nullable|string|in:sale_value,conversion_charge,process_charge,hybrid',
            'billing_address.name' => 'required|string|max:255',
            'billing_address.address_line_1' => 'required|string|max:255',
            'billing_address.address_line_2' => 'nullable|string|max:255',
            'billing_address.city' => 'required|string|max:255',
            'billing_address.state' => 'required|string|max:255',
            'billing_address.country' => 'required|string|max:255',
            'billing_address.zip_code' => 'required|string|max:20',
            'shipping_address.name' => 'required_if:same_as_billing,false|string|max:255',
            'shipping_address.address_line_1' => 'required_if:same_as_billing,false|string|max:255',
            'shipping_address.address_line_2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required_if:same_as_billing,false|string|max:255',
            'shipping_address.state' => 'required_if:same_as_billing,false|string|max:255',
            'shipping_address.country' => 'required_if:same_as_billing,false|string|max:255',
            'shipping_address.zip_code' => 'required_if:same_as_billing,false|string|max:20',
            'same_as_billing' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}