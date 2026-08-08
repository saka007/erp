import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import { CalendarDays, Coins, Factory, Gauge, Layers, Package, ShoppingBag, ShoppingCart, Trash2, Truck, Users, Warehouse, Zap, Boxes, Cog, FileBarChart2 } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import NoRecordsFound from '@/components/no-records-found';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { formatTextileLabel } from '@/components/textile/textile-form-options';
import type { PageProps } from '@/types';

interface KpiItem {
    label: string;
    value: number | string;
    hint?: string;
}

interface ReportData {
    kpis: KpiItem[];
    rows: Record<string, unknown>[];
}

interface Column {
    key: string;
    header: string;
    render?: (value: any, row: Record<string, unknown>) => React.ReactNode;
}

const REPORT_SECTIONS = [
    'production', 'loom', 'operator', 'yarn-consumption', 'beam', 'grey-fabric',
    'finished-fabric', 'dispatch', 'purchase', 'sales', 'stock', 'profit',
    'machine-efficiency', 'waste-analysis', 'power-consumption', 'daily-mis',
] as const;

type ReportSection = typeof REPORT_SECTIONS[number];

function dash(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';
    return typeof value === 'string' ? formatTextileLabel(value) : String(value);
}

function num(value: unknown): string {
    return value === null || value === undefined || value === '' ? '-' : Number(value).toFixed(2);
}

function ReportPanel({
    kpis,
    rows,
    columns,
    emptyIcon,
    emptyTitle,
    emptyDescription,
    tableTitle,
    exportFilename,
    section,
    filters,
}: {
    kpis: KpiItem[];
    rows: Record<string, unknown>[];
    columns: Column[];
    emptyIcon: React.ComponentType<{ className?: string }>;
    emptyTitle: string;
    emptyDescription: string;
    tableTitle: string;
    exportFilename: string;
    section: string;
    filters: { from: string | null; to: string | null };
}) {
    const { t } = useTranslation();
    const exportParams: Record<string, string> = { section };
    if (filters.from) exportParams.from = filters.from;
    if (filters.to) exportParams.to = filters.to;
    const exportUrl = route('textile.reports.export', exportParams);
    return (
        <div className="space-y-4">
            <TextileKpiOverview items={kpis} />
            <TextileDataTableSection title={t(tableTitle)}>
                <TextileDataTableCard
                    data={rows}
                    columns={columns}
                    exportable
                    exportFilename={exportFilename}
                    exportUrl={exportUrl}
                    emptyState={
                        <NoRecordsFound
                            icon={emptyIcon}
                            title={t(emptyTitle)}
                            description={t(emptyDescription)}
                        />
                    }
                />
            </TextileDataTableSection>
        </div>
    );
}

