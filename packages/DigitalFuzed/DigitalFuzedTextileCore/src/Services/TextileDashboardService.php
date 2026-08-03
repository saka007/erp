<?php

namespace DigitalFuzed\TextileCore\Services;

use DigitalFuzed\TextileCore\Models\TextileBreakdown;
use DigitalFuzed\TextileCore\Models\TextileChemicalCost;
use DigitalFuzed\TextileCore\Models\TextileLabourCost;
use DigitalFuzed\TextileCore\Models\TextileMachineCost;
use DigitalFuzed\TextileCore\Models\TextileMaintenanceCost;
use DigitalFuzed\TextileCore\Models\TextilePowerCost;
use DigitalFuzed\TextileCore\Models\TextileWorkflowDocument;
use DigitalFuzed\TextileInventory\Models\TextileLot;
use DigitalFuzed\TextileInventory\Models\TextileMovement;
use Illuminate\Support\Collection;
use Workdo\Hrm\Models\Attendance;
use Workdo\Hrm\Models\Department;
use Workdo\Hrm\Models\Employee;

class TextileDashboardService
{
    private const PRODUCTION_TYPES = ['production_batch', 'weaving_output', 'shift_production'];
    private const PURCHASE_TYPES = ['purchase_order', 'purchase_requisition', 'grn'];
    private const IN_PROGRESS_STATUSES = ['draft', 'pending', 'submitted'];

    public function kpis(): array
    {
        $costing = app(TextileCostingService::class)->summary();

        $documents = TextileWorkflowDocument::query()->where('created_by', $this->tenantId());
        $totalDocuments = (clone $documents)->count();
        $inProgress = (clone $documents)->whereIn('status', self::IN_PROGRESS_STATUSES)->count();

        return [
            ['label' => 'Total Revenue', 'value' => number_format($costing['total_revenue'], 2), 'hint' => $this->revenueDelta()],
            ['label' => 'Total Cost', 'value' => number_format($costing['total_cost'], 2), 'hint' => $costing['snapshots_count'].' margin snapshots'],
            ['label' => 'Total Margin', 'value' => number_format($costing['total_margin'], 2), 'hint' => 'after finalised costing'],
            ['label' => 'Margin %', 'value' => number_format($costing['margin_percent'], 2), 'hint' => 'margin / revenue'],
            ['label' => 'Workflow Documents', 'value' => $totalDocuments, 'hint' => 'all textile workflow docs'],
            ['label' => 'In Progress', 'value' => $inProgress, 'hint' => 'draft / pending / submitted'],
        ];
    }

    public function productionTrend(int $days = 14): array
    {
        return $this->quantityTrend(self::PRODUCTION_TYPES, $days);
    }

    public function dispatchTrend(int $days = 14): array
    {
        return $this->quantityTrend(['dispatch_plan'], $days);
    }

