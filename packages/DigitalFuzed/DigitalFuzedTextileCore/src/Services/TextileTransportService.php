<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileDispatchDriver;
use DigitalFuzed\TextileCore\Models\TextileDispatchRoute;
use DigitalFuzed\TextileCore\Models\TextileDispatchVehicle;
use DigitalFuzed\TextileCore\Models\TextileFuelEntry;
use DigitalFuzed\TextileCore\Models\TextileFreightCost;
use DigitalFuzed\TextileCore\Models\TextileVehicleMaintenance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class TextileTransportService
{
    public function fuelEntryModel(): string
    {
        return TextileFuelEntry::class;
    }

    public function freightCostModel(): string
    {
        return TextileFreightCost::class;
    }

    public function vehicleMaintenanceModel(): string
    {
        return TextileVehicleMaintenance::class;
    }

    public function saveFuelEntry(array $data): TextileFuelEntry
    {
        $data = array_merge($this->baseAttributes(), $data);
        $fuel = TextileFuelEntry::create($data);
        $this->denormalizeTransportRefs($fuel);
        $fuel->save();

        return $fuel;
    }

    public function saveFreightCost(array $data): TextileFreightCost
    {
        $data = array_merge($this->baseAttributes(), $data);
        $freight = TextileFreightCost::create($data);
        $this->denormalizeTransportRefs($freight);
        $freight->save();

        return $freight;
    }

    public function saveVehicleMaintenance(array $data): TextileVehicleMaintenance
    {
        $data = array_merge($this->baseAttributes(), $data);
        $maintenance = TextileVehicleMaintenance::create($data);
        $this->denormalizeTransportRefs($maintenance);
        $maintenance->save();

        return $maintenance;
    }

    /**
     * Denormalize display names from linked masters onto a transport record.
     * Only writes columns that exist on the record's table, so maintenance
     * records (vehicle-only) skip driver/route snapshots safely.
     */
    private function denormalizeTransportRefs(object $record): void
    {
        $table = $record->getTable();

        if (Schema::hasColumn($table, 'vehicle_name') && !empty($record->vehicle_id)) {
            $vehicle = TextileDispatchVehicle::where('created_by', $record->created_by)->find($record->vehicle_id);
            $record->vehicle_name = $vehicle?->vehicle_number ?? null;
        }

        if (Schema::hasColumn($table, 'driver_name') && !empty($record->driver_id)) {
            $driver = TextileDispatchDriver::where('created_by', $record->created_by)->find($record->driver_id);
            $record->driver_name = $driver?->name ?? null;
        }

        if (Schema::hasColumn($table, 'route_name') && !empty($record->route_id)) {
            $route = TextileDispatchRoute::where('created_by', $record->created_by)->find($record->route_id);
            $record->route_name = $route?->route_name ?? null;
        }
    }

    private function baseAttributes(): array
    {
        return [
            'created_by' => Auth::id(),
            'creator_id' => Auth::id(),
        ];
    }
}
