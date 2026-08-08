<?php

namespace Tests\Feature\Textile;

use App\Models\AddOn;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Hrm\Models\Branch;

/**
 * Phase 3C — Slice A: Stock consumption & lot traceability.
 *
 * Verifies that the manufacturing flow now consumes/reserves yarn and builds
 * the lot traceability chain:
 *
 *   yarn lot ──reserve/issue──▶ beam lot ──parent──▶ grey fabric lot ──parent──▶ takha lot
 *
 * - Yarn allocation reserves yarn (available_quantity drops, reservation row).
 * - Beam receipt from a yarn allocation issues yarn (no double decrement) and
 *   links the beam lot to the source yarn lot.
 * - Weaving output creates its OWN grey lot reference (collision fix: the beam
 *   lot no longer swallows the grey lot) and links it to the beam lot.
 * - Takha entries create per-takha grey lots linked to the weaving output lot.
 */
class TextileInventoryConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_yarn_allocation_reserves_yarn_and_beam_receipt_links_lots(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Consumption Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        // Simulate an incoming-QC pass (approved) so the warp-plan gate accepts the lot.
        $incomingQc = TextileWorkflowDocument::create([
            'document_type' => 'incoming_qc',
            'document_number' => 'IQC-C1',
            'lot_reference' => 'YARN-LOT-C1',
            'quantity' => 500,
            'unit' => 'kg',
            'status' => 'approved',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
            'branch_id' => $branchA->id,
        ]);

        // Seed a yarn lot as if it arrived via GRN + incoming-QC pass.
        $yarnLot = TextileLot::create([
            'lot_reference' => 'YARN-LOT-C1',
            'received_quantity' => 500,
            'available_quantity' => 500,
            'status' => 'active',
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'source_document_type' => 'incoming_qc',
            'source_document_id' => $incomingQc->id,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        // Warp plan referencing the yarn lot, then approve it.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => $yarnLot->id,
                'source_action' => 'warp_plan',
                'party_name' => 'Warp Unit C',
                'lot_reference' => 'YARN-LOT-C1',
                'quantity' => 200,
                'unit' => 'kg',
            ])
            ->assertSessionHasNoErrors();

        $warpPlan = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'warp_plan')
            ->latest('id')
            ->first();

        $this->assertNotNull($warpPlan);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.warp-plans.approve'), [
                'warp_plan_id' => $warpPlan->id,
            ])
            ->assertSessionHasNoErrors();

        // Yarn allocation should reserve 200kg from the yarn lot.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.yarn-allocations.store'), [
                'warp_plan_id' => $warpPlan->id,
            ])
            ->assertSessionHasNoErrors();

        $yarnAllocation = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'yarn_allocation')
            ->latest('id')
            ->first();

        $this->assertNotNull($yarnAllocation);
        $this->assertSame('approved', $yarnAllocation->status);

        $yarnLot->refresh();
        $this->assertEquals(300, (float) $yarnLot->available_quantity);

        $reservation = TextileReservation::query()
            ->where('created_by', $companyA->id)
            ->where('lot_reference', 'YARN-LOT-C1')
            ->where('reference_type', 'yarn_allocation')
            ->where('reference_id', $yarnAllocation->id)
            ->first();

        $this->assertNotNull($reservation);
        $this->assertEquals(200, (float) $reservation->reserved_quantity);
        $this->assertSame('reserved', $reservation->status);

        // Beam receipt consumes the reserved yarn (fulfills reservation, no double decrement).
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.from-yarn-allocation'), [
                'yarn_allocation_id' => $yarnAllocation->id,
                'quantity' => 180,
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->where('source_reference_id', $yarnAllocation->id)
            ->first();

        $this->assertNotNull($beam);
        $this->assertSame('BEAM-' . str_pad((string) $yarnAllocation->id, 6, '0', STR_PAD_LEFT), $beam->lot_reference);

        // Beam lot exists and traces back to the yarn lot.
        $beamLot = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('lot_reference', $beam->lot_reference)
            ->first();

        $this->assertNotNull($beamLot);
        $this->assertSame(TextileLot::TYPE_BEAM, $beamLot->material_type);
        $this->assertSame('YARN-LOT-C1', $beamLot->parent_lot_reference);
        $this->assertSame(TextileLot::TYPE_YARN, $beamLot->parent_lot_type);

        // Issue movement posted for the yarn consumption.
        $issueMovement = TextileMovement::query()
            ->where('created_by', $companyA->id)
            ->where('movement_type', 'issue')
            ->where('lot_reference', 'YARN-LOT-C1')
            ->where('reference_type', 'beam')
            ->where('reference_id', $beam->id)
            ->first();

        $this->assertNotNull($issueMovement);
        $this->assertEquals(180, (float) $issueMovement->quantity);

        // Available yarn stays at 300 — the reservation already committed the stock.
        $yarnLot->refresh();
        $this->assertEquals(300, (float) $yarnLot->available_quantity);

        $reservation->refresh();
        $this->assertSame('consumed', $reservation->status);
        $this->assertFalse((bool) $reservation->is_active);
    }

    public function test_weaving_output_creates_own_grey_lot_and_takha_links_to_it(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Weaving Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        // Seed a yarn lot + beam (beam approved) to reach the weaving stage quickly.
        TextileLot::create([
            'lot_reference' => 'YARN-LOT-W1',
            'received_quantity' => 300,
            'available_quantity' => 300,
            'status' => 'active',
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => TextileLot::query()->where('lot_reference', 'YARN-LOT-W1')->value('id'),
                'source_action' => 'beam_prepare',
                'party_name' => 'Weaving Unit W',
                'lot_reference' => 'BEAM-W1',
                'quantity' => 240,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->latest('id')
            ->first();

        $this->assertNotNull($beam);
        $this->assertSame('BEAM-W1', $beam->lot_reference);

        // Beam created directly from a yarn lot: yarn is consumed + beam lot linked.
        $beamLot = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('lot_reference', 'BEAM-W1')
            ->first();

        $this->assertNotNull($beamLot);
        $this->assertSame('YARN-LOT-W1', $beamLot->parent_lot_reference);
        $this->assertSame(TextileLot::TYPE_YARN, $beamLot->parent_lot_type);

        $yarnLot = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('lot_reference', 'YARN-LOT-W1')
            ->first();

        $this->assertEquals(60, (float) $yarnLot->available_quantity);

        // Approve beam → batch → release.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.approve'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.store'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $batch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_batch')
            ->latest('id')
            ->first();

        $this->assertNotNull($batch);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.release'), [
                'batch_id' => $batch->id,
            ])
            ->assertSessionHasNoErrors();

        // Weaving output: grey lot gets its OWN reference (not swallowed by beam lot).
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.weaving-output.store'), [
                'batch_id' => $batch->id,
                'quantity' => 220,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $output = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'weaving_output')
            ->latest('id')
            ->first();

        $this->assertNotNull($output);

        // The batch reuses the beam's lot_reference — a dedicated grey lot must exist.
        $greyLot = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
            ->where('source_document_type', 'weaving_output')
            ->where('source_document_id', $output->id)
            ->first();

        $this->assertNotNull($greyLot);
        $this->assertNotSame('BEAM-W1', $greyLot->lot_reference);
        $this->assertStringStartsWith('GREY-', $greyLot->lot_reference);
        $this->assertSame('BEAM-W1', $greyLot->parent_lot_reference);
        $this->assertSame(TextileLot::TYPE_BEAM, $greyLot->parent_lot_type);
        $this->assertEquals(220, (float) $greyLot->available_quantity);

        // Beam lot is untouched by the weaving output (still a beam).
        $beamLot->refresh();
        $this->assertSame(TextileLot::TYPE_BEAM, $beamLot->material_type);
        $this->assertEquals(240, (float) $beamLot->available_quantity);

        // Takha entry creates a grey lot linked to the weaving-output grey lot.
        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.takha-entries.store'), [
                'weaving_output_id' => $output->id,
                'takha_number' => 'TAKHA-W1-01',
                'quantity' => 80,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $takhaLot = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('lot_reference', 'TAKHA-W1-01')
            ->first();

        $this->assertNotNull($takhaLot);
        $this->assertSame(TextileLot::TYPE_GREY_FABRIC, $takhaLot->material_type);
        $this->assertSame($greyLot->lot_reference, $takhaLot->parent_lot_reference);
        $this->assertSame(TextileLot::TYPE_GREY_FABRIC, $takhaLot->parent_lot_type);
        $this->assertEquals(80, (float) $takhaLot->available_quantity);
    }

    public function test_weaving_output_lot_creation_is_idempotent_for_same_document(): void
    {
        $companyA = $this->company();
        $branchA = Branch::create([
            'branch_name' => 'Idempotency Branch',
            'creator_id' => $companyA->id,
            'created_by' => $companyA->id,
        ]);
        $this->withSession(['active_branch_id' => $branchA->id]);

        TextileLot::create([
            'lot_reference' => 'YARN-LOT-I1',
            'received_quantity' => 100,
            'available_quantity' => 100,
            'status' => 'active',
            'material_type' => TextileLot::TYPE_YARN,
            'production_stage' => TextileLot::STAGE_PROCUREMENT,
            'is_active' => true,
            'created_by' => $companyA->id,
            'creator_id' => $companyA->id,
        ]);

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.store'), [
                'source_reference_type' => 'textile_lot',
                'source_reference_id' => TextileLot::query()->where('lot_reference', 'YARN-LOT-I1')->value('id'),
                'source_action' => 'beam_prepare',
                'party_name' => 'Idem Unit I',
                'lot_reference' => 'BEAM-I1',
                'quantity' => 90,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $beam = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'beam')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.beams.approve'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.store'), [
                'beam_id' => $beam->id,
            ])
            ->assertSessionHasNoErrors();

        $batch = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'production_batch')
            ->latest('id')
            ->first();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.batches.release'), [
                'batch_id' => $batch->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($companyA)
            ->post(route('textile.manufacturing.weaving-output.store'), [
                'batch_id' => $batch->id,
                'quantity' => 80,
                'unit' => 'mtr',
            ])
            ->assertSessionHasNoErrors();

        $output = TextileWorkflowDocument::query()
            ->where('created_by', $companyA->id)
            ->where('document_type', 'weaving_output')
            ->latest('id')
            ->first();

        $this->assertNotNull($output);

        $greyCount = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
            ->where('source_document_type', 'weaving_output')
            ->where('source_document_id', $output->id)
            ->count();

        $this->assertSame(1, $greyCount);

        // Calling the auto-creation hook again for the same document must NOT
        // create a second grey lot (idempotent by source document).
        $this->app->make(\DigitalFuzed\TextileInventory\Services\TextileLotAutoCreationService::class)
            ->createFromWeavingOutput($output, 'BEAM-I1', TextileLot::TYPE_BEAM);

        $greyCountAfter = TextileLot::query()
            ->where('created_by', $companyA->id)
            ->where('material_type', TextileLot::TYPE_GREY_FABRIC)
            ->where('source_document_type', 'weaving_output')
            ->where('source_document_id', $output->id)
            ->count();

        $this->assertSame(1, $greyCountAfter);
    }

    private function company(): User
    {
        AddOn::create([
            'module' => 'TextileCore',
            'name' => 'Textile Core',
            'package_name' => 'textile-core',
            'is_enable' => true,
            'monthly_price' => 0,
            'yearly_price' => 0,
        ]);

        $plan = Plan::create([
            'name' => 'Textile Smart Inventory Plan',
            'modules' => ['TextileCore', 'TextileInventory'],
        ]);

        $company = User::factory()->create([
            'type' => 'company',
            'active_plan' => $plan->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate([
            'name' => 'company',
            'guard_name' => 'web',
        ], [
            'label' => 'Company',
            'created_by' => $company->id,
        ]);

        $company->assignRole($role);

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileCore',
        ]);

        UserActiveModule::create([
            'user_id' => $company->id,
            'module' => 'TextileInventory',
        ]);

        return $company;
    }
}
