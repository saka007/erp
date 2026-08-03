<?php

namespace DigitalFuzed\TextileCore\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use DigitalFuzed\TextileCore\Services\TextileReportsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TextileReportsController extends Controller
{
    private const SECTIONS = [
        'production', 'loom', 'operator', 'yarn-consumption', 'beam', 'grey-fabric',
        'finished-fabric', 'dispatch', 'purchase', 'sales', 'stock', 'profit',
        'machine-efficiency', 'waste-analysis', 'power-consumption', 'daily-mis',
    ];

    private const COLUMNS = [
        'production' => ['type' => 'Type', 'document_number' => 'Document', 'party_name' => 'Party', 'lot_reference' => 'Lot', 'quantity' => 'Qty', 'unit' => 'Unit', 'status' => 'Status', 'date' => 'Date'],
        'loom' => ['machine_name' => 'Machine', 'machine_type' => 'Type', 'operator_name' => 'Operator', 'status' => 'Status', 'date' => 'Date'],
        'operator' => ['operator_name' => 'Operator', 'shift' => 'Shift', 'planned_quantity' => 'Planned', 'actual_quantity' => 'Actual', 'efficiency_percent' => 'Efficiency %', 'date' => 'Date'],
        'yarn-consumption' => ['type' => 'Type', 'document_number' => 'Document', 'lot_reference' => 'Lot', 'party_name' => 'Party', 'quantity' => 'Qty', 'unit' => 'Unit', 'status' => 'Status', 'date' => 'Date'],
        'beam' => ['document_number' => 'Beam No', 'lot_reference' => 'Lot', 'party_name' => 'Party', 'quantity' => 'Qty', 'unit' => 'Unit', 'status' => 'Status', 'date' => 'Date'],
        'grey-fabric' => ['roll_number' => 'Roll No', 'lot_reference' => 'Lot', 'roll_length' => 'Length', 'roll_weight' => 'Weight', 'grade' => 'Grade', 'defects' => 'Defects', 'date' => 'Date'],
        'finished-fabric' => ['lot_reference' => 'Lot', 'batch_number' => 'Batch', 'received_quantity' => 'Received', 'available_quantity' => 'Available', 'status' => 'Status', 'frozen' => 'Frozen', 'date' => 'Date'],
        'dispatch' => ['document_number' => 'Document', 'party_name' => 'Party', 'lot_reference' => 'Lot', 'quantity' => 'Qty', 'dispatch_mode' => 'Mode', 'truck_number' => 'Truck', 'freight_amount' => 'Freight', 'status' => 'Status', 'date' => 'Date'],
        'purchase' => ['type' => 'Type', 'document_number' => 'Document', 'party_name' => 'Supplier', 'lot_reference' => 'Lot', 'quantity' => 'Qty', 'unit' => 'Unit', 'status' => 'Status', 'date' => 'Date'],
        'sales' => ['document_number' => 'Order No', 'party_name' => 'Customer', 'lot_reference' => 'Lot', 'quantity' => 'Qty', 'unit' => 'Unit', 'status' => 'Status', 'date' => 'Date'],
        'stock' => ['movement_type' => 'Type', 'lot_reference' => 'Lot', 'location_from' => 'From', 'location_to' => 'To', 'quantity' => 'Qty', 'status' => 'Status', 'date' => 'Date'],
        'profit' => ['document_number' => 'Document', 'party_name' => 'Party', 'lot_reference' => 'Lot', 'revenue_value' => 'Revenue', 'total_cost' => 'Cost', 'margin' => 'Margin', 'date' => 'Date'],
        'machine-efficiency' => ['machine_name' => 'Machine', 'operator_name' => 'Operator', 'planned_quantity' => 'Planned', 'actual_quantity' => 'Actual', 'runtime_hours' => 'Runtime', 'downtime_hours' => 'Downtime', 'efficiency_percent' => 'Efficiency %', 'date' => 'Date'],
        'waste-analysis' => ['type' => 'Type', 'document_number' => 'Document', 'lot_reference' => 'Lot', 'party_name' => 'Party', 'quantity' => 'Qty', 'unit' => 'Unit', 'date' => 'Date'],
        'power-consumption' => ['period_start' => 'From', 'period_end' => 'To', 'meter_reading_start' => 'Reading Start', 'meter_reading_end' => 'Reading End', 'units_consumed' => 'Units', 'rate_per_unit' => 'Rate', 'total_cost' => 'Total Cost'],
        'daily-mis' => ['date' => 'Date', 'production_qty' => 'Production', 'dispatches' => 'Dispatches', 'revenue' => 'Revenue', 'cost' => 'Cost', 'waste_qty' => 'Waste'],
    ];

