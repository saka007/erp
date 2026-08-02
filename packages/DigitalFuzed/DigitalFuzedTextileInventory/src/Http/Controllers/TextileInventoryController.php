<?php

namespace DigitalFuzed\TextileInventory\Http\Controllers;

use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileLocation;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use DigitalFuzed\TextileInventory\Models\TextileReservation;
use DigitalFuzed\TextileInventory\Services\TextileAvailabilityService;
use DigitalFuzed\TextileInventory\Services\TextileMovementService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

class TextileInventoryController extends Controller
{
    private const MOVEMENT_TYPES = ['receipt', 'issue', 'transfer', 'adjustment'];
    private const MOVEMENT_STATUSES = ['pending', 'posted', 'cancelled'];
    private const LOT_STATUSES = ['active', 'hold', 'inactive'];

    public function index(Request $request)
    {
        $this->authorizeTextileAccess();

        $movementsQuery = TextileMovement::query()->where('created_by', creatorId());

        if ($request->filled('movement_type')) {
            $movementsQuery->where('movement_type', $request->string('movement_type'));
        }

        if ($request->filled('status')) {
            $movementsQuery->where('status', $request->string('status'));
        }

        if ($request->filled('lot_reference')) {
            $movementsQuery->where('lot_reference', 'like', '%'.$request->string('lot_reference').'%');
        }

        if ($request->filled('location')) {
            $location = $request->string('location');
            $movementsQuery->where(function ($query) use ($location) {
                $query->where('location_from', $location)->orWhere('location_to', $location);
            });
        }

        return Inertia::render('DigitalFuzedTextileInventory/Inventory/Index', [
            'lots' => TextileLot::query()
                ->where('created_by', creatorId())
                ->select('*')
                ->selectSub(function ($query) {
                    $query->from('textile_reservations')
                        ->selectRaw('COALESCE(SUM(reserved_quantity), 0)')
                        ->whereColumn('textile_reservations.lot_reference', 'textile_lots.lot_reference')
                        ->whereColumn('textile_reservations.created_by', 'textile_lots.created_by')
                        ->where('is_active', true);
                }, 'reserved_quantity')
                ->latest()
                ->get(),
            'movements' => $movementsQuery->latest()->limit(100)->get(),
            'reservations' => TextileReservation::where('created_by', creatorId())->where('is_active', true)->latest()->limit(100)->get(),
            'locations' => TextileLocation::where('created_by', creatorId())->where('is_active', true)->latest()->get(),
            'movementTypes' => self::MOVEMENT_TYPES,
            'movementStatuses' => self::MOVEMENT_STATUSES,
            'filters' => [
                'movement_type' => $request->string('movement_type')->toString(),
                'status' => $request->string('status')->toString(),
                'lot_reference' => $request->string('lot_reference')->toString(),
                'location' => $request->string('location')->toString(),
            ],
        ]);
    }