export default function Index({
    filters,
    production,
    loom,
    operator,
    yarnConsumption,
    beam,
    greyFabric,
    finishedFabric,
    dispatch,
    purchase,
    sales,
    stock,
    profit,
    machineEfficiency,
    wasteAnalysis,
    powerConsumption,
    dailyMis,
}: {
    filters: { from: string | null; to: string | null };
    production: ReportData;
    loom: ReportData;
    operator: ReportData;
    yarnConsumption: ReportData;
    beam: ReportData;
    greyFabric: ReportData;
    finishedFabric: ReportData;
    dispatch: ReportData;
    purchase: ReportData;
    sales: ReportData;
    stock: ReportData;
    profit: ReportData;
    machineEfficiency: ReportData;
    wasteAnalysis: ReportData;
    powerConsumption: ReportData;
    dailyMis: ReportData;
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;

    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const activeSection: ReportSection = sectionParam && REPORT_SECTIONS.includes(sectionParam as ReportSection)
        ? (sectionParam as ReportSection)
        : REPORT_SECTIONS[0];

    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const applyFilters = () => {
        const params: Record<string, string> = {};
        if (from) params.from = from;
        if (to) params.to = to;
        if (activeSection) params.section = activeSection;
        router.get(route('textile.reports.index'), params, { preserveState: true, replace: true });
    };

    const dateColumn = (key = 'date'): Column => ({ key, header: t('Date'), render: (value) => dash(value) });

    const renderTab = (section: ReportSection) => {
        switch (section) {
            case 'production':
                return (
                    <ReportPanel
                        kpis={production.kpis}
                        rows={production.rows}
                        tableTitle="Production Documents"
                        exportFilename="production-report"
                        section="production"
                        filters={filters}
                        emptyIcon={Factory}
                        emptyTitle="No production documents found"
                        emptyDescription="Production batches, weaving outputs and shift productions will appear here."
                        columns={[
                            { key: 'type', header: t('Type'), render: (value) => dash(value) },
                            { key: 'document_number', header: t('Document') },
                            { key: 'party_name', header: t('Party'), render: (value) => dash(value) },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'unit', header: t('Unit'), render: (value) => dash(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'loom':
                return (
                    <ReportPanel
                        kpis={loom.kpis}
                        rows={loom.rows}
                        tableTitle="Loom Machines"
                        exportFilename="loom-report"
                        section="loom"
                        filters={filters}
                        emptyIcon={Cog}
                        emptyTitle="No loom masters found"
                        emptyDescription="Approved loom masters will appear here."
                        columns={[
                            { key: 'machine_name', header: t('Machine') },
                            { key: 'machine_type', header: t('Type'), render: (value) => dash(value) },
                            { key: 'operator_name', header: t('Operator'), render: (value) => dash(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'operator':
                return (
                    <ReportPanel
                        kpis={operator.kpis}
                        rows={operator.rows}
                        section="operator"
                        filters={filters}
                        tableTitle="Operator Efficiency"
                        exportFilename="operator-report"
                        emptyIcon={Users}
                        emptyTitle="No operator efficiency records found"
                        emptyDescription="Operator efficiency logs will appear here."
                        columns={[
                            { key: 'operator_name', header: t('Operator'), render: (value) => dash(value) },
                            { key: 'shift', header: t('Shift'), render: (value) => dash(value) },
                            { key: 'planned_quantity', header: t('Planned'), render: (value) => num(value) },
                            { key: 'actual_quantity', header: t('Actual'), render: (value) => num(value) },
                            { key: 'efficiency_percent', header: t('Efficiency %'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'yarn-consumption':
                return (
                    <ReportPanel
                        kpis={yarnConsumption.kpis}
                        rows={yarnConsumption.rows}
                        section="yarn-consumption"
                        filters={filters}
                        tableTitle="Yarn & Chemical Consumption"
                        exportFilename="yarn-consumption-report"
                        emptyIcon={Layers}
                        emptyTitle="No yarn allocation or chemical consumption found"
                        emptyDescription="Yarn allocations and chemical consumption records will appear here."
                        columns={[
                            { key: 'type', header: t('Type'), render: (value) => dash(value) },
                            { key: 'document_number', header: t('Document') },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'party_name', header: t('Party'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'unit', header: t('Unit'), render: (value) => dash(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'beam':
                return (
                    <ReportPanel
                        kpis={beam.kpis}
                        rows={beam.rows}
                        section="beam"
                        filters={filters}
                        tableTitle="Beam Register"
                        exportFilename="beam-report"
                        emptyIcon={Boxes}
                        emptyTitle="No beams found"
                        emptyDescription="Registered beams will appear here."
                        columns={[
                            { key: 'document_number', header: t('Beam No') },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'party_name', header: t('Party'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'unit', header: t('Unit'), render: (value) => dash(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'grey-fabric':
                return (
                    <ReportPanel
                        section="grey-fabric"
                        filters={filters}
                        kpis={greyFabric.kpis}
                        rows={greyFabric.rows}
                        tableTitle="Grey Fabric Rolls"
                        exportFilename="grey-fabric-report"
                        emptyIcon={Package}
                        emptyTitle="No grey fabric rolls found"
                        emptyDescription="Generated grey fabric rolls will appear here."
                        columns={[
                            { key: 'roll_number', header: t('Roll No') },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'roll_length', header: t('Length'), render: (value) => num(value) },
                            { key: 'roll_weight', header: t('Weight'), render: (value) => num(value) },
                            { key: 'grade', header: t('Grade'), render: (value) => dash(value) },
                            { key: 'defects', header: t('Defects'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'finished-fabric':
                return (
                    <ReportPanel
                        section="finished-fabric"
                        filters={filters}
                        kpis={finishedFabric.kpis}
                        rows={finishedFabric.rows}
                        tableTitle="Finished Fabric Stock"
                        exportFilename="finished-fabric-report"
                        emptyIcon={Warehouse}
                        emptyTitle="No finished lots found"
                        emptyDescription="Active inventory lots will appear here."
                        columns={[
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'batch_number', header: t('Batch'), render: (value) => dash(value) },
                            { key: 'received_quantity', header: t('Received'), render: (value) => num(value) },
                            { key: 'available_quantity', header: t('Available'), render: (value) => num(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            { key: 'frozen', header: t('Frozen'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'dispatch':
                return (
                    <ReportPanel
                        kpis={dispatch.kpis}
                        rows={dispatch.rows}
                        section="dispatch"
                        filters={filters}
                        tableTitle="Dispatch Plans"
                        exportFilename="dispatch-report"
                        emptyIcon={Truck}
                        emptyTitle="No dispatch plans found"
                        emptyDescription="Dispatch planning documents will appear here."
                        columns={[
                            { key: 'document_number', header: t('Document') },
                            { key: 'party_name', header: t('Party'), render: (value) => dash(value) },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'dispatch_mode', header: t('Mode'), render: (value) => dash(value) },
                            { key: 'truck_number', header: t('Truck'), render: (value) => dash(value) },
                            { key: 'freight_amount', header: t('Freight'), render: (value) => num(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'purchase':
                return (
                    <ReportPanel
                        kpis={purchase.kpis}
                        rows={purchase.rows}
                        tableTitle="Purchase Documents"
                        exportFilename="purchase-report"
                        section="purchase"
                        filters={filters}
                        emptyIcon={ShoppingCart}
                        emptyTitle="No purchase documents found"
                        emptyDescription="Purchase orders, requisitions and GRNs (Goods Received Notes) will appear here."
                        columns={[
                            { key: 'type', header: t('Type'), render: (value) => dash(value) },
                            { key: 'document_number', header: t('Document') },
                            { key: 'party_name', header: t('Supplier'), render: (value) => dash(value) },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'unit', header: t('Unit'), render: (value) => dash(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'sales':
                return (
                    <ReportPanel
                        kpis={sales.kpis}
                        rows={sales.rows}
                        tableTitle="Sales Orders"
                        exportFilename="sales-report"
                        section="sales"
                        filters={filters}
                        emptyIcon={ShoppingBag}
                        emptyTitle="No sales orders found"
                        emptyDescription="Sales order documents will appear here."
                        columns={[
                            { key: 'document_number', header: t('Order No') },
                            { key: 'party_name', header: t('Customer'), render: (value) => dash(value) },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'unit', header: t('Unit'), render: (value) => dash(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'stock':
                return (
                    <ReportPanel
                        kpis={stock.kpis}
                        rows={stock.rows}
                        section="stock"
                        filters={filters}
                        tableTitle="Stock Movements"
                        exportFilename="stock-report"
                        emptyIcon={Warehouse}
                        emptyTitle="No stock movements found"
                        emptyDescription="Receipts, issues and transfers will appear here."
                        columns={[
                            { key: 'movement_type', header: t('Type'), render: (value) => dash(value) },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'location_from', header: t('From'), render: (value) => dash(value) },
                            { key: 'location_to', header: t('To'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'status', header: t('Status'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'profit':
                return (
                    <ReportPanel
                        kpis={profit.kpis}
                        rows={profit.rows}
                        section="profit"
                        filters={filters}
                        tableTitle="Margin Snapshots"
                        exportFilename="profit-report"
                        emptyIcon={Coins}
                        emptyTitle="No margin snapshots found"
                        emptyDescription="Finalized costing entries post margin snapshots which appear here."
                        columns={[
                            { key: 'document_number', header: t('Document') },
                            { key: 'party_name', header: t('Party'), render: (value) => dash(value) },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'revenue_value', header: t('Revenue'), render: (value) => num(value) },
                            { key: 'total_cost', header: t('Cost'), render: (value) => num(value) },
                            { key: 'margin', header: t('Margin'), render: (value) => num(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'machine-efficiency':
                return (
                    <ReportPanel
                        section="machine-efficiency"
                        filters={filters}
                        kpis={machineEfficiency.kpis}
                        rows={machineEfficiency.rows}
                        tableTitle="Machine Efficiency Logs"
                        exportFilename="machine-efficiency-report"
                        emptyIcon={Gauge}
                        emptyTitle="No machine efficiency records found"
                        emptyDescription="Loom efficiency logs will appear here."
                        columns={[
                            { key: 'machine_name', header: t('Machine'), render: (value) => dash(value) },
                            { key: 'operator_name', header: t('Operator'), render: (value) => dash(value) },
                            { key: 'planned_quantity', header: t('Planned'), render: (value) => num(value) },
                            { key: 'actual_quantity', header: t('Actual'), render: (value) => num(value) },
                            { key: 'runtime_hours', header: t('Runtime'), render: (value) => num(value) },
                            { key: 'downtime_hours', header: t('Downtime'), render: (value) => num(value) },
                            { key: 'efficiency_percent', header: t('Efficiency %'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'waste-analysis':
                return (
                    <ReportPanel
                        section="waste-analysis"
                        filters={filters}
                        kpis={wasteAnalysis.kpis}
                        rows={wasteAnalysis.rows}
                        tableTitle="Waste & Rework"
                        exportFilename="waste-analysis-report"
                        emptyIcon={Trash2}
                        emptyTitle="No waste or rework records found"
                        emptyDescription="Recorded waste and rework entries will appear here."
                        columns={[
                            { key: 'type', header: t('Type'), render: (value) => dash(value) },
                            { key: 'document_number', header: t('Document') },
                            { key: 'lot_reference', header: t('Lot'), render: (value) => dash(value) },
                            { key: 'party_name', header: t('Party'), render: (value) => dash(value) },
                            { key: 'quantity', header: t('Qty'), render: (value) => num(value) },
                            { key: 'unit', header: t('Unit'), render: (value) => dash(value) },
                            dateColumn(),
                        ]}
                    />
                );
            case 'power-consumption':
                return (
                    <ReportPanel
                        section="power-consumption"
                        filters={filters}
                        kpis={powerConsumption.kpis}
                        rows={powerConsumption.rows}
                        tableTitle="Power Consumption"
                        exportFilename="power-consumption-report"
                        emptyIcon={Zap}
                        emptyTitle="No power cost periods found"
                        emptyDescription="Recorded power billing periods will appear here."
                        columns={[
                            { key: 'period_start', header: t('From') },
                            { key: 'period_end', header: t('To') },
                            { key: 'meter_reading_start', header: t('Reading Start'), render: (value) => num(value) },
                            { key: 'meter_reading_end', header: t('Reading End'), render: (value) => num(value) },
                            { key: 'units_consumed', header: t('Units'), render: (value) => num(value) },
                            { key: 'rate_per_unit', header: t('Rate'), render: (value) => num(value) },
                            { key: 'total_cost', header: t('Total Cost'), render: (value) => num(value) },
                        ]}
                    />
                );
            case 'daily-mis':
                return (
                    <ReportPanel
                        section="daily-mis"
                        filters={filters}
                        kpis={dailyMis.kpis}
                        rows={dailyMis.rows}
                        tableTitle="Daily MIS"
                        exportFilename="daily-mis-report"
                        emptyIcon={CalendarDays}
                        emptyTitle="No activity for the selected period"
                        emptyDescription="Day-wise production, dispatch, revenue and waste summary will appear here."
                        columns={[
                            { key: 'date', header: t('Date') },
                            { key: 'production_qty', header: t('Production'), render: (value) => dash(value) },
                            { key: 'dispatches', header: t('Dispatches'), render: (value) => dash(value) },
                            { key: 'revenue', header: t('Revenue'), render: (value) => dash(value) },
                            { key: 'cost', header: t('Cost'), render: (value) => dash(value) },
                            { key: 'waste_qty', header: t('Waste'), render: (value) => dash(value) },
                        ]}
                    />
                );
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Reports') }]}
            pageTitle={t('Textile Reports')}
        >
            <Head title={t('Textile Reports')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border/70 bg-card/60 p-4">
                    <div className="space-y-1">
                        <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{t('From')}</label>
                        <Input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="w-44" />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{t('To')}</label>
                        <Input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="w-44" />
                    </div>
                    <Button variant="outline" size="sm" onClick={applyFilters} className="mb-0.5">
                        <FileBarChart2 className="mr-2 h-4 w-4" />
                        {t('Apply Filters')}
                    </Button>
                </div>

                <Tabs value={activeSection} onValueChange={(value) => {
                    const params: Record<string, string> = {};
                    if (filters.from) params.from = filters.from;
                    if (filters.to) params.to = filters.to;
                    params.section = value;
                    router.get(route('textile.reports.index'), params, { preserveState: true, replace: true });
                }}>
                    <TabsList className="flex flex-wrap">
                        <TabsTrigger value="production">{t('Production')}</TabsTrigger>
                        <TabsTrigger value="loom">{t('Loom')}</TabsTrigger>
                        <TabsTrigger value="operator">{t('Operator')}</TabsTrigger>
                        <TabsTrigger value="yarn-consumption">{t('Yarn')}</TabsTrigger>
                        <TabsTrigger value="beam">{t('Beam')}</TabsTrigger>
                        <TabsTrigger value="grey-fabric">{t('Grey Fabric')}</TabsTrigger>
                        <TabsTrigger value="finished-fabric">{t('Finished')}</TabsTrigger>
                        <TabsTrigger value="dispatch">{t('Dispatch')}</TabsTrigger>
                        <TabsTrigger value="purchase">{t('Purchase')}</TabsTrigger>
                        <TabsTrigger value="sales">{t('Sales')}</TabsTrigger>
                        <TabsTrigger value="stock">{t('Stock')}</TabsTrigger>
                        <TabsTrigger value="profit">{t('Profit')}</TabsTrigger>
                        <TabsTrigger value="machine-efficiency">{t('Efficiency')}</TabsTrigger>
                        <TabsTrigger value="waste-analysis">{t('Waste')}</TabsTrigger>
                        <TabsTrigger value="power-consumption">{t('Power')}</TabsTrigger>
                        <TabsTrigger value="daily-mis">{t('Daily MIS')}</TabsTrigger>
                    </TabsList>

                    {REPORT_SECTIONS.map((section) => (
                        <TabsContent key={section} value={section} className="space-y-4">
                            {renderTab(section)}
                        </TabsContent>
                    ))}
                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