    private const TITLES = [
        'production' => 'Production Report', 'loom' => 'Loom Report', 'operator' => 'Operator Report',
        'yarn-consumption' => 'Yarn Consumption Report', 'beam' => 'Beam Report', 'grey-fabric' => 'Grey Fabric Report',
        'finished-fabric' => 'Finished Fabric Report', 'dispatch' => 'Dispatch Report', 'purchase' => 'Purchase Report',
        'sales' => 'Sales Report', 'stock' => 'Stock Report', 'profit' => 'Profit Report',
        'machine-efficiency' => 'Machine Efficiency Report', 'waste-analysis' => 'Waste Analysis Report',
        'power-consumption' => 'Power Consumption Report', 'daily-mis' => 'Daily MIS Report',
    ];

    public function __construct(protected TextileReportsService $reportsService)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeTextileAccess();

        $filters = [
            'from' => $request->query('from') ?: null,
            'to' => $request->query('to') ?: null,
        ];

        return Inertia::render('DigitalFuzedTextileCore/Reports/Index', [
            'filters' => $filters,
            'production' => $this->reportsService->production($filters),
            'loom' => $this->reportsService->loom($filters),
            'operator' => $this->reportsService->operator($filters),
            'yarnConsumption' => $this->reportsService->yarnConsumption($filters),
            'beam' => $this->reportsService->beam($filters),
            'greyFabric' => $this->reportsService->greyFabric($filters),
            'finishedFabric' => $this->reportsService->finishedFabric($filters),
            'dispatch' => $this->reportsService->dispatch($filters),
            'purchase' => $this->reportsService->purchase($filters),
            'sales' => $this->reportsService->sales($filters),
            'stock' => $this->reportsService->stock($filters),
            'profit' => $this->reportsService->profit($filters),
            'machineEfficiency' => $this->reportsService->machineEfficiency($filters),
            'wasteAnalysis' => $this->reportsService->wasteAnalysis($filters),
            'powerConsumption' => $this->reportsService->powerConsumption($filters),
            'dailyMis' => $this->reportsService->dailyMis($filters),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeTextileAccess();

        $section = in_array($request->query('section'), self::SECTIONS, true) ? $request->query('section') : 'production';
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $filters = [
            'from' => $request->query('from') ?: null,
            'to' => $request->query('to') ?: null,
        ];

        $report = $this->reportFor($section, $filters);
        $columns = self::COLUMNS[$section];
        $rows = array_map(fn (array $row) => array_map(
            fn (string $key) => $row[$key] ?? '',
            array_keys($columns)
        ), $report['rows']);

        $filename = Str::slug(self::TITLES[$section]) . ($filters['from'] || $filters['to'] ? '-' . ($filters['from'] ?? 'start') . '-' . ($filters['to'] ?? 'end') : '');

        if ($format === 'pdf') {
            return $this->downloadPdf(self::TITLES[$section], $columns, $rows, $filters, $filename);
        }

        return $this->downloadXlsx(self::TITLES[$section], $columns, $rows, $filename);
    }

    private function reportFor(string $section, array $filters): array
    {
        return match ($section) {
            'loom' => $this->reportsService->loom($filters),
            'operator' => $this->reportsService->operator($filters),
            'yarn-consumption' => $this->reportsService->yarnConsumption($filters),
            'beam' => $this->reportsService->beam($filters),
            'grey-fabric' => $this->reportsService->greyFabric($filters),
            'finished-fabric' => $this->reportsService->finishedFabric($filters),
            'dispatch' => $this->reportsService->dispatch($filters),
            'purchase' => $this->reportsService->purchase($filters),
            'sales' => $this->reportsService->sales($filters),
            'stock' => $this->reportsService->stock($filters),
            'profit' => $this->reportsService->profit($filters),
            'machine-efficiency' => $this->reportsService->machineEfficiency($filters),
            'waste-analysis' => $this->reportsService->wasteAnalysis($filters),
            'power-consumption' => $this->reportsService->powerConsumption($filters),
            'daily-mis' => $this->reportsService->dailyMis($filters),
            default => $this->reportsService->production($filters),
        };
    }

    private function downloadXlsx(string $title, array $columns, array $rows, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit($title, 31, ''));

        $col = 1;
        foreach ($columns as $header) {
            $sheet->setCellValue([$col++, 1], $header);
        }
        foreach (range('A', chr(64 + count($columns))) as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $sheet->getStyle('A1:' . chr(64 + count($columns)) . '1')->getFont()->setBold(true);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($row as $value) {
                $sheet->setCellValue([$col++, $rowNumber], $value);
            }
            $rowNumber++;
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'textile-export');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend(true);
    }

    private function downloadPdf(string $title, array $columns, array $rows, array $filters, string $filename)
    {
        $html = view('digitalfuzed-textile-core::reports.export', [
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'filters' => $filters,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        return Pdf::loadHTML($html)->download($filename . '.pdf');
    }

    private function authorizeTextileAccess(): void
    {
        if (!auth()->check()) {
            abort(401);
        }
        if (auth()->user()->type !== 'company' && auth()->user()->type !== 'super admin') {
            abort(403);
        }
    }
}
