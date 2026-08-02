<?php

namespace DigitalFuzed\TextileInventory\Services;

use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TextileAvailabilityService
{
    public function reserve(string $lotReference, float $quantity, ?string $referenceType = null, ?int $referenceId = null): TextileReservation
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        $lot = TextileLot::firstOrCreate(
            ['created_by' => $tenantId, 'lot_reference' => $lotReference],
            [
                'creator_id' => auth()->id(),
                'received_quantity' => 0,
                'available_quantity' => 0,
                'status' => 'active',
            ]
        );

        if ((float) $lot->available_quantity < $quantity) {
            throw new RuntimeException('Insufficient available quantity for reservation.');
        }

        $reservation = TextileReservation::create([
            'lot_reference' => $lotReference,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reserved_quantity' => $quantity,
            'status' => 'reserved',
            'is_active' => true,
            'creator_id' => auth()->id(),
            'created_by' => $tenantId,
        ]);

        $lot->available_quantity = max(0, (float) $lot->available_quantity - $quantity);
        $lot->save();

        return $reservation;
    }

    public function getAvailability(string $lotReference): array
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        $lot = TextileLot::where('lot_reference', $lotReference)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->first();

        $reserved = TextileReservation::where('lot_reference', $lotReference)
            ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
            ->where('is_active', true)
            ->sum('reserved_quantity');

        return [
            'lot_reference' => $lotReference,
            'received_quantity' => $lot?->received_quantity ?? 0,
            'available_quantity' => $lot?->available_quantity ?? 0,
            'reserved_quantity' => (float) $reserved,
        ];
    }

    public function releaseReservation(int $reservationId): TextileReservation
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        return DB::transaction(function () use ($reservationId, $tenantId) {
            $reservation = TextileReservation::query()
                ->where('id', $reservationId)
                ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
                ->where('is_active', true)
                ->first();

            if ($reservation === null) {
                throw new RuntimeException('Active reservation not found for this tenant.');
            }

            $lot = TextileLot::query()
                ->where('lot_reference', $reservation->lot_reference)
                ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
                ->lockForUpdate()
                ->first();

            if ($lot !== null) {
                $lot->available_quantity = (float) $lot->available_quantity + (float) $reservation->reserved_quantity;
                $lot->save();
            }

            $reservation->is_active = false;
            $reservation->status = 'released';
            $reservation->save();

            return $reservation;
        });
    }

    public function allocateReservation(int $reservationId, int $allocationReferenceId, string $allocationReferenceType = 'allocation'): TextileReservation
    {
        $tenantId = (auth()->check() && function_exists('creatorId')) ? creatorId() : null;

        return DB::transaction(function () use ($reservationId, $allocationReferenceId, $allocationReferenceType, $tenantId) {
            $reservation = TextileReservation::query()
                ->where('id', $reservationId)
                ->when($tenantId !== null, fn ($q) => $q->where('created_by', $tenantId))
                ->where('is_active', true)
                ->first();

            if ($reservation === null) {
                throw new RuntimeException('Active reservation not found for this tenant.');
            }

            $reservation->reference_type = $allocationReferenceType;
            $reservation->reference_id = $allocationReferenceId;
            $reservation->status = 'allocated';
            $reservation->save();

            return $reservation;
        });
    }
}