    public function storeLocation(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('textile_locations', 'name')->where(fn ($query) => $query->where('created_by', creatorId())),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'location_type' => ['nullable', 'string', 'max:50'],
        ]);

        TextileLocation::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'location_type' => $validated['location_type'] ?? 'warehouse',
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Textile location created successfully.'));
    }

    public function archiveLocation(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'location_id' => ['required', 'integer', 'min:1'],
        ]);

        $location = TextileLocation::where('created_by', creatorId())
            ->where('id', $validated['location_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $location->is_active = false;
        $location->save();

        return back()->with('success', __('Textile location archived successfully.'));
    }

    public function storeLot(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'lot_reference' => [
                'required',
                'string',
                'max:100',
                Rule::unique('textile_lots', 'lot_reference')->where(fn ($query) => $query->where('created_by', creatorId())),
            ],
            'received_quantity' => ['required', 'numeric', 'gt:0'],
            'available_quantity' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(self::LOT_STATUSES)],
        ]);

        TextileLot::create([
            'lot_reference' => $validated['lot_reference'],
            'received_quantity' => $validated['received_quantity'],
            'available_quantity' => $validated['available_quantity'] ?? $validated['received_quantity'],
            'status' => $validated['status'] ?? 'active',
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Textile lot created successfully.'));
    }

    public function updateLot(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'lot_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(self::LOT_STATUSES)],
        ]);

        $lot = TextileLot::where('created_by', creatorId())->where('id', $validated['lot_id'])->firstOrFail();
        $lot->status = $validated['status'];
        $lot->save();

        return back()->with('success', __('Textile lot updated successfully.'));
    }

    public function archiveLot(Request $request)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'lot_id' => ['required', 'integer', 'min:1'],
        ]);

        $lot = TextileLot::where('created_by', creatorId())->where('id', $validated['lot_id'])->where('is_active', true)->firstOrFail();
        $lot->is_active = false;
        $lot->status = 'inactive';
        $lot->save();

        return back()->with('success', __('Textile lot archived successfully.'));
    }

    public function showLot(int $lotId, TextileAvailabilityService $availabilityService)
    {
        $this->authorizeTextileAccess();

        $lot = TextileLot::where('created_by', creatorId())->where('id', $lotId)->firstOrFail();

        return Inertia::render('DigitalFuzedTextileInventory/Inventory/LotShow', [
            'lot' => $lot,
            'movements' => TextileMovement::where('created_by', creatorId())->where('lot_reference', $lot->lot_reference)->latest()->get(),
            'reservations' => TextileReservation::where('created_by', creatorId())->where('lot_reference', $lot->lot_reference)->latest()->get(),
            'availability' => $availabilityService->getAvailability($lot->lot_reference),
        ]);
    }

    public function storeMovement(Request $request, TextileMovementService $movementService)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(self::MOVEMENT_TYPES)],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'lot_reference' => ['nullable', 'string', 'max:100'],
            'location_from' => ['nullable', 'string', 'max:100'],
            'location_to' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(self::MOVEMENT_STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $movementService->createMovement($validated);
        $this->syncLotAvailability($validated);

        return back()->with('success', __('Textile movement recorded successfully.'));
    }

    public function storeReservation(Request $request, TextileAvailabilityService $availabilityService)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $availabilityService->reserve(
                $validated['lot_reference'],
                (float) $validated['quantity'],
                $validated['reference_type'] ?? null,
                $validated['reference_id'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['quantity' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Textile lot reserved successfully.'));
    }

    public function releaseReservation(Request $request, TextileAvailabilityService $availabilityService)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'reservation_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $availabilityService->releaseReservation((int) $validated['reservation_id']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reservation_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Textile reservation released successfully.'));
    }

    public function allocateReservation(Request $request, TextileAvailabilityService $availabilityService)
    {
        $this->authorizeTextileAccess();

        $validated = $request->validate([
            'reservation_id' => ['required', 'integer', 'min:1'],
            'allocation_reference_id' => ['required', 'integer', 'min:1'],
            'allocation_reference_type' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $availabilityService->allocateReservation(
                (int) $validated['reservation_id'],
                (int) $validated['allocation_reference_id'],
                $validated['allocation_reference_type'] ?? 'allocation',
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reservation_id' => __($exception->getMessage())]);
        }

        return back()->with('success', __('Textile reservation linked to allocation successfully.'));
    }

    private function syncLotAvailability(array $movement): void
    {
        if (empty($movement['lot_reference'])) {
            return;
        }

        $lot = TextileLot::firstOrCreate(
            ['created_by' => creatorId(), 'lot_reference' => $movement['lot_reference']],
            [
                'creator_id' => Auth::id(),
                'received_quantity' => 0,
                'available_quantity' => 0,
                'status' => 'active',
                'is_active' => true,
            ]
        );

        $quantity = (float) $movement['quantity'];
        $type = strtolower((string) $movement['movement_type']);

        if ($type === 'receipt') {
            $lot->received_quantity = (float) $lot->received_quantity + $quantity;
            $lot->available_quantity = (float) $lot->available_quantity + $quantity;
        }

        if ($type === 'issue') {
            $lot->available_quantity = max(0, (float) $lot->available_quantity - $quantity);
        }

        $lot->save();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin'], true), 403);
    }
}