<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Workdo\Account\Models\Customer;
use Workdo\Hrm\Models\Branch;
use Workdo\Quotation\Http\Requests\StoreQuotationRequest;
use Workdo\Quotation\Http\Requests\UpdateQuotationRequest;

class QuotationDuplicateLotValidationTest extends TestCase
{
    use RefreshDatabase;

    private int $customerId;

    private int $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $branch = Branch::create(['branch_name' => 'Quotation Branch', 'creator_id' => $company->id, 'created_by' => $company->id]);

        $this->customerId = Customer::create([
            'company_name' => 'Validation Test Co',
            'contact_person_name' => 'Tester',
            'contact_person_email' => 'tester@validation.test',
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ])->id;

        $this->warehouseId = Warehouse::create([
            'name' => 'Validation Warehouse',
            'address' => 'Test Street 1',
            'city' => 'Test City',
            'zip_code' => '12345',
            'phone' => '1234567890',
            'email' => 'wh@validation.test',
            'branch_id' => $branch->id,
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ])->id;
    }

    private function validateItems(array $items, string $type = 'takha')
    {
        $request = new StoreQuotationRequest();
        $request->merge([
            'invoice_date' => '2026-08-10',
            'due_date' => '2026-08-20',
            'customer_id' => $this->customerId,
            'warehouse_id' => $this->warehouseId,
            'quotation_type' => $type,
            'items' => $items,
        ]);

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        return $validator;
    }

    public function test_duplicate_lot_reference_is_rejected(): void
    {
        $validator = $this->validateItems([
            ['product_id' => 1, 'product_type' => 'lot', 'lot_reference' => 'TAK-LOT-0001', 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => 2, 'product_type' => 'lot', 'lot_reference' => 'TAK-LOT-0001', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.1.lot_reference', $validator->errors()->toArray());
    }

    public function test_unique_lot_references_pass(): void
    {
        $validator = $this->validateItems([
            ['product_id' => 1, 'product_type' => 'lot', 'lot_reference' => 'TAK-LOT-0001', 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => 2, 'product_type' => 'lot', 'lot_reference' => 'TAK-LOT-0002', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_product_rows_with_null_lot_reference_pass(): void
    {
        $validator = $this->validateItems([
            ['product_id' => 1, 'product_type' => 'product', 'lot_reference' => null, 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => 2, 'product_type' => 'product', 'lot_reference' => null, 'quantity' => 1, 'unit_price' => 100],
        ], 'general');

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_also_rejects_duplicate_lot_reference(): void
    {
        $request = new UpdateQuotationRequest();
        $request->merge([
            'invoice_date' => '2026-08-10',
            'due_date' => '2026-08-20',
            'customer_id' => $this->customerId,
            'warehouse_id' => $this->warehouseId,
            'quotation_type' => 'takha',
            'items' => [
                ['product_id' => 1, 'product_type' => 'lot', 'lot_reference' => 'TAK-LOT-0001', 'quantity' => 1, 'unit_price' => 100],
                ['product_id' => 2, 'product_type' => 'lot', 'lot_reference' => 'TAK-LOT-0001', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.1.lot_reference', $validator->errors()->toArray());
    }
}
