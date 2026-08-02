<?php

namespace Tests\Feature\Textile;

use App\Models\User;
use DigitalFuzed\TextileCore\Services\TextileWorkflowService;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Services\TextileAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextileTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_queries_are_tenant_scoped(): void
    {
        $companyA = User::factory()->create([
            'type' => 'company',
            'created_by' => null,
        ]);

        $companyB = User::factory()->create([
            'type' => 'company',
            'created_by' => null,
        ]);

        $staffA = User::factory()->create([
            'type' => 'staff',
            'created_by' => $companyA->id,
        ]);

        $staffB = User::factory()->create([
            'type' => 'staff',
            'created_by' => $companyB->id,
        ]);

        $workflow = app(TextileWorkflowService::class);

        $this->actingAs($staffA);
        $workflow->createDocument(['document_type' => 'sales_order', 'quantity' => 12]);
        $workflow->createDocument(['document_type' => 'sales_order', 'quantity' => 5]);

        $this->actingAs($staffB);
        $workflow->createDocument(['document_type' => 'sales_order', 'quantity' => 2]);

        $this->actingAs($staffA);
        $aItems = $workflow->listByType('sales_order');
        $aSummary = $workflow->summary();

        $this->assertCount(2, $aItems);
        $this->assertSame(2, $aSummary['total_documents']);
        $this->assertTrue($aItems->every(fn ($item) => (int) $item->created_by === (int) $companyA->id));

        $this->actingAs($staffB);
        $bItems = $workflow->listByType('sales_order');
        $bSummary = $workflow->summary();

        $this->assertCount(1, $bItems);
        $this->assertSame(1, $bSummary['total_documents']);
        $this->assertTrue($bItems->every(fn ($item) => (int) $item->created_by === (int) $companyB->id));
    }

    public function test_lot_availability_is_tenant_scoped(): void
    {
        $companyA = User::factory()->create([
            'type' => 'company',
            'created_by' => null,
        ]);

        $companyB = User::factory()->create([
            'type' => 'company',
            'created_by' => null,
        ]);

        $staffA = User::factory()->create([
            'type' => 'staff',
            'created_by' => $companyA->id,
        ]);

        $staffB = User::factory()->create([
            'type' => 'staff',
            'created_by' => $companyB->id,
        ]);

        TextileLot::create([
            'created_by' => $companyA->id,
            'creator_id' => $staffA->id,
            'lot_reference' => 'LOT-SHARED',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
        ]);

        TextileLot::create([
            'created_by' => $companyB->id,
            'creator_id' => $staffB->id,
            'lot_reference' => 'LOT-SHARED',
            'received_quantity' => 80,
            'available_quantity' => 80,
            'status' => 'active',
        ]);

        $availability = new TextileAvailabilityService();

        $this->actingAs($staffA);
        $availability->reserve('LOT-SHARED', 15, 'sales_order', 10);
        $aState = $availability->getAvailability('LOT-SHARED');

        $this->actingAs($staffB);
        $availability->reserve('LOT-SHARED', 20, 'sales_order', 11);
        $bState = $availability->getAvailability('LOT-SHARED');

        $this->assertSame(85.0, (float) $aState['available_quantity']);
        $this->assertSame(15.0, (float) $aState['reserved_quantity']);

        $this->assertSame(60.0, (float) $bState['available_quantity']);
        $this->assertSame(20.0, (float) $bState['reserved_quantity']);
    }
}
