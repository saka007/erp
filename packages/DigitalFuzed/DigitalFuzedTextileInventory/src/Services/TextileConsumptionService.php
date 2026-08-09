<?php

namespace DigitalFuzed\TextileInventory\Services;

use DigitalFuzed\TextileCore\Models\TextileUnitConversion;
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
        string $locationTo = 'sizing',
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
            'location_to' => $locationTo,
            'quantity' => $issueQuantity,
            'unit' => $unit,
            'status' => 'posted',
            'notes' => $locationTo === 'sizing-vendor'
                ? 'Yarn issued to sizing vendor (outsourced sizing).'
                : 'Yarn issued for beam preparation (in-house sizing).',
        ]);

        if ($reservation === null) {
            // Direct consumption (no prior reservation) — decrement the lot.
            $lot->available_quantity = max(0, (float) $lot->available_quantity - $issueQuantity);
            $lot->save();
        }

        return true;
    }

    /**
     * Reserve beam stock when part of a beam is assigned for production.
     *
     * A production assignment commits a portion of the beam to manufacturing:
     * the committed quantity is reserved from the beam lot, so inventory shows
     * the remaining (uncommitted) beam quantity as available. This mirrors
     * reserveYarnForAllocation. Fail-open: no-op when the beam lot is missing
     * or has nothing available.
     *
     * @return TextileReservation|null
     */
    public function reserveBeamForAssignment(
        string $beamLotReference,
        float $quantity,
        string $referenceType = 'production_assignment',
        ?int $referenceId = null,
        ?int $tenantId = null,
    ): ?TextileReservation {
        if ($beamLotReference === '' || $quantity <= 0) {
            return null;
        }

        $lot = $this->findLot($beamLotReference, $tenantId);
        if ($lot === null) {
            Log::info("TextileConsumption: beam lot '{$beamLotReference}' not found — assignment reservation skipped (fail-open).");

            return null;
        }

        $available = (float) $lot->available_quantity;
        if ($available <= 0) {
            Log::info("TextileConsumption: beam lot '{$beamLotReference}' has no available stock — assignment reservation skipped (fail-open).");

            return null;
        }

        // Reserve only what is actually available; never over-commit.
        $reserveQuantity = min($quantity, $available);

        try {
            return $this->availabilityService->reserve(
                $beamLotReference,
                $reserveQuantity,
                $referenceType,
                $referenceId,
            );
        } catch (\RuntimeException $e) {
            Log::warning("TextileConsumption: beam reservation failed for '{$beamLotReference}': {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Consume a beam lot when weaving output (grey fabric) is recorded.
     *
     * When production assignments for the batch already reserved beam stock,
     * those reservations are fulfilled instead of decrementing the lot again
     * (avoids double consumption) — the reservation already reduced
     * available_quantity at assignment time.
     *
     * In-house weaving issues the beam warehouse → weaving. Outsourced weaving
     * (powerloom vendor) issues the beam warehouse → powerloom-vendor.
     * Fail-open: no-op when the beam lot is missing or has nothing available.
     */
    public function issueBeamForWeaving(
        string $beamLotReference,
        float $quantity,
        string $unit,
        string $referenceType = 'weaving_output',
        ?int $referenceId = null,
        ?int $tenantId = null,
        bool $outsourced = false,
        array $reservationReferenceIds = [],
    ): bool {
        return $this->consumeBeamForWeaving(
            $beamLotReference,
            $quantity,
            $unit,
            $referenceType,
            $referenceId,
            $tenantId,
            $outsourced,
            $reservationReferenceIds,
        );
    }

    /**
     * Consume a beam lot for weaving, fulfilling the production-assignment
     * reservations that committed the beam to production.
     *
     * Reservations already reduced available_quantity at assignment time, so
     * the consumed quantity is drawn from the active reservations (marked
     * consumed / reduced) rather than decrementing the lot again. Any quantity
     * beyond the reserved amount (legacy data without reservations, or weaving
     * more than was assigned) is consumed directly from the lot's available
     * stock. Fail-open.
     */
    public function consumeBeamForWeaving(
        string $beamLotReference,
        float $quantity,
        string $unit,
        string $referenceType = 'weaving_output',
        ?int $referenceId = null,
        ?int $tenantId = null,
        bool $outsourced = false,
        array $reservationReferenceIds = [],
    ): bool {
        if ($beamLotReference === '' || $quantity <= 0) {
            return false;
        }

        $lot = $this->findLot($beamLotReference, $tenantId);
        if ($lot === null) {
            Log::info("TextileConsumption: beam lot '{$beamLotReference}' not found — weaving issue skipped (fail-open).");

            return false;
        }

        // Fulfill active production-assignment reservations first (fail-open).
        $consumedFromReservations = 0.0;
        $remaining = $quantity;

        if ($reservationReferenceIds !== []) {
            $reservations = TextileReservation::query()
                ->where('lot_reference', $beamLotReference)
                ->where('reference_type', 'production_assignment')
                ->whereIn('reference_id', $reservationReferenceIds)
                ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            foreach ($reservations as $reservation) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min((float) $reservation->reserved_quantity, $remaining);
                $reservation->reserved_quantity = max(0, (float) $reservation->reserved_quantity - $take);
                if ((float) $reservation->reserved_quantity <= 0) {
                    $reservation->status = 'consumed';
                    $reservation->is_active = false;
                }
                $reservation->save();
                $consumedFromReservations += $take;
                $remaining -= $take;
            }
        }

        $available = (float) $lot->available_quantity;
        $lotConsume = min($remaining, $available);

        if ($consumedFromReservations <= 0 && $lotConsume <= 0) {
            Log::info("TextileConsumption: beam lot '{$beamLotReference}' has no available stock or reservations — weaving issue skipped (fail-open).");

            return false;
        }

        $this->movementService->createMovement([
            'movement_type' => 'issue',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'lot_reference' => $beamLotReference,
            'location_from' => 'warehouse',
            'location_to' => $outsourced ? 'powerloom-vendor' : 'weaving',
            'quantity' => $quantity,
            'unit' => $unit,
            'status' => 'posted',
            'notes' => $outsourced
                ? 'Beam issued to powerloom vendor for outsourced weaving.'
                : 'Beam issued for in-house weaving.',
        ]);

        if ($lotConsume > 0) {
            $lot->available_quantity = max(0, $available - $lotConsume);
            $lot->save();
        }

        return true;
    }

    /**
     * Consume the parent grey fabric lot when a takha is cut from it.
     *
     * In-house weaving moves grey weaving → warehouse. Outsourced weaving
     * receives the takha grey back from the powerloom vendor.
     * Fail-open: no-op when the parent grey lot is missing or has nothing
     * available.
     */
    public function issueGreyForTakha(
        string $greyLotReference,
        float $quantity,
        string $unit,
        string $referenceType = 'takha_entry',
        ?int $referenceId = null,
        ?int $tenantId = null,
        bool $outsourced = false,
    ): bool {
        return $this->issueForProduction(
            $greyLotReference,
            $quantity,
            $unit,
            $referenceType,
            $referenceId,
            $tenantId,
            $outsourced ? 'powerloom-vendor' : 'weaving',
            'warehouse',
            $outsourced
                ? 'Grey fabric received from powerloom vendor takha cutting.'
                : 'Grey fabric issued for in-house takha cutting.',
        );
    }

    /**
     * Generic production consumption: post an issue movement and decrement the
     * source lot's available_quantity (fail-open).
     *
     * When the movement unit differs from the lot's recorded unit and a
     * conversion factor exists (e.g. kg → mtr), the consumed quantity is
     * converted so the lot is not over-decremented. Without a conversion the
     * raw quantity is used.
     */
    public function issueForProduction(
        string $lotReference,
        float $quantity,
        string $unit,
        string $referenceType,
        ?int $referenceId,
        ?int $tenantId,
        string $locationFrom,
        string $locationTo,
        string $notes,
    ): bool {
        if ($lotReference === '' || $quantity <= 0) {
            return false;
        }

        $lot = $this->findLot($lotReference, $tenantId);
        if ($lot === null) {
            Log::info("TextileConsumption: lot '{$lotReference}' not found — production issue skipped (fail-open).");

            return false;
        }

        $available = (float) $lot->available_quantity;
        $lotUnit = $this->resolveLotUnit($lot);

        // Convert the movement quantity into the lot's own unit when they differ.
        $consumeQuantity = $quantity;
        if ($unit !== '' && $lotUnit !== null && strtolower($lotUnit) !== strtolower($unit)) {
            $converted = $this->convertQuantity($quantity, $unit, $lotUnit, $tenantId);
            if ($converted !== null) {
                $consumeQuantity = $converted;
            }
        }

        $issueQuantity = min($consumeQuantity, $available);
        if ($issueQuantity <= 0) {
            Log::info("TextileConsumption: lot '{$lotReference}' has no available stock — production issue skipped (fail-open).");

            return false;
        }

        $this->movementService->createMovement([
            'movement_type' => 'issue',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'lot_reference' => $lotReference,
            'location_from' => $locationFrom,
            'location_to' => $locationTo,
            'quantity' => $consumeQuantity,
            'unit' => $unit,
            'status' => 'posted',
            'notes' => $notes,
        ]);

        $lot->available_quantity = max(0, $available - $issueQuantity);
        $lot->save();

        return true;
    }

    /**
     * Convert a quantity from one unit to another using the tenant's active
     * conversion table (fail-open: null when no conversion is defined).
     */
    private function convertQuantity(float $quantity, string $fromUnit, string $toUnit, ?int $tenantId): ?float
    {
        if ($fromUnit === '' || $toUnit === '' || $fromUnit === $toUnit) {
            return null;
        }

        $conversion = TextileUnitConversion::query()
            ->where('from_unit', $fromUnit)
            ->where('to_unit', $toUnit)
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->first();

        if ($conversion !== null) {
            return $quantity * (float) $conversion->factor;
        }

        // Reverse conversion (to_unit → from_unit) divides by the factor.
        $reverse = TextileUnitConversion::query()
            ->where('from_unit', $toUnit)
            ->where('to_unit', $fromUnit)
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->first();

        if ($reverse !== null && (float) $reverse->factor > 0) {
            return $quantity / (float) $reverse->factor;
        }

        return null;
    }

    /**
     * Resolve the unit a lot is recorded in from its source workflow document
     * (lots don't carry a unit column). Fail-open: null when unknown.
     */
    private function resolveLotUnit(TextileLot $lot): ?string
    {
        $sourceType = (string) ($lot->source_document_type ?? '');
        $sourceId = (int) ($lot->source_document_id ?? 0);

        if ($sourceType === '' || $sourceId <= 0) {
            return null;
        }

        if (! class_exists(\DigitalFuzed\TextileCore\Models\TextileWorkflowDocument::class)) {
            return null;
        }

        $source = \DigitalFuzed\TextileCore\Models\TextileWorkflowDocument::query()
            ->where('created_by', $lot->created_by)
            ->where('document_type', $sourceType)
            ->where('id', $sourceId)
            ->first();

        if ($source === null) {
            return null;
        }

        $unit = (string) ($source->unit ?? '');

        return $unit !== '' ? $unit : null;
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
