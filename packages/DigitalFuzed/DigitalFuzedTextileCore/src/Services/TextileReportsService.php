<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileBreakdown;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use Illuminate\Support\Collection;

class TextileReportsService
{
    public function production(array $filters = []): array
    {
        $docs = $this->documents(['production_batch', 'weaving_output', 'shift_production'], $filters);
        $batches = $docs->where('document_type', 'production_batch');
        $outputs = $docs->whereIn('document_type', ['weaving_output', 'shift_production']);

        return [
            'kpis' => [
                ['label' => 'Production Batches', 'value' => $batches->count(), 'hint' => 'production_batch documents'],
                ['label' => 'Total Batch Qty', 'value' => number_format($batches->sum('quantity'), 2), 'hint' => 'meters across batches'],
                ['label' => 'Weaving Outputs', 'value' => $outputs->count(), 'hint' => 'weaving_output + shift_production'],
                ['label' => 'Output Qty', 'value' => number_format($outputs->sum('quantity'), 2), 'hint' => 'meters produced'],
            ],
            'rows' => $this->rows($docs),
        ];
    }

    public function loom(array $filters = []): array
    {
        $masters = $this->documents(['loom_master'], $filters);
        $breakdowns = $this->tenantQuery(TextileBreakdown::query())
            ->when($filters['from'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->where('is_active', true)
            ->count();
        $efficiency = $this->documents(['loom_efficiency'], $filters);

        return [
            'kpis' => [
                ['label' => 'Loom Masters', 'value' => $masters->count(), 'hint' => 'registered machines'],
                ['label' => 'Active Looms', 'value' => $masters->whereIn('status', ['approved', 'released', 'closed'])->count(), 'hint' => 'approved/released/closed'],
                ['label' => 'Breakdowns', 'value' => $breakdowns, 'hint' => 'recorded breakdown events'],
                ['label' => 'Avg Efficiency', 'value' => $this->percent($efficiency->avg('metadata.efficiency_percent')), 'hint' => 'from loom efficiency logs'],
            ],
            'rows' => $masters->map(fn ($doc) => [
                'document_number' => $doc->document_number,
                'machine_name' => $doc->document_number,
                'machine_type' => $doc->metadata['machine_type'] ?? '-',
                'operator_name' => $doc->metadata['operator_name'] ?? $doc->party_name ?? '-',
                'status' => $doc->status,
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function operator(array $filters = []): array
    {
        $docs = $this->documents(['operator_efficiency'], $filters);

        return [
            'kpis' => [
                ['label' => 'Records', 'value' => $docs->count(), 'hint' => 'operator efficiency logs'],
                ['label' => 'Avg Efficiency', 'value' => $this->percent($docs->avg('metadata.efficiency_percent')), 'hint' => 'across all operators'],
                ['label' => 'Best Efficiency', 'value' => $this->percent($docs->max('metadata.efficiency_percent')), 'hint' => 'single best record'],
            ],
            'rows' => $docs->map(fn ($doc) => [
                'operator_name' => $doc->metadata['operator_name'] ?? $doc->party_name ?? '-',
                'shift' => $doc->metadata['planned_shift'] ?? $doc->lot_reference ?? '-',
                'planned_quantity' => $doc->metadata['planned_quantity'] ?? 0,
                'actual_quantity' => $doc->quantity,
                'efficiency_percent' => $this->percent($doc->metadata['efficiency_percent']),
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function yarnConsumption(array $filters = []): array
    {
        $allocation = $this->documents(['yarn_allocation'], $filters);
        $chemical = $this->documents(['chemical_consumption'], $filters);

        return [
            'kpis' => [
                ['label' => 'Yarn Allocations', 'value' => $allocation->count(), 'hint' => 'warp yarn allocation plans'],
                ['label' => 'Allocated Qty', 'value' => number_format($allocation->sum('quantity'), 2), 'hint' => 'yarn quantity allocated'],
                ['label' => 'Chemical Entries', 'value' => $chemical->count(), 'hint' => 'chemical consumption records'],
                ['label' => 'Chemical Qty', 'value' => number_format($chemical->sum('quantity'), 2), 'hint' => 'sizing/processing chemicals'],
            ],
            'rows' => $allocation->concat($chemical)->map(fn ($doc) => [
                'type' => $doc->document_type,
                'document_number' => $doc->document_number,
                'lot_reference' => $doc->lot_reference ?? '-',
                'party_name' => $doc->party_name ?? '-',
                'quantity' => $doc->quantity,
                'unit' => $doc->unit ?? '-',
                'status' => $doc->status,
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function beam(array $filters = []): array
    {
        $beams = $this->documents(['beam'], $filters);
        $issues = $this->documents(['beam_issue'], $filters);
        $returns = $this->documents(['beam_return'], $filters);
        $inspections = $this->documents(['beam_inspection'], $filters);

        return [
            'kpis' => [
                ['label' => 'Beams', 'value' => $beams->count(), 'hint' => 'registered beams'],
                ['label' => 'Issues', 'value' => $issues->count(), 'hint' => 'beam issue records'],
                ['label' => 'Returns', 'value' => $returns->count(), 'hint' => 'beam return records'],
                ['label' => 'Inspections', 'value' => $inspections->count(), 'hint' => 'beam inspection records'],
            ],
            'rows' => $beams->map(fn ($doc) => [
                'document_number' => $doc->document_number,
                'lot_reference' => $doc->lot_reference ?? '-',
                'party_name' => $doc->party_name ?? '-',
                'quantity' => $doc->quantity,
                'unit' => $doc->unit ?? '-',
                'status' => $doc->status,
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function greyFabric(array $filters = []): array
    {
        $rolls = $this->documents(['grey_fabric_roll'], $filters);

        return [
            'kpis' => [
                ['label' => 'Grey Rolls', 'value' => $rolls->count(), 'hint' => 'generated grey fabric rolls'],
                ['label' => 'Total Meters', 'value' => number_format($rolls->sum('quantity'), 2), 'hint' => 'roll lengths combined'],
                ['label' => 'Avg Roll Length', 'value' => number_format($rolls->count() > 0 ? $rolls->sum('quantity') / $rolls->count() : 0, 2), 'hint' => 'meters per roll'],
                ['label' => 'Total Weight', 'value' => number_format($rolls->sum('metadata.roll_weight'), 2), 'hint' => 'roll weight combined'],
            ],
            'rows' => $rolls->map(fn ($doc) => [
                'roll_number' => $doc->metadata['roll_number'] ?? $doc->lot_reference ?? '-',
                'lot_reference' => $doc->lot_reference ?? '-',
                'roll_length' => $doc->metadata['roll_length'] ?? $doc->quantity ?? 0,
                'roll_weight' => $doc->metadata['roll_weight'] ?? 0,
                'grade' => $doc->metadata['grade'] ?? '-',
                'defects' => count($doc->metadata['defects'] ?? []),
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function finishedFabric(array $filters = []): array
    {
        $lots = $this->tenantQuery(TextileLot::query())
            ->where('is_active', true)
            ->when($filters['from'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->get();

        return [
            'kpis' => [
                ['label' => 'Finished Lots', 'value' => $lots->count(), 'hint' => 'active inventory lots'],
                ['label' => 'Received Qty', 'value' => number_format($lots->sum('received_quantity'), 2), 'hint' => 'total received'],
                ['label' => 'Available Qty', 'value' => number_format($lots->sum('available_quantity'), 2), 'hint' => 'on-hand finished stock'],
                ['label' => 'Frozen Lots', 'value' => $lots->where('is_frozen', true)->count(), 'hint' => 'lots under freeze'],
            ],
            'rows' => $lots->map(fn ($lot) => [
                'lot_reference' => $lot->lot_reference ?? '-',
                'batch_number' => $lot->batch_number ?? '-',
                'received_quantity' => $lot->received_quantity,
                'available_quantity' => $lot->available_quantity,
                'status' => $lot->status ?? '-',
                'frozen' => $lot->is_frozen ? 'Yes' : 'No',
                'date' => $lot->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function dispatch(array $filters = []): array
    {
        $plans = $this->documents(['dispatch_plan'], $filters);
        $dispatches = $this->documents(['dispatch'], $filters);
        $freight = $plans->sum(fn ($doc) => (float) ($doc->metadata['freight_amount'] ?? 0));

        return [
            'kpis' => [
                ['label' => 'Dispatch Plans', 'value' => $plans->count(), 'hint' => 'dispatch planning documents'],
                ['label' => 'Dispatches', 'value' => $dispatches->count(), 'hint' => 'confirmed dispatches'],
                ['label' => 'Total Freight', 'value' => number_format($freight, 2), 'hint' => 'freight amount combined'],
                ['label' => 'Planned Qty', 'value' => number_format($plans->sum('quantity'), 2), 'hint' => 'quantity planned'],
            ],
            'rows' => $plans->map(fn ($doc) => [
                'document_number' => $doc->document_number,
                'party_name' => $doc->party_name ?? '-',
                'lot_reference' => $doc->lot_reference ?? '-',
                'quantity' => $doc->quantity,
                'dispatch_mode' => $doc->metadata['dispatch_mode'] ?? '-',
                'truck_number' => $doc->metadata['truck_number'] ?? '-',
                'freight_amount' => $doc->metadata['freight_amount'] ?? 0,
                'status' => $doc->status,
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function purchase(array $filters = []): array
    {
        $docs = $this->documents(['purchase_order', 'purchase_requisition', 'grn'], $filters);
        $orders = $docs->where('document_type', 'purchase_order');
        $requisitions = $docs->where('document_type', 'purchase_requisition');
        $grns = $docs->where('document_type', 'grn');

        return [
            'kpis' => [
                ['label' => 'Purchase Orders', 'value' => $orders->count(), 'hint' => 'PO documents'],
                ['label' => 'Requisitions', 'value' => $requisitions->count(), 'hint' => 'PR documents'],
                ['label' => 'GRNs', 'value' => $grns->count(), 'hint' => 'goods receipt notes'],
                ['label' => 'Total PO Qty', 'value' => number_format($orders->sum('quantity'), 2), 'hint' => 'quantity ordered'],
            ],
            'rows' => $this->rows($docs),
        ];
    }

    public function sales(array $filters = []): array
    {
        $docs = $this->documents(['sales_order'], $filters);

        return [
            'kpis' => [
                ['label' => 'Sales Orders', 'value' => $docs->count(), 'hint' => 'SO documents'],
                ['label' => 'Total Qty', 'value' => number_format($docs->sum('quantity'), 2), 'hint' => 'quantity ordered'],
                ['label' => 'Approved', 'value' => $docs->where('status', 'approved')->count(), 'hint' => 'approved orders'],
                ['label' => 'Parties', 'value' => $docs->pluck('party_name')->filter()->unique()->count(), 'hint' => 'unique customers'],
            ],
            'rows' => $this->rows($docs),
        ];
    }

    public function stock(array $filters = []): array
    {
        $movements = $this->tenantQuery(TextileMovement::query())
            ->where('is_active', true)
            ->when($filters['from'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->get();
        $receipts = $movements->where('movement_type', 'receipt');
        $issues = $movements->where('movement_type', 'issue');

        return [
            'kpis' => [
                ['label' => 'Movements', 'value' => $movements->count(), 'hint' => 'stock movement records'],
                ['label' => 'Receipts', 'value' => $receipts->count(), 'hint' => 'inward movements'],
                ['label' => 'Issues', 'value' => $issues->count(), 'hint' => 'outward movements'],
                ['label' => 'Moved Qty', 'value' => number_format($movements->sum('quantity'), 2), 'hint' => 'total quantity moved'],
            ],
            'rows' => $movements->map(fn ($movement) => [
                'movement_type' => $movement->movement_type ?? '-',
                'lot_reference' => $movement->lot_reference ?? '-',
                'location_from' => $movement->location_from ?? '-',
                'location_to' => $movement->location_to ?? '-',
                'quantity' => $movement->quantity,
                'status' => $movement->status ?? '-',
                'date' => $movement->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function profit(array $filters = []): array
    {
        $snapshots = $this->documents(['margin_snapshot'], $filters);
        $revenue = $snapshots->sum(fn ($doc) => (float) ($doc->metadata['revenue_value'] ?? 0));
        $cost = $snapshots->sum(fn ($doc) => (float) ($doc->metadata['total_cost'] ?? 0));

        return [
            'kpis' => [
                ['label' => 'Margin Snapshots', 'value' => $snapshots->count(), 'hint' => 'posted margin snapshots'],
                ['label' => 'Total Revenue', 'value' => number_format($revenue, 2), 'hint' => 'revenue value combined'],
                ['label' => 'Total Cost', 'value' => number_format($cost, 2), 'hint' => 'product cost combined'],
                ['label' => 'Gross Margin', 'value' => number_format($revenue - $cost, 2), 'hint' => 'revenue minus cost'],
            ],
            'rows' => $snapshots->map(fn ($doc) => [
                'document_number' => $doc->document_number,
                'party_name' => $doc->party_name ?? '-',
                'lot_reference' => $doc->lot_reference ?? '-',
                'revenue_value' => $doc->metadata['revenue_value'] ?? 0,
                'total_cost' => $doc->metadata['total_cost'] ?? 0,
                'margin' => (float) ($doc->metadata['revenue_value'] ?? 0) - (float) ($doc->metadata['total_cost'] ?? 0),
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function machineEfficiency(array $filters = []): array
    {
        $docs = $this->documents(['loom_efficiency'], $filters);

        return [
            'kpis' => [
                ['label' => 'Records', 'value' => $docs->count(), 'hint' => 'loom efficiency logs'],
                ['label' => 'Avg Efficiency', 'value' => $this->percent($docs->avg('metadata.efficiency_percent')), 'hint' => 'across all records'],
                ['label' => 'Total Runtime', 'value' => number_format($docs->sum('metadata.runtime_hours'), 2), 'hint' => 'hours in runtime'],
                ['label' => 'Total Downtime', 'value' => number_format($docs->sum('metadata.downtime_hours'), 2), 'hint' => 'hours in downtime'],
            ],
            'rows' => $docs->map(fn ($doc) => [
                'machine_name' => $doc->lot_reference ?? '-',
                'operator_name' => $doc->metadata['operator_name'] ?? $doc->party_name ?? '-',
                'planned_quantity' => $doc->metadata['planned_quantity'] ?? 0,
                'actual_quantity' => $doc->quantity,
                'runtime_hours' => $doc->metadata['runtime_hours'] ?? 0,
                'downtime_hours' => $doc->metadata['downtime_hours'] ?? 0,
                'efficiency_percent' => $this->percent($doc->metadata['efficiency_percent']),
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function wasteAnalysis(array $filters = []): array
    {
        $docs = $this->documents(['waste', 'rework'], $filters);
        $waste = $docs->where('document_type', 'waste');
        $rework = $docs->where('document_type', 'rework');

        return [
            'kpis' => [
                ['label' => 'Waste Records', 'value' => $waste->count(), 'hint' => 'waste entries'],
                ['label' => 'Waste Qty', 'value' => number_format($waste->sum('quantity'), 2), 'hint' => 'waste quantity'],
                ['label' => 'Rework Records', 'value' => $rework->count(), 'hint' => 'rework entries'],
                ['label' => 'Rework Qty', 'value' => number_format($rework->sum('quantity'), 2), 'hint' => 'rework quantity'],
            ],
            'rows' => $docs->map(fn ($doc) => [
                'type' => $doc->document_type,
                'document_number' => $doc->document_number,
                'lot_reference' => $doc->lot_reference ?? '-',
                'party_name' => $doc->party_name ?? '-',
                'quantity' => $doc->quantity,
                'unit' => $doc->unit ?? '-',
                'date' => $doc->created_at->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function powerConsumption(array $filters = []): array
    {
        $records = $this->tenantQuery(TextilePowerCost::query())
            ->when($filters['from'] ?? null, fn ($q) => $q->whereDate('period_start', '>=', $filters['from']))
            ->when($filters['to'] ?? null, fn ($q) => $q->whereDate('period_end', '<=', $filters['to']))
            ->where('is_active', true)
            ->get();

        return [
            'kpis' => [
                ['label' => 'Periods', 'value' => $records->count(), 'hint' => 'billing periods recorded'],
                ['label' => 'Units Consumed', 'value' => number_format($records->sum('units_consumed'), 2), 'hint' => 'total units'],
                ['label' => 'Total Cost', 'value' => number_format($records->sum('total_cost'), 2), 'hint' => 'power cost combined'],
                ['label' => 'Avg Rate', 'value' => number_format($records->count() > 0 ? $records->sum('total_cost') / max($records->sum('units_consumed'), 1) : 0, 4), 'hint' => 'cost per unit'],
            ],
            'rows' => $records->map(fn ($record) => [
                'period_start' => $record->period_start,
                'period_end' => $record->period_end,
                'meter_reading_start' => $record->meter_reading_start,
                'meter_reading_end' => $record->meter_reading_end,
                'units_consumed' => $record->units_consumed,
                'rate_per_unit' => $record->rate_per_unit,
                'total_cost' => $record->total_cost,
            ])->values()->all(),
        ];
    }

    public function dailyMis(array $filters = []): array
    {
        $docs = $this->documents(['production_batch', 'weaving_output', 'shift_production', 'dispatch_plan', 'margin_snapshot', 'waste', 'rework'], $filters);
        $rows = [];
        $totals = ['production' => 0.0, 'revenue' => 0.0, 'cost' => 0.0, 'dispatches' => 0, 'waste' => 0.0];

        foreach ($docs->groupBy(fn ($doc) => $doc->created_at->format('Y-m-d')) as $date => $dayDocs) {
            $production = $dayDocs->whereIn('document_type', ['production_batch', 'weaving_output', 'shift_production'])->sum('quantity');
            $revenue = $dayDocs->where('document_type', 'margin_snapshot')->sum(fn ($doc) => (float) ($doc->metadata['revenue_value'] ?? 0));
            $cost = $dayDocs->where('document_type', 'margin_snapshot')->sum(fn ($doc) => (float) ($doc->metadata['total_cost'] ?? 0));
            $dispatches = $dayDocs->where('document_type', 'dispatch_plan')->count();
            $waste = $dayDocs->whereIn('document_type', ['waste', 'rework'])->sum('quantity');

            $rows[] = [
                'date' => $date,
                'production_qty' => number_format($production, 2),
                'dispatches' => $dispatches,
                'revenue' => number_format($revenue, 2),
                'cost' => number_format($cost, 2),
                'waste_qty' => number_format($waste, 2),
            ];

            $totals['production'] += $production;
            $totals['revenue'] += $revenue;
            $totals['cost'] += $cost;
            $totals['dispatches'] += $dispatches;
            $totals['waste'] += $waste;
        }

        usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'kpis' => [
                ['label' => 'Days Covered', 'value' => count($rows), 'hint' => 'days with activity'],
                ['label' => 'Total Production', 'value' => number_format($totals['production'], 2), 'hint' => 'batches + weaving output'],
                ['label' => 'Total Revenue', 'value' => number_format($totals['revenue'], 2), 'hint' => 'margin snapshot revenue'],
                ['label' => 'Total Waste', 'value' => number_format($totals['waste'], 2), 'hint' => 'waste + rework qty'],
            ],
            'rows' => $rows,
        ];
    }

    protected function documents(array $types, array $filters = []): Collection
    {
        return TextileWorkflowDocument::query()
            ->when($this->tenantId() !== null, fn ($q) => $q->where('created_by', $this->tenantId()))
            ->whereIn('document_type', $types)
            ->when($filters['from'] ?? null, fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] ?? null, fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->orderByDesc('created_at')
            ->get();
    }

    protected function tenantQuery($query)
    {
        return $query->when($this->tenantId() !== null, fn ($q) => $q->where('created_by', $this->tenantId()));
    }

    protected function tenantId(): ?int
    {
        return auth()->check() && function_exists('creatorId') ? creatorId() : null;
    }

    protected function rows(Collection $docs): array
    {
        return $docs->map(fn ($doc) => [
            'type' => $doc->document_type,
            'document_number' => $doc->document_number,
            'party_name' => $doc->party_name ?? '-',
            'lot_reference' => $doc->lot_reference ?? '-',
            'quantity' => $doc->quantity,
            'unit' => $doc->unit ?? '-',
            'status' => $doc->status,
            'date' => $doc->created_at->format('Y-m-d'),
        ])->values()->all();
    }

    protected function percent($value): string
    {
        return $value !== null ? number_format((float) $value, 2) . '%' : '-';
    }
}
