<?php

namespace DigitalFuzed\TextileInventory\Services;

use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
use Illuminate\Support\Facades\Log;

/**
 * Consumption + traceability wiring for the textile manufacturing flow.
 *
 * Slice A of the Smart Inventory Insights plan:
 *  - reserveYarnForAllocation : yarn allocation commits yarn stock (reservation)
 *  - issueYarnForBeam         : beam receipt consumes yarn (issue movement)
 *  - linkParentLot            : record parent_lot_reference on derived lots
 *
 * All methods are FAIL-OPEN: when the upstream lot does not exist (legacy data,
 * seeded demos, or tests that skip lot creation) they log a warning and return
 * without throwing, so the manufacturing workflow keeps working.
 */
class TextileConsumptionService
{
    public function __construct(
        protected TextileAvailabilityService $availabilityService,
        protected TextileMovementService $movementService
    ) {
    }

    /**
     * Reserve yarn from the source yarn lot for a yarn allocation.
     * Fail-open: no-op when the yarn lot is missing or has nothing available.
     *
     * @return TextileReservation|null
     */
    public function reserveYarnForAllocation(
        string $yarnLotReference,
        float $quantity,
        string $referenceType = 'yarn_allocation',
        ?int $referenceId = null,
        ?int $tenantId = null,
    ): ?TextileReservation {
        if ($yarnLotReference === '' || $quantity <= 0) {
            return null;
        }

        $lot = $this->findLot($yarnLotReference, $tenantId);
        if ($lot === null) {
            Log::info("TextileConsumption: yarn lot '{$yarnLotReference}' not found — allocation reservation skipped (fail-open).");

            return null;
        }

        $available = (float) $lot->available_quantity;
        if ($available <= 0) {
            Log::info("TextileConsumption: yarn lot '{$yarnLotReference}' has no available stock — allocation reservation skipped (fail-open).");

            return null;
        }

        // Reserve only what is actually available; never over-commit.
        $reserveQuantity = min($quantity, $available);

        try {
            return $this->availabilityService->reserve(
                $yarnLotReference,
                $reserveQuantity,
                $referenceType,
                $referenceId,
            );
        } catch (\RuntimeException $e) {
            Log::warning("TextileConsumption: reservation failed for '{$yarnLotReference}': {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Consume yarn from the source yarn lot when a beam is received.
     * Posts an issue movement (fail-open when the yarn lot is missing).
     *
     * When $reservationReferenceType/$reservationReferenceId point to an active
     * reservation (yarn was reserved at allocation time, so available_quantity
     * was already reduced), the reservation is fulfilled instead of decrementing
     * the lot again — avoiding double consumption.
     */
    public function issueYarnForBeam(
        string $yarnLotReference,
        float $quantity,
        string $unit,
        string $referenceType = 'beam',
        ?int $referenceId = null,
        ?int $tenantId = null,
        ?string $reservationReferenceType = null,
        ?int $reservationReferenceId = null,
    ): bool {
        if ($yarnLotReference === '' || $quantity <= 0) {
            return false;
        }

        $lot = $this->findLot($yarnLotReference, $tenantId);
        if ($lot === null) {
            Log::info("TextileConsumption: yarn lot '{$yarnLotReference}' not found — beam issue skipped (fail-open).");

            return false;
        }

        $reservation = $this->findActiveReservation(
            $yarnLotReference,
            $reservationReferenceType,
            $reservationReferenceId,
            $tenantId,
        );

        $issueQuantity = $quantity;

        if ($reservation !== null) {
            // Yarn already committed via reservation — fulfill it, no extra decrement.
            $issueQuantity = min($quantity, (float) $reservation->reserved_quantity);
            $reservation->status = 'consumed';
            $reservation->is_active = false;
            $reservation->save();
        } else {
            $issueQuantity = min($quantity, (float) $lot->available_quantity);
        }

        if ($issueQuantity <= 0) {
            Log::info("TextileConsumption: yarn lot '{$yarnLotReference}' has no available stock — beam issue skipped (fail-open).");

            return false;
        }

        $this->movementService->createMovement([
            'movement_type' => 'issue',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'lot_reference' => $yarnLotReference,
            'location_from' => 'warehouse',
            'location_to' => 'sizing',
            'quantity' => $issueQuantity,
            'unit' => $unit,
            'status' => 'posted',
            'notes' => 'Yarn issued for beam preparation (Smart Inventory Insights).',
        ]);

        if ($reservation === null) {
            // Direct consumption (no prior reservation) — decrement the lot.
            $lot->available_quantity = max(0, (float) $lot->available_quantity - $issueQuantity);
            $lot->save();
        }

        return true;
    }

    /**
     * Mark a parent lot link on a derived lot (fail-open: no-op if the derived
     * lot is missing).
     */
    public function linkParentLot(
        string $derivedLotReference,
        ?string $parentLotReference,
        ?string $parentLotType,
        ?int $tenantId = null,
    ): void {
        if ($derivedLotReference === '' || $parentLotReference === '') {
            return;
        }

        $lot = $this->findLot($derivedLotReference, $tenantId);
        if ($lot === null) {
            return;
        }

        if ((string) ($lot->parent_lot_reference ?? '') === $parentLotReference) {
            return;
        }

        $lot->parent_lot_reference = $parentLotReference;
        $lot->parent_lot_type = $parentLotType;
        $lot->save();
    }

    private function findLot(string $lotReference, ?int $tenantId): ?TextileLot
    {
        return TextileLot::query()
            ->where('lot_reference', $lotReference)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->first();
    }

    private function findActiveReservation(
        string $lotReference,
        ?string $referenceType,
        ?int $referenceId,
        ?int $tenantId,
    ): ?TextileReservation {
        if ($referenceType === null || $referenceId === null) {
            return null;
        }

        return TextileReservation::query()
            ->where('lot_reference', $lotReference)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }
}
