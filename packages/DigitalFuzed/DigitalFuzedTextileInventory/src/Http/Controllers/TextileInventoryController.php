<?php

namespace DigitalFuzed\TextileInventory\Http\Controllers;

use DigitalFuzed\TextileCore\Services\TextileOperatingPolicyService;
use DigitalFuzed\TextileCore\Traits\ProvidesRecentActivity;
use DigitalFuzed\TextileInventory\Models\TextileCycleCount;
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
    use ProvidesRecentActivity;

    private const MOVEMENT_TYPES = ['receipt', 'issue', 'transfer', 'adjustment'];
    private const ADJUSTMENT_DIRECTIONS = ['increase', 'decrease'];
    private const MOVEMENT_STATUSES = ['pending', 'posted', 'cancelled'];
    private const LOT_STATUSES = ['active', 'hold', 'inactive'];

    public function __construct(protected TextileOperatingPolicyService $policyService)
    {
    }

    /**
     * Material-type section IDs mapped to their TextileLot constant.
     */
    private const SECTION_MATERIAL_TYPES = [
        'yarn-stock' => TextileLot::TYPE_YARN,
        'beam-stock' => TextileLot::TYPE_BEAM,
        'grey-fabric' => TextileLot::TYPE_GREY_FABRIC,
        'finished-fabric' => TextileLot::TYPE_FINISHED_FABRIC,
        'chemicals' => TextileLot::TYPE_CHEMICAL,
        'packing-materials' => TextileLot::TYPE_PACKING_MATERIAL,
    ];

    public function index(Request $request)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapabilityOrAbort('inventory');

        $section = $request->string('section')->toString();
        $tenantId = creatorId();

        // Base lot query with reserved quantity sub-select
        $lotQuery = TextileLot::query()
            ->where('created_by', $tenantId)
            ->select('*')
            ->selectSub(function ($query) {
                $query->from('textile_reservations')
                    ->selectRaw('COALESCE(SUM(reserved_quantity), 0)')
                    ->whereColumn('textile_reservations.lot_reference', 'textile_lots.lot_reference')
                    ->whereColumn('textile_reservations.created_by', 'textile_lots.created_by')
                    ->where('is_active', true);
            }, 'reserved_quantity');

        // Filter by material_type when viewing a material-type section
        $materialType = self::SECTION_MATERIAL_TYPES[$section] ?? null;
        if ($materialType) {
            $lotQuery->where('material_type', $materialType);
        }

        $lots = $lotQuery->latest()->get();

        // KPIs — always computed for the filtered set
        $kpis = [
            'total_lots' => $lots->count(),
            'total_qty' => (float) $lots->sum('received_quantity'),
            'available_qty' => (float) $lots->sum('available_quantity'),
            'reserved_qty' => (float) $lots->sum('reserved_quantity'),
            'frozen_count' => $lots->where('is_frozen', true)->count(),
        ];

        // Per-material-type breakdown (for overview)
        $materialTypeKpis = [];
        if ($section === 'overview' || ! $materialType) {
            foreach (self::SECTION_MATERIAL_TYPES as $sectionKey => $type) {
                $typeLots = $lots->where('material_type', $type);
                $materialTypeKpis[$sectionKey] = [
                    'label' => TextileLot::materialTypeLabel($type),
                    'icon' => TextileLot::materialTypeIcon($type),
                    'count' => $typeLots->count(),
                    'total_qty' => (float) $typeLots->sum('received_quantity'),
                    'available_qty' => (float) $typeLots->sum('available_quantity'),
                ];
            }
        }

        // Movement filters
        $movementsQuery = TextileMovement::query()->where('created_by', $tenantId);

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

        $baseData = [
            'lots' => $lots,
            'kpis' => $kpis,
            'materialTypeKpis' => $materialTypeKpis,
            'section' => $section,
            'materialType' => $materialType,
            'recentActivity' => $this->recentActivity(),
        ];

        // For locations-controls section, include locations, cycle counts, and recent movements
        if ($section === 'locations-controls' || $section === '') {
            return Inertia::render('DigitalFuzedTextileInventory/Inventory/Index', array_merge($baseData, [
                'locations' => TextileLocation::where('created_by', $tenantId)->where('is_active', true)->latest()->get(),
                'cycleCounts' => TextileCycleCount::where('created_by', $tenantId)->latest()->limit(100)->get(),
                'movements' => $movementsQuery->latest()->limit(100)->get(),
                'reservations' => TextileReservation::where('created_by', $tenantId)->where('is_active', true)->latest()->limit(100)->get(),
                'movementTypes' => self::MOVEMENT_TYPES,
                'movementStatuses' => self::MOVEMENT_STATUSES,
                'filters' => [
                    'movement_type' => $request->string('movement_type')->toString(),
                    'status' => $request->string('status')->toString(),
                    'lot_reference' => $request->string('lot_reference')->toString(),
                    'location' => $request->string('location')->toString(),
                ],
            ]));
        }

        return Inertia::render('DigitalFuzedTextileInventory/Inventory/Index', $baseData);
    }

    public function storeLocation(Request $request)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_locations', 'name');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('textile_locations', 'name')->where(fn ($query) => $query->where('created_by', creatorId())),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'rack' => ['nullable', 'string', 'max:50'],
            'bin' => ['nullable', 'string', 'max:50'],
            'location_type' => ['nullable', 'string', 'max:50'],
        ]);

        TextileLocation::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'rack' => $validated['rack'] ?? null,
            'bin' => $validated['bin'] ?? null,
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
        $this->authorizeCapability('inventory_locations', 'location_id');

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
        $this->authorizeCapability('inventory_transactions', 'lot_reference');

        $validated = $request->validate([
            'lot_reference' => [
                'required',
                'string',
                'max:100',
                Rule::unique('textile_lots', 'lot_reference')->where(fn ($query) => $query->where('created_by', creatorId())),
            ],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'qr_code' => ['nullable', 'string', 'max:500'],
            'received_quantity' => ['required', 'numeric', 'gt:0'],
            'available_quantity' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(self::LOT_STATUSES)],
            'material_type' => ['nullable', 'string', 'max:40', Rule::in(TextileLot::MATERIAL_TYPES)],
            'production_stage' => ['nullable', 'string', 'max:40'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $barcode = $validated['barcode'] ?? strtoupper('LOT-'.$validated['lot_reference']);
        $qrCode = $validated['qr_code'] ?? sprintf(
            'LOT:%s|BATCH:%s|BARCODE:%s|TENANT:%s',
            $validated['lot_reference'],
            $validated['batch_number'] ?? '-',
            $barcode,
            (string) creatorId()
        );

        TextileLot::create([
            'lot_reference' => $validated['lot_reference'],
            'batch_number' => $validated['batch_number'] ?? null,
            'barcode' => $barcode,
            'qr_code' => $qrCode,
            'received_quantity' => $validated['received_quantity'],
            'available_quantity' => $validated['available_quantity'] ?? $validated['received_quantity'],
            'status' => $validated['status'] ?? 'active',
            'material_type' => $validated['material_type'] ?? null,
            'production_stage' => $validated['production_stage'] ?? null,
            'source_document_type' => $validated['source_document_type'] ?? null,
            'source_document_id' => $validated['source_document_id'] ?? null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Textile lot created successfully.'));
    }

    public function updateLot(Request $request)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_controls', 'lot_id');

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
        $this->authorizeCapability('inventory_controls', 'lot_id');

        $validated = $request->validate([
            'lot_id' => ['required', 'integer', 'min:1'],
        ]);

        $lot = TextileLot::where('created_by', creatorId())->where('id', $validated['lot_id'])->where('is_active', true)->firstOrFail();
        $lot->is_active = false;
        $lot->status = 'inactive';
        $lot->save();

        return back()->with('success', __('Textile lot archived successfully.'));
    }

    public function freezeLot(Request $request)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_freeze', 'lot_id');

        $validated = $request->validate([
            'lot_id' => ['required', 'integer', 'min:1'],
            'freeze_note' => ['nullable', 'string'],
        ]);

        $lot = TextileLot::where('created_by', creatorId())
            ->where('id', $validated['lot_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $lot->is_frozen = true;
        $lot->freeze_note = $validated['freeze_note'] ?? null;
        $lot->status = 'hold';
        $lot->save();

        return back()->with('success', __('Textile lot frozen successfully.'));
    }

    public function unfreezeLot(Request $request)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_freeze', 'lot_id');

        $validated = $request->validate([
            'lot_id' => ['required', 'integer', 'min:1'],
        ]);

        $lot = TextileLot::where('created_by', creatorId())
            ->where('id', $validated['lot_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $lot->is_frozen = false;
        $lot->freeze_note = null;
        if ($lot->status === 'hold') {
            $lot->status = 'active';
        }
        $lot->save();

        return back()->with('success', __('Textile lot unfrozen successfully.'));
    }

    public function showLot(int $lotId, TextileAvailabilityService $availabilityService)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_records', 'lotId');

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
        $this->authorizeCapability('inventory_movements', 'movement_type');

        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(self::MOVEMENT_TYPES)],
            'adjustment_direction' => ['nullable', Rule::in(self::ADJUSTMENT_DIRECTIONS), 'required_if:movement_type,adjustment'],
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

        if (($validated['movement_type'] ?? null) !== 'receipt' && ! empty($validated['lot_reference'])) {
            $this->ensureLotIsNotFrozen((string) $validated['lot_reference']);
        }

        $movementService->createMovement($validated);
        $this->syncLotAvailability($validated);

        return back()->with('success', __('Textile movement recorded successfully.'));
    }

    public function storeReservation(Request $request, TextileAvailabilityService $availabilityService)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_reservations', 'lot_reference');

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->ensureLotIsNotFrozen((string) $validated['lot_reference']);

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

    public function storePhysicalVerification(Request $request, TextileMovementService $movementService)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_verification', 'lot_reference');

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
        ]);

        $this->ensureLotIsNotFrozen((string) $validated['lot_reference']);

        $lot = TextileLot::query()
            ->where('created_by', creatorId())
            ->where('lot_reference', $validated['lot_reference'])
            ->firstOrFail();

        [$difference, $currentAvailable, $countedQuantity, $adjustmentDirection] = $this->resolveVariance(
            $lot,
            (float) $validated['counted_quantity']
        );

        if ($difference <= 0.00001) {
            return back()->with('success', __('Physical verification recorded with no stock variance.'));
        }

        $movementPayload = [
            'movement_type' => 'adjustment',
            'adjustment_direction' => $adjustmentDirection,
            'reference_type' => 'physical_verification',
            'lot_reference' => $lot->lot_reference,
            'location_from' => $validated['location'] ?? null,
            'location_to' => $validated['location'] ?? null,
            'quantity' => $difference,
            'unit' => $validated['unit'] ?? null,
            'status' => 'posted',
            'notes' => sprintf('Physical verification adjusted lot from %s to %s', $currentAvailable, $countedQuantity),
        ];

        $movementService->createMovement($movementPayload);
        $this->syncLotAvailability($movementPayload);

        return back()->with('success', __('Physical verification adjustment posted successfully.'));
    }

    public function storeCycleCount(Request $request, TextileMovementService $movementService)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_cycle_count', 'lot_reference');

        $validated = $request->validate([
            'lot_reference' => ['required', 'string', 'max:100'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureLotIsNotFrozen((string) $validated['lot_reference']);

        $lot = TextileLot::query()
            ->where('created_by', creatorId())
            ->where('lot_reference', $validated['lot_reference'])
            ->firstOrFail();

        [$difference, $currentAvailable, $countedQuantity, $adjustmentDirection] = $this->resolveVariance(
            $lot,
            (float) $validated['counted_quantity']
        );

        if ($difference > 0.00001) {
            $movementPayload = [
                'movement_type' => 'adjustment',
                'adjustment_direction' => $adjustmentDirection,
                'reference_type' => 'cycle_count',
                'lot_reference' => $lot->lot_reference,
                'location_from' => $validated['location'] ?? null,
                'location_to' => $validated['location'] ?? null,
                'quantity' => $difference,
                'unit' => $validated['unit'] ?? null,
                'status' => 'posted',
                'notes' => sprintf('Cycle count adjusted lot from %s to %s', $currentAvailable, $countedQuantity),
            ];

            $movementService->createMovement($movementPayload);
            $this->syncLotAvailability($movementPayload);
        }

        TextileCycleCount::create([
            'lot_reference' => $lot->lot_reference,
            'expected_quantity' => $currentAvailable,
            'counted_quantity' => $countedQuantity,
            'variance_quantity' => $countedQuantity - $currentAvailable,
            'adjustment_direction' => $adjustmentDirection,
            'location' => $validated['location'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'status' => $difference > 0.00001 ? 'posted' : 'verified',
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
            'created_by' => creatorId(),
            'creator_id' => Auth::id(),
        ]);

        return back()->with('success', __('Cycle count recorded successfully.'));
    }

    public function releaseReservation(Request $request, TextileAvailabilityService $availabilityService)
    {
        $this->authorizeTextileAccess();
        $this->authorizeCapability('inventory_reservations', 'reservation_id');

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
        $this->authorizeCapability('inventory_reservations', 'reservation_id');

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

        if ($type === 'adjustment') {
            $direction = strtolower((string) ($movement['adjustment_direction'] ?? 'decrease'));

            if ($direction === 'increase') {
                $lot->available_quantity = (float) $lot->available_quantity + $quantity;
            }

            if ($direction === 'decrease') {
                $lot->available_quantity = max(0, (float) $lot->available_quantity - $quantity);
            }
        }

        $lot->save();
    }

    private function authorizeTextileAccess(): void
    {
        $user = Auth::user();

        abort_unless($user && in_array($user->type, ['company', 'superadmin', 'staff'], true), 403);
    }

    private function authorizeCapability(string $capability, string $errorKey): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => __($exception->getMessage()),
            ]);
        }
    }

    private function authorizeCapabilityOrAbort(string $capability): void
    {
        try {
            $this->policyService->assertCapability($capability);
        } catch (RuntimeException $exception) {
            abort(403, __($exception->getMessage()));
        }
    }

    private function ensureLotIsNotFrozen(string $lotReference): void
    {
        $lot = TextileLot::query()
            ->where('created_by', creatorId())
            ->where('lot_reference', $lotReference)
            ->first();

        if ($lot && (bool) $lot->is_frozen) {
            abort(422, __('The selected lot is frozen and cannot be moved or reserved.'));
        }
    }

    private function resolveVariance(TextileLot $lot, float $countedQuantity): array
    {
        $currentAvailable = (float) $lot->available_quantity;
        $difference = abs($countedQuantity - $currentAvailable);

        if ($difference <= 0.00001) {
            return [0.0, $currentAvailable, $countedQuantity, null];
        }

        $adjustmentDirection = $countedQuantity > $currentAvailable ? 'increase' : 'decrease';

        return [$difference, $currentAvailable, $countedQuantity, $adjustmentDirection];
    }
}