<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileChemicalCost;
use DigitalFuzed\TextileCore\Models\TextileCostCenter;
use DigitalFuzed\TextileCore\Models\TextileLabourCost;
use DigitalFuzed\TextileCore\Models\TextileMachineCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceCost;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use Illuminate\Support\Facades\Auth;

class TextileFinanceService
{
    public function saveMachineCost(array $data): TextileMachineCost
    {
        $data = array_merge($this->baseAttributes(), $data);
        $data['total_cost'] = round(
            (float) ($data['depreciation_cost'] ?? 0)
            + (float) ($data['maintenance_cost'] ?? 0)
            + (float) ($data['power_cost'] ?? 0)
            + (float) ($data['labor_cost'] ?? 0)
            + (float) ($data['other_cost'] ?? 0),
            2
        );

        $cost = TextileMachineCost::create($data);
        $this->denormalizeMachineRef($cost);
        $cost->save();

        return $cost;
    }

    public function savePowerCost(array $data): TextilePowerCost
    {
        $data = array_merge($this->baseAttributes(), $data);
        $units = round((float) ($data['meter_reading_end'] ?? 0) - (float) ($data['meter_reading_start'] ?? 0), 2);
        $data['units_consumed'] = $units;
        $data['total_cost'] = round($units * (float) ($data['rate_per_unit'] ?? 0), 2);

        return TextilePowerCost::create($data);
    }

    public function saveChemicalCost(array $data): TextileChemicalCost
    {
        $data = array_merge($this->baseAttributes(), $data);
        $data['total_cost'] = round((float) ($data['quantity'] ?? 0) * (float) ($data['unit_cost'] ?? 0), 2);

        return TextileChemicalCost::create($data);
    }

    public function saveLabourCost(array $data): TextileLabourCost
    {
        $data = array_merge($this->baseAttributes(), $data);
        $data['total_cost'] = round(
            (float) ($data['worker_count'] ?? 0)
            * (float) ($data['hours_worked'] ?? 0)
            * (float) ($data['rate_per_hour'] ?? 0),
            2
        );

        $cost = TextileLabourCost::create($data);
        $this->denormalizeCostCenterRef($cost);
        $cost->save();

        return $cost;
    }

    /**
     * Cost per meter per approved costing entry plus the weighted average.
     */
    public function costPerMeter(): array
    {
        $entries = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'costing_entry')
            ->where('status', 'approved')
            ->get();

        $rows = [];
        $totalCost = 0.0;
        $totalMeters = 0.0;

        foreach ($entries as $entry) {
            $meta = is_array($entry->metadata) ? $entry->metadata : [];
            $total = (float) ($meta['total_cost'] ?? 0);
            $meters = max(0, (float) $entry->quantity);
            $totalCost += $total;
            $totalMeters += $meters;

            $rows[] = [
                'id' => (int) $entry->id,
                'document_number' => $entry->document_number,
                'party_name' => $entry->party_name,
                'lot_reference' => $entry->lot_reference,
                'quantity' => $meters,
                'total_cost' => round($total, 2),
                'cost_per_meter' => $meters > 0 ? round($total / $meters, 4) : null,
            ];
        }

        return [
            'rows' => $rows,
            'total_cost' => round($totalCost, 2),
            'total_meters' => $totalMeters,
            'average_cost_per_meter' => $totalMeters > 0 ? round($totalCost / $totalMeters, 4) : 0,
        ];
    }

    /**
     * Cost per roll per approved costing entry that carries a rolls_count,
     * plus the aggregate average.
     */
    public function costPerRoll(): array
    {
        $entries = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'costing_entry')
            ->where('status', 'approved')
            ->get();

        $rows = [];
        $totalCost = 0.0;
        $totalRolls = 0.0;

        foreach ($entries as $entry) {
            $meta = is_array($entry->metadata) ? $entry->metadata : [];
            $total = (float) ($meta['total_cost'] ?? 0);
            $rolls = (float) ($meta['rolls_count'] ?? 0);

            if ($rolls > 0) {
                $totalCost += $total;
                $totalRolls += $rolls;

                $rows[] = [
                    'id' => (int) $entry->id,
                    'document_number' => $entry->document_number,
                    'party_name' => $entry->party_name,
                    'lot_reference' => $entry->lot_reference,
                    'quantity' => (float) $entry->quantity,
                    'total_cost' => round($total, 2),
                    'rolls_count' => $rolls,
                    'cost_per_roll' => round($total / $rolls, 2),
                ];
            }
        }

        return [
            'rows' => $rows,
            'total_cost' => round($totalCost, 2),
            'total_rolls' => $totalRolls,
            'average_cost_per_roll' => $totalRolls > 0 ? round($totalCost / $totalRolls, 2) : 0,
        ];
    }

    /**
     * Profitability: margin snapshots (revenue - cost) plus the operating
     * cost breakdown from maintenance, machine, power, chemical and labour.
     */
    public function profitability(): array
    {
        $snapshots = TextileWorkflowDocument::query()
            ->where('created_by', creatorId())
            ->where('document_type', 'margin_snapshot')
            ->get();

        $totalRevenue = 0.0;
        $totalCost = 0.0;

        foreach ($snapshots as $snapshot) {
            $meta = is_array($snapshot->metadata) ? $snapshot->metadata : [];
            $totalRevenue += (float) ($meta['revenue_value'] ?? 0);
            $totalCost += (float) ($meta['total_cost'] ?? 0);
        }

        $maintenance = TextileMaintenanceCost::query()->where('created_by', creatorId())->where('is_active', true)->sum('total_cost');
        $machine = TextileMachineCost::query()->where('created_by', creatorId())->where('is_active', true)->sum('total_cost');
        $power = TextilePowerCost::query()->where('created_by', creatorId())->where('is_active', true)->sum('total_cost');
        $chemical = TextileChemicalCost::query()->where('created_by', creatorId())->where('is_active', true)->sum('total_cost');
        $labour = TextileLabourCost::query()->where('created_by', creatorId())->where('is_active', true)->sum('total_cost');

        $operatingCosts = round($maintenance + $machine + $power + $chemical + $labour, 2);
        $margin = $totalRevenue - $totalCost - $operatingCosts;

        return [
            'snapshots_count' => $snapshots->count(),
            'total_revenue' => round($totalRevenue, 2),
            'product_cost' => round($totalCost, 2),
            'operating_costs' => $operatingCosts,
            'breakdown' => [
                'maintenance' => round((float) $maintenance, 2),
                'machine' => round((float) $machine, 2),
                'power' => round((float) $power, 2),
                'chemical' => round((float) $chemical, 2),
                'labour' => round((float) $labour, 2),
            ],
            'margin_value' => round($margin, 2),
            'margin_percent' => $totalRevenue > 0 ? round(($margin / $totalRevenue) * 100, 2) : 0,
        ];
    }

    private function denormalizeMachineRef(object $record): void
    {
        if (empty($record->machine_id)) {
            return;
        }

        $machine = TextileWorkflowDocument::query()
            ->where('created_by', $record->created_by)
            ->where('document_type', 'loom_master')
            ->find($record->machine_id);

        if ($machine) {
            $record->machine_name = $machine->document_number;
            $record->machine_type = $machine->metadata['machine_type'] ?? ($record->machine_type ?? null);
        }
    }

    private function denormalizeCostCenterRef(object $record): void
    {
        if (empty($record->cost_center_id)) {
            return;
        }

        $center = TextileCostCenter::query()
            ->where('created_by', $record->created_by)
            ->where('is_active', true)
            ->find($record->cost_center_id);

        if ($center) {
            $record->cost_center_name = $center->name;
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
