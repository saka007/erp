<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileBreakdown;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceSparePartUsage;
use DigitalFuzed\TextileCore\Models\TextilePmSchedule;
use DigitalFuzed\TextileCore\Models\TextileServiceSchedule;
use Illuminate\Support\Facades\Auth;

class TextileMaintenanceService
{
    public function savePmSchedule(array $data): TextilePmSchedule
    {
        $data = array_merge($this->baseAttributes(), $data);
        $schedule = TextilePmSchedule::create($data);
        $this->denormalizeMachineRef($schedule);
        $schedule->save();

        return $schedule;
    }

    public function saveBreakdown(array $data): TextileBreakdown
    {
        $data = array_merge($this->baseAttributes(), $data);
        $breakdown = TextileBreakdown::create($data);
        $this->denormalizeMachineRef($breakdown);
        $breakdown->save();

        return $breakdown;
    }

    public function saveServiceSchedule(array $data): TextileServiceSchedule
    {
        $data = array_merge($this->baseAttributes(), $data);
        $schedule = TextileServiceSchedule::create($data);
        $this->denormalizeMachineRef($schedule);
        $schedule->save();

        return $schedule;
    }

    public function saveSparePartUsage(array $data): TextileMaintenanceSparePartUsage
    {
        $data = array_merge($this->baseAttributes(), $data);
        $usage = TextileMaintenanceSparePartUsage::create($data);
        $this->denormalizeSparePartMachineRef($usage);
        $usage->save();

        return $usage;
    }

    public function saveMaintenanceCost(array $data): TextileMaintenanceCost
    {
        $data = array_merge($this->baseAttributes(), $data);
        $cost = TextileMaintenanceCost::create($data);
        $this->denormalizeMachineRef($cost);
        $cost->save();

        return $cost;
    }

    /**
     * Denormalize machine display name/type from a loom master document
     * onto records that carry a machine_id column.
     */
    private function denormalizeMachineRef(object $record): void
    {
        if (empty($record->machine_id)) {
            return;
        }

        $machine = \DigitalFuzed\TextileCore\Models\TextileWorkflowDocument::query()
            ->where('created_by', $record->created_by)
            ->where('document_type', 'loom_master')
            ->find($record->machine_id);

        if ($machine) {
            $record->machine_name = $machine->document_number;
            $record->machine_type = $machine->metadata['machine_type'] ?? ($record->machine_type ?? null);
        }
    }

    /**
     * Spare part usage records only carry a denormalized machine_name,
     * derived from the linked maintenance reference when available.
     */
    private function denormalizeSparePartMachineRef(object $record): void
    {
        if (!empty($record->machine_name) || empty($record->maintenance_ref_id) || empty($record->maintenance_ref_type)) {
            return;
        }

        $model = match ($record->maintenance_ref_type) {
            'pm' => TextilePmSchedule::class,
            'breakdown' => TextileBreakdown::class,
            'service' => TextileServiceSchedule::class,
            default => null,
        };

        if ($model === null) {
            return;
        }

        $ref = $model::query()
            ->where('created_by', $record->created_by)
            ->find($record->maintenance_ref_id);

        if ($ref) {
            $record->machine_name = $ref->machine_name;
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