    public function financialTrend(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $snapshots = TextileWorkflowDocument::query()
            ->where('created_by', $this->tenantId())
            ->where('document_type', 'margin_snapshot')
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'metadata']);

        $byDate = [];
        foreach ($snapshots as $snapshot) {
            $date = $snapshot->created_at->format('Y-m-d');
            $meta = is_array($snapshot->metadata) ? $snapshot->metadata : [];
            $byDate[$date]['revenue'] = ($byDate[$date]['revenue'] ?? 0) + (float) ($meta['revenue_value'] ?? 0);
            $byDate[$date]['cost'] = ($byDate[$date]['cost'] ?? 0) + (float) ($meta['total_cost'] ?? 0);
        }

        return $this->fillDates($byDate, $days, fn (string $day) => [
            'date' => $day,
            'revenue' => round($byDate[$day]['revenue'] ?? 0, 2),
            'cost' => round($byDate[$day]['cost'] ?? 0, 2),
            'margin' => round(($byDate[$day]['revenue'] ?? 0) - ($byDate[$day]['cost'] ?? 0), 2),
        ]);
    }

    public function machineEfficiency(int $limit = 10): array
    {
        $logs = TextileWorkflowDocument::query()
            ->where('created_by', $this->tenantId())
            ->where('document_type', 'loom_efficiency')
            ->latest()
            ->get(['document_number', 'metadata']);

        $seen = [];
        $rows = [];
        foreach ($logs as $log) {
            $meta = is_array($log->metadata) ? $log->metadata : [];
            $machine = $meta['machine_name'] ?? $log->document_number;
            if (isset($seen[$machine])) {
                continue;
            }
            $seen[$machine] = true;
            $rows[] = ['name' => $machine, 'efficiency' => round((float) ($meta['efficiency_percent'] ?? 0), 2)];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    public function powerTrend(int $limit = 6): array
    {
        return TextilePowerCost::query()
            ->where('created_by', $this->tenantId())
            ->latest('period_start')
            ->limit($limit)
            ->get(['period_start', 'units_consumed', 'total_cost'])
            ->map(fn (TextilePowerCost $row) => [
                'period' => $row->period_start,
                'units' => (float) $row->units_consumed,
                'cost' => (float) $row->total_cost,
            ])
            ->values()
            ->all();
    }

    protected function quantityTrend(array $types, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $docs = TextileWorkflowDocument::query()
            ->where('created_by', $this->tenantId())
            ->whereIn('document_type', $types)
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'quantity']);

        $byDate = [];
        foreach ($docs as $doc) {
            $date = $doc->created_at->format('Y-m-d');
            $byDate[$date] = ($byDate[$date] ?? 0) + (float) $doc->quantity;
        }

        return $this->fillDates($byDate, $days, fn (string $day) => [
            'date' => $day,
            'quantity' => round($byDate[$day] ?? 0, 2),
        ]);
    }

    protected function fillDates(array $byDate, int $days, callable $mapper): array
    {
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $series[] = $mapper(now()->subDays($i)->format('Y-m-d'));
        }

        return $series;
    }

    public function purchase(): array
    {
        $docs = $this->tenantWorkflowDocs(self::PURCHASE_TYPES);

        return [
            'kpis' => [
                ['label' => 'Purchase Documents', 'value' => $docs->count(), 'hint' => 'orders + requisitions + GRNs'],
                ['label' => 'Purchase Orders', 'value' => $docs->where('document_type', 'purchase_order')->count(), 'hint' => 'PO documents'],
                ['label' => 'Requisitions', 'value' => $docs->where('document_type', 'purchase_requisition')->count(), 'hint' => 'PR documents'],
                ['label' => 'GRNs Received', 'value' => $docs->where('document_type', 'grn')->count(), 'hint' => 'goods receipts'],
                ['label' => 'Total Qty', 'value' => number_format($docs->sum('quantity'), 2), 'hint' => 'units across documents'],
                ['label' => 'In Progress', 'value' => $docs->whereIn('status', self::IN_PROGRESS_STATUSES)->count(), 'hint' => 'draft / pending / submitted'],
            ],
            'trend' => $this->quantityTrend(self::PURCHASE_TYPES, 14),
            'status' => $this->statusDistribution($docs),
            'types' => $this->typeDistribution($docs, 5),
        ];
    }

    public function sales(): array
    {
        $docs = $this->tenantWorkflowDocs(['sales_order']);

        return [
            'kpis' => [
                ['label' => 'Sales Orders', 'value' => $docs->count(), 'hint' => 'sales_order documents'],
                ['label' => 'Total Qty', 'value' => number_format($docs->sum('quantity'), 2), 'hint' => 'meters ordered'],
                ['label' => 'Approved', 'value' => $docs->where('status', 'approved')->count(), 'hint' => 'approved orders'],
                ['label' => 'Released', 'value' => $docs->where('status', 'released')->count(), 'hint' => 'released orders'],
                ['label' => 'In Progress', 'value' => $docs->whereIn('status', self::IN_PROGRESS_STATUSES)->count(), 'hint' => 'draft / pending / submitted'],
                ['label' => 'Avg Order Qty', 'value' => number_format($docs->avg('quantity') ?? 0, 2), 'hint' => 'meters per order'],
            ],
            'trend' => $this->quantityTrend(['sales_order'], 14),
            'status' => $this->statusDistribution($docs),
        ];
    }

    public function inventory(): array
    {
        $tenantId = $this->tenantId();
        $lots = TextileLot::query()->where('created_by', $tenantId)->get();
        $movements = TextileMovement::query()->where('created_by', $tenantId)->get();

        return [
            'kpis' => [
                ['label' => 'Total Lots', 'value' => $lots->count(), 'hint' => 'registered inventory lots'],
                ['label' => 'Available Qty', 'value' => number_format($lots->sum('available_quantity'), 2), 'hint' => 'meters on hand'],
                ['label' => 'Allocated Qty', 'value' => number_format($lots->sum(fn (TextileLot $lot) => (float) $lot->received_quantity - (float) $lot->available_quantity), 2), 'hint' => 'received minus available'],
                ['label' => 'Movements', 'value' => $movements->count(), 'hint' => 'receipt / issue / transfer / adjustment'],
                ['label' => 'Receipts Qty', 'value' => number_format($movements->where('movement_type', 'receipt')->sum('quantity'), 2), 'hint' => 'inbound quantity'],
                ['label' => 'Issues Qty', 'value' => number_format($movements->where('movement_type', 'issue')->sum('quantity'), 2), 'hint' => 'outbound quantity'],
            ],
            'movementTrend' => $this->movementTrend(),
            'lotStatus' => $lots
                ->groupBy('status')
                ->map(fn (Collection $group, string $status) => ['name' => $status, 'value' => $group->count()])
                ->values()
                ->all(),
        ];
    }

    public function finance(): array
    {
        $costing = app(TextileCostingService::class)->summary();
        $tenantId = $this->tenantId();

        $costs = [
            ['name' => 'Power', 'value' => round((float) TextilePowerCost::query()->where('created_by', $tenantId)->sum('total_cost'), 2)],
            ['name' => 'Chemicals', 'value' => round((float) TextileChemicalCost::query()->where('created_by', $tenantId)->sum('total_cost'), 2)],
            ['name' => 'Labour', 'value' => round((float) TextileLabourCost::query()->where('created_by', $tenantId)->sum('total_cost'), 2)],
            ['name' => 'Machines', 'value' => round((float) TextileMachineCost::query()->where('created_by', $tenantId)->sum('total_cost'), 2)],
            ['name' => 'Maintenance', 'value' => round((float) TextileMaintenanceCost::query()->where('created_by', $tenantId)->sum('total_cost'), 2)],
        ];

        return [
            'kpis' => [
                ['label' => 'Total Revenue', 'value' => number_format($costing['total_revenue'], 2), 'hint' => 'from margin snapshots'],
                ['label' => 'Total Cost', 'value' => number_format($costing['total_cost'], 2), 'hint' => 'from margin snapshots'],
                ['label' => 'Total Margin', 'value' => number_format($costing['total_margin'], 2), 'hint' => 'after finalised costing'],
                ['label' => 'Margin %', 'value' => number_format($costing['margin_percent'], 2), 'hint' => 'margin / revenue'],
                ['label' => 'Costing Entries', 'value' => $costing['entries_count'], 'hint' => 'costing_entry documents'],
                ['label' => 'Snapshots', 'value' => $costing['snapshots_count'], 'hint' => 'margin_snapshot documents'],
            ],
            'costBreakdown' => $costs,
            'financialTrend' => $this->financialTrend(),
            'powerTrend' => $this->powerTrend(),
        ];
    }

    public function maintenance(): array
    {
        $tenantId = $this->tenantId();
        $breakdowns = TextileBreakdown::query()->where('created_by', $tenantId)->get();
        $costs = TextileMaintenanceCost::query()->where('created_by', $tenantId)->get();

        $start = now()->subDays(13)->startOfDay();
        $byDate = [];
        foreach ($breakdowns->filter(fn (TextileBreakdown $b) => $b->created_at >= $start) as $breakdown) {
            $date = $breakdown->created_at->format('Y-m-d');
            $byDate[$date] = ($byDate[$date] ?? 0) + 1;
        }
        $trend = $this->fillDates($byDate, 14, fn (string $day) => [
            'date' => $day,
            'breakdowns' => $byDate[$day] ?? 0,
        ]);

        $downtimeByMachine = $breakdowns
            ->groupBy(fn (TextileBreakdown $b) => $b->machine_name ?: 'Unassigned')
            ->map(fn (Collection $group, string $machine) => [
                'name' => $machine,
                'hours' => round($group->sum('downtime_minutes') / 60, 2),
            ])
            ->sortByDesc('hours')
            ->take(8)
            ->values()
            ->all();

        $costTrend = $costs
            ->sortByDesc('cost_date')
            ->take(6)
            ->map(fn (TextileMaintenanceCost $cost) => [
                'period' => (string) $cost->cost_date,
                'cost' => (float) $cost->total_cost,
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'Breakdowns', 'value' => $breakdowns->count(), 'hint' => 'recorded breakdown events'],
                ['label' => 'Open', 'value' => $breakdowns->where('status', '!=', 'resolved')->count(), 'hint' => 'not yet resolved'],
                ['label' => 'Downtime', 'value' => number_format($breakdowns->sum('downtime_minutes') / 60, 2), 'hint' => 'hours across events'],
                ['label' => 'Maintenance Cost', 'value' => number_format($costs->sum('total_cost'), 2), 'hint' => 'labour + parts + external'],
                ['label' => 'Cost Entries', 'value' => $costs->count(), 'hint' => 'maintenance cost records'],
                ['label' => 'Avg Downtime', 'value' => number_format($breakdowns->avg('downtime_minutes') ? $breakdowns->avg('downtime_minutes') / 60 : 0, 2), 'hint' => 'hours per event'],
            ],
            'trend' => $trend,
            'downtimeByMachine' => $downtimeByMachine,
            'costTrend' => $costTrend,
        ];
    }

    public function hr(): array
    {
        $tenantId = $this->tenantId();
        $employees = Employee::query()->where('created_by', $tenantId)->get();
        $departments = Department::query()->where('created_by', $tenantId)->pluck('department_name', 'id');
        $attendance = Attendance::query()->where('created_by', $tenantId)->get();

        $today = now()->toDateString();
        $todayCount = $attendance->filter(fn (Attendance $a) => $a->date && $a->date->format('Y-m-d') === $today)->count();

        $start = now()->subDays(13)->startOfDay();
        $byDate = [];
        foreach ($attendance->filter(fn (Attendance $a) => $a->date && $a->date->gte($start)) as $record) {
            $date = $record->date->format('Y-m-d');
            $byDate[$date] = ($byDate[$date] ?? 0) + 1;
        }
        $trend = $this->fillDates($byDate, 14, fn (string $day) => [
            'date' => $day,
            'present' => $byDate[$day] ?? 0,
        ]);

        $byDepartment = $employees
            ->groupBy('department_id')
            ->map(fn (Collection $group, $departmentId) => [
                'name' => $departments[$departmentId] ?? 'Unassigned',
                'value' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'Employees', 'value' => $employees->count(), 'hint' => 'active employee records'],
                ['label' => 'Departments', 'value' => $departments->count(), 'hint' => 'registered departments'],
                ['label' => "Today's Attendance", 'value' => $todayCount, 'hint' => $today],
                ['label' => 'Overtime (30d)', 'value' => number_format($attendance->sum('overtime_hours'), 2), 'hint' => 'hours across records'],
                ['label' => 'Total Hours', 'value' => number_format($attendance->sum('total_hour'), 2), 'hint' => 'clocked hours'],
                ['label' => 'Absent Records', 'value' => $attendance->where('status', 'absent')->count(), 'hint' => 'status absent'],
            ],
            'attendanceTrend' => $trend,
            'employeesByDepartment' => $byDepartment,
        ];
    }

    protected function movementTrend(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = TextileMovement::query()
            ->where('created_by', $this->tenantId())
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'movement_type', 'quantity']);

        $byDate = [];
        foreach ($rows as $row) {
            $date = $row->created_at->format('Y-m-d');
            $type = $row->movement_type;
            $byDate[$date][$type] = ($byDate[$date][$type] ?? 0) + (float) $row->quantity;
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $series[] = [
                'date' => $day,
                'receipt' => round($byDate[$day]['receipt'] ?? 0, 2),
                'issue' => round($byDate[$day]['issue'] ?? 0, 2),
                'transfer' => round($byDate[$day]['transfer'] ?? 0, 2),
                'adjustment' => round($byDate[$day]['adjustment'] ?? 0, 2),
            ];
        }

        return $series;
    }

    protected function tenantWorkflowDocs(array $types): Collection
    {
        return TextileWorkflowDocument::query()
            ->where('created_by', $this->tenantId())
            ->whereIn('document_type', $types)
            ->get();
    }

    protected function statusDistribution(Collection $docs): array
    {
        return $docs
            ->groupBy('status')
            ->map(fn (Collection $group, string $status) => ['name' => $status, 'value' => $group->count()])
            ->sortBy('name')
            ->values()
            ->all();
    }

    protected function typeDistribution(Collection $docs, int $limit = 8): array
    {
        return $docs
            ->groupBy('document_type')
            ->map(fn (Collection $group, string $type) => ['name' => $type, 'value' => $group->count()])
            ->sortByDesc('value')
            ->take($limit)
            ->values()
            ->all();
    }

    protected function revenueDelta(): string
    {
        $tenantId = $this->tenantId();
        $currentStart = now()->subDays(30)->startOfDay();
        $previousStart = now()->subDays(60)->startOfDay();

        $revenueIn = function ($from, $to) use ($tenantId) {
            return (float) TextileWorkflowDocument::query()
                ->where('created_by', $tenantId)
                ->where('document_type', 'margin_snapshot')
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->get(['metadata'])
                ->sum(fn (TextileWorkflowDocument $row) => (float) (is_array($row->metadata) ? ($row->metadata['revenue_value'] ?? 0) : 0));
        };

        $current = $revenueIn($currentStart, now());
        $previous = $revenueIn($previousStart, $currentStart);

        if ($previous <= 0) {
            return $current > 0 ? 'new revenue in last 30d' : 'no revenue yet';
        }

        $delta = round(($current - $previous) / $previous * 100, 1);

        return ($delta >= 0 ? '+' : '').$delta.'% vs previous 30d';
    }

    protected function tenantId(): ?int
    {
        if (! auth()->check()) {
            return null;
        }

        return function_exists('creatorId') ? creatorId() : auth()->id();
    }
}
