import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import { BadgeDollarSign, Beaker, Calculator, Cog, IndianRupee, Plus, TrendingUp, Users, Zap } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { formatTextileOptionLabel } from '@/components/textile/textile-form-options';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import type { PageProps } from '@/types';

interface EntityOption {
    id: number;
    label: string;
}

interface MachineCost {
    id: number;
    machine_name?: string | null;
    machine_type?: string | null;
    period_start?: string | null;
    period_end?: string | null;
    depreciation_cost: string;
    maintenance_cost: string;
    power_cost: string;
    labor_cost: string;
    other_cost: string;
    total_cost: string;
    notes?: string | null;
}

interface PowerCost {
    id: number;
    period_start?: string | null;
    period_end?: string | null;
    meter_reading_start: string;
    meter_reading_end: string;
    units_consumed: string;
    rate_per_unit: string;
    total_cost: string;
    allocation_notes?: string | null;
}

interface ChemicalCost {
    id: number;
    chemical_date?: string | null;
    chemical_name: string;
    process_stage?: string | null;
    quantity: string;
    unit?: string | null;
    unit_cost: string;
    total_cost: string;
    batch_reference?: string | null;
    notes?: string | null;
}

interface LabourCost {
    id: number;
    labour_date?: string | null;
    cost_center_name?: string | null;
    shift_name?: string | null;
    worker_count: number;
    hours_worked: string;
    rate_per_hour: string;
    total_cost: string;
    notes?: string | null;
}

interface CostEntryRow {
    id: number;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: number;
    total_cost: number;
    cost_per_meter?: number | null;
    cost_per_roll?: number | null;
    rolls_count?: number;
}

interface CostMetric {
    rows: CostEntryRow[];
    total_cost: number;
    total_meters: number;
    total_rolls: number;
    average_cost_per_meter: number;
    average_cost_per_roll: number;
}

interface Profitability {
    snapshots_count: number;
    total_revenue: number;
    product_cost: number;
    operating_costs: number;
    breakdown: {
        maintenance: number;
        machine: number;
        power: number;
        chemical: number;
        labour: number;
    };
    margin_value: number;
    margin_percent: number;
}

const FINANCE_SECTIONS = ['cost-per-meter', 'cost-per-roll', 'machine-cost', 'power-cost', 'chemical-cost', 'labour-cost', 'profitability'] as const;
type FinanceSection = typeof FINANCE_SECTIONS[number];

function formatLabel(value?: string | null) {
    return value ? formatTextileOptionLabel(value) : '-';
}

function formatDate(value?: string | null) {
    return value ? value.slice(0, 10) : '-';
}

export default function Index({
    machineCosts,
    powerCosts,
    chemicalCosts,
    labourCosts,
    machineOptions,
    machineTypeOptions,
    processStageOptions,
    shiftOptions,
    costCenterOptions,
    costPerMeter,
    costPerRoll,
    profitability,
}: {
    machineCosts: MachineCost[];
    powerCosts: PowerCost[];
    chemicalCosts: ChemicalCost[];
    labourCosts: LabourCost[];
    machineOptions: EntityOption[];
    machineTypeOptions: string[];
    processStageOptions: string[];
    shiftOptions: string[];
    costCenterOptions: EntityOption[];
    costPerMeter: CostMetric;
    costPerRoll: CostMetric;
    profitability: Profitability;
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;

    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const visibleSections: FinanceSection[] = [...FINANCE_SECTIONS];
    const activeSection = sectionParam && visibleSections.includes(sectionParam as FinanceSection)
        ? sectionParam as FinanceSection
        : visibleSections[0];

    const resolvedMachineOptions = machineOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedMachineTypeOptions = machineTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedProcessStageOptions = processStageOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedShiftOptions = shiftOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedCostCenterOptions = costCenterOptions.map((value) => ({ value: String(value.id), label: value.label }));

    const machineCostForm = useForm({
        machine_id: '',
        machine_type: '',
        period_start: '',
        period_end: '',
        depreciation_cost: '',
        maintenance_cost: '',
        power_cost: '',
        labor_cost: '',
        other_cost: '',
        notes: '',
    });

    const powerCostForm = useForm({
        period_start: '',
        period_end: '',
        meter_reading_start: '',
        meter_reading_end: '',
        rate_per_unit: '',
        allocation_notes: '',
    });

    const chemicalCostForm = useForm({
        chemical_date: '',
        chemical_name: '',
        process_stage: '',
        quantity: '',
        unit: 'kg',
        unit_cost: '',
        batch_reference: '',
        notes: '',
    });

    const labourCostForm = useForm({
        labour_date: '',
        cost_center_id: '',
        shift_name: '',
        worker_count: '',
        hours_worked: '',
        rate_per_hour: '',
        notes: '',
    });

    const totalOperatingCosts = Object.values(profitability.breakdown).reduce((sum, value) => sum + Number(value || 0), 0);

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Finance') }]}
            pageTitle={t('Textile Finance')}
        >
            <Head title={t('Textile Finance')} />

            <div className="space-y-6">
                <TextileKpiOverview
                    title={t('Finance Overview')}
                    className="mb-6"
                    items={[
                        { label: t('Total Revenue'), value: Number(profitability.total_revenue).toFixed(2), hint: t('From finalized margin snapshots') },
                        { label: t('Total Cost'), value: Number(profitability.product_cost).toFixed(2), hint: t('Product cost across costing entries') },
                        { label: t('Margin'), value: `${Number(profitability.margin_percent).toFixed(2)}%`, hint: t('Revenue minus product and operating costs') },
                        { label: t('Cost Per Meter'), value: Number(costPerMeter.average_cost_per_meter).toFixed(4), hint: t('Weighted average across approved entries') },
                        { label: t('Cost Per Roll'), value: Number(costPerRoll.average_cost_per_roll).toFixed(2), hint: t('Average across entries with roll counts') },
                        { label: t('Operating Costs'), value: totalOperatingCosts.toFixed(2), hint: t('Maintenance, machine, power, chemical, labour') },
                    ]}
                />

                <Tabs value={activeSection} onValueChange={(value) => {
                    const search = new URLSearchParams(window.location.search);
                    search.set('section', value);
                    router.get(route('textile.finance.index'), Object.fromEntries(search), { preserveState: true, replace: true });
                }}>
                    <TabsList>
                        <TabsTrigger value="cost-per-meter">{t('Cost Per Meter')}</TabsTrigger>
                        <TabsTrigger value="cost-per-roll">{t('Cost Per Roll')}</TabsTrigger>
                        <TabsTrigger value="machine-cost">{t('Machine Cost')}</TabsTrigger>
                        <TabsTrigger value="power-cost">{t('Power Cost')}</TabsTrigger>
                        <TabsTrigger value="chemical-cost">{t('Chemical Cost')}</TabsTrigger>
                        <TabsTrigger value="labour-cost">{t('Labour Cost')}</TabsTrigger>
                        <TabsTrigger value="profitability">{t('Profitability')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="cost-per-meter" className="space-y-4">
                        <TextileDataTableSection title={t('Cost Per Meter')}>
                            <TextileDataTableCard
                                data={costPerMeter.rows}
                                emptyState={<NoRecordsFound icon={Calculator} title={t('No approved costing entries found')} description={t('Finalize costing entries in the Costing workspace to see cost per meter.')} />}
                                columns={[
                                    { key: 'document_number', header: t('Entry') },
                                    { key: 'party_name', header: t('Party') },
                                    { key: 'lot_reference', header: t('Lot') },
                                    { key: 'quantity', header: t('Meters') },
                                    { key: 'total_cost', header: t('Total Cost') },
                                    { key: 'cost_per_meter', header: t('Cost / Meter'), render: (value) => value != null ? Number(value).toFixed(4) : '-' },
                                ]}
                            />
                        </TextileDataTableSection>
                    </TabsContent>

                    <TabsContent value="cost-per-roll" className="space-y-4">
                        <TextileDataTableSection title={t('Cost Per Roll')}>
                            <TextileDataTableCard
                                data={costPerRoll.rows}
                                emptyState={<NoRecordsFound icon={BadgeDollarSign} title={t('No roll costing data found')} description={t('Add a roll count when capturing costing entries to compute cost per roll.')} />}
                                columns={[
                                    { key: 'document_number', header: t('Entry') },
                                    { key: 'party_name', header: t('Party') },
                                    { key: 'lot_reference', header: t('Lot') },
                                    { key: 'quantity', header: t('Meters') },
                                    { key: 'rolls_count', header: t('Rolls') },
                                    { key: 'total_cost', header: t('Total Cost') },
                                    { key: 'cost_per_roll', header: t('Cost / Roll'), render: (value) => value != null ? Number(value).toFixed(2) : '-' },
                                ]}
                            />
                        </TextileDataTableSection>
                    </TabsContent>

                    <TabsContent value="machine-cost" className="space-y-4">
                        <TextileFormCard title={t('Record Machine Cost')} icon={Cog}>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    machineCostForm.post(route('textile.finance.machine-costs.store'), {
                                        onSuccess: () => machineCostForm.reset(),
                                    });
                                }}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                <SelectField label={t('Machine')} value={machineCostForm.data.machine_id} onChange={(value) => machineCostForm.setData('machine_id', value)} options={resolvedMachineOptions} includeEmpty emptyLabel={t('Select machine')} helperText={t('Registered loom masters are listed.')} disabled={resolvedMachineOptions.length === 0} disabledReason={t('No active machine found. Register loom master first.')} />
                                <SelectField label={t('Machine Type')} value={machineCostForm.data.machine_type} onChange={(value) => machineCostForm.setData('machine_type', value)} options={resolvedMachineTypeOptions} includeEmpty emptyLabel={t('Select machine type')} />
                                <Field label={t('Period Start')} type="date" value={machineCostForm.data.period_start} onChange={(value) => machineCostForm.setData('period_start', value)} />
                                <Field label={t('Period End')} type="date" value={machineCostForm.data.period_end} onChange={(value) => machineCostForm.setData('period_end', value)} />
                                <Field label={t('Depreciation Cost')} type="number" min="0" step="0.01" value={machineCostForm.data.depreciation_cost} onChange={(value) => machineCostForm.setData('depreciation_cost', value)} />
                                <Field label={t('Maintenance Cost')} type="number" min="0" step="0.01" value={machineCostForm.data.maintenance_cost} onChange={(value) => machineCostForm.setData('maintenance_cost', value)} />
                                <Field label={t('Power Cost')} type="number" min="0" step="0.01" value={machineCostForm.data.power_cost} onChange={(value) => machineCostForm.setData('power_cost', value)} />
                                <Field label={t('Labour Cost')} type="number" min="0" step="0.01" value={machineCostForm.data.labor_cost} onChange={(value) => machineCostForm.setData('labor_cost', value)} />
                                <Field label={t('Other Cost')} type="number" min="0" step="0.01" value={machineCostForm.data.other_cost} onChange={(value) => machineCostForm.setData('other_cost', value)} />
                                <Field label={t('Notes')} value={machineCostForm.data.notes} onChange={(value) => machineCostForm.setData('notes', value)} />
                                <Button type="submit" disabled={machineCostForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Save Machine Cost')}</Button>
                            </form>
                        </TextileFormCard>

                        <TextileDataTableSection title={t('Machine Cost Records')}>
                            <TextileDataTableCard
                                data={machineCosts}
                                emptyState={<NoRecordsFound icon={Cog} title={t('No machine costs found')} description={t('Record machine level cost allocations.')} />}
                                columns={[
                                    { key: 'machine_name', header: t('Machine'), render: (value) => formatLabel(value) },
                                    { key: 'machine_type', header: t('Type'), render: (value) => formatLabel(value) },
                                    { key: 'period_start', header: t('Period'), render: (_value, row) => `${formatDate(row.period_start)} - ${formatDate(row.period_end)}` },
                                    { key: 'total_cost', header: t('Total Cost') },
                                ]}
                            />
                        </TextileDataTableSection>
                    </TabsContent>

                    <TabsContent value="power-cost" className="space-y-4">
                        <TextileFormCard title={t('Record Power Cost')} icon={Zap}>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    powerCostForm.post(route('textile.finance.power-costs.store'), {
                                        onSuccess: () => powerCostForm.reset(),
                                    });
                                }}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                <Field label={t('Period Start')} type="date" value={powerCostForm.data.period_start} onChange={(value) => powerCostForm.setData('period_start', value)} />
                                <Field label={t('Period End')} type="date" value={powerCostForm.data.period_end} onChange={(value) => powerCostForm.setData('period_end', value)} />
                                <Field label={t('Meter Reading Start')} type="number" min="0" step="0.01" value={powerCostForm.data.meter_reading_start} onChange={(value) => powerCostForm.setData('meter_reading_start', value)} required />
                                <Field label={t('Meter Reading End')} type="number" min="0" step="0.01" value={powerCostForm.data.meter_reading_end} onChange={(value) => powerCostForm.setData('meter_reading_end', value)} required />
                                <Field label={t('Rate Per Unit')} type="number" min="0" step="0.01" value={powerCostForm.data.rate_per_unit} onChange={(value) => powerCostForm.setData('rate_per_unit', value)} required />
                                <Field label={t('Allocation Notes')} value={powerCostForm.data.allocation_notes} onChange={(value) => powerCostForm.setData('allocation_notes', value)} />
                                <Button type="submit" disabled={powerCostForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Save Power Cost')}</Button>
                            </form>
                        </TextileFormCard>

                        <TextileDataTableSection title={t('Power Cost Records')}>
                            <TextileDataTableCard
                                data={powerCosts}
                                emptyState={<NoRecordsFound icon={Zap} title={t('No power costs found')} description={t('Record meter readings to track power consumption and cost.')} />}
                                columns={[
                                    { key: 'period_start', header: t('Period'), render: (_value, row) => `${formatDate(row.period_start)} - ${formatDate(row.period_end)}` },
                                    { key: 'units_consumed', header: t('Units') },
                                    { key: 'rate_per_unit', header: t('Rate') },
                                    { key: 'total_cost', header: t('Total Cost') },
                                ]}
                            />
                        </TextileDataTableSection>
                    </TabsContent>

                    <TabsContent value="chemical-cost" className="space-y-4">
                        <TextileFormCard title={t('Record Chemical Cost')} icon={Beaker}>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    chemicalCostForm.post(route('textile.finance.chemical-costs.store'), {
                                        onSuccess: () => chemicalCostForm.reset(),
                                    });
                                }}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                <Field label={t('Chemical Date')} type="date" value={chemicalCostForm.data.chemical_date} onChange={(value) => chemicalCostForm.setData('chemical_date', value)} />
                                <Field label={t('Chemical Name')} value={chemicalCostForm.data.chemical_name} onChange={(value) => chemicalCostForm.setData('chemical_name', value)} required />
                                <SelectField label={t('Process Stage')} value={chemicalCostForm.data.process_stage} onChange={(value) => chemicalCostForm.setData('process_stage', value)} options={resolvedProcessStageOptions} includeEmpty emptyLabel={t('Select process stage')} />
                                <Field label={t('Batch Reference')} value={chemicalCostForm.data.batch_reference} onChange={(value) => chemicalCostForm.setData('batch_reference', value)} />
                                <Field label={t('Quantity')} type="number" min="0" step="0.01" value={chemicalCostForm.data.quantity} onChange={(value) => chemicalCostForm.setData('quantity', value)} />
                                <Field label={t('Unit')} value={chemicalCostForm.data.unit} onChange={(value) => chemicalCostForm.setData('unit', value)} />
                                <Field label={t('Unit Cost')} type="number" min="0" step="0.01" value={chemicalCostForm.data.unit_cost} onChange={(value) => chemicalCostForm.setData('unit_cost', value)} required />
                                <Field label={t('Notes')} value={chemicalCostForm.data.notes} onChange={(value) => chemicalCostForm.setData('notes', value)} />
                                <Button type="submit" disabled={chemicalCostForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Save Chemical Cost')}</Button>
                            </form>
                        </TextileFormCard>

                        <TextileDataTableSection title={t('Chemical Cost Records')}>
                            <TextileDataTableCard
                                data={chemicalCosts}
                                emptyState={<NoRecordsFound icon={Beaker} title={t('No chemical costs found')} description={t('Record chemical consumption per process stage.')} />}
                                columns={[
                                    { key: 'chemical_date', header: t('Date'), render: (value) => formatDate(value) },
                                    { key: 'chemical_name', header: t('Chemical') },
                                    { key: 'process_stage', header: t('Stage'), render: (value) => formatLabel(value) },
                                    { key: 'quantity', header: t('Qty') },
                                    { key: 'total_cost', header: t('Total Cost') },
                                ]}
                            />
                        </TextileDataTableSection>
                    </TabsContent>

                    <TabsContent value="labour-cost" className="space-y-4">
                        <TextileFormCard title={t('Record Labour Cost')} icon={Users}>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    labourCostForm.post(route('textile.finance.labour-costs.store'), {
                                        onSuccess: () => labourCostForm.reset(),
                                    });
                                }}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                <Field label={t('Labour Date')} type="date" value={labourCostForm.data.labour_date} onChange={(value) => labourCostForm.setData('labour_date', value)} />
                                <SelectField label={t('Cost Center')} value={labourCostForm.data.cost_center_id} onChange={(value) => labourCostForm.setData('cost_center_id', value)} options={resolvedCostCenterOptions} includeEmpty emptyLabel={t('Select cost center')} helperText={t('Cost centers registered in Master Setup.')} disabled={resolvedCostCenterOptions.length === 0} disabledReason={t('No cost center found. Create one under Master Setup > Core Setup.')} />
                                <SelectField label={t('Shift')} value={labourCostForm.data.shift_name} onChange={(value) => labourCostForm.setData('shift_name', value)} options={resolvedShiftOptions} includeEmpty emptyLabel={t('Select shift')} />
                                <Field label={t('Worker Count')} type="number" min="1" step="1" value={labourCostForm.data.worker_count} onChange={(value) => labourCostForm.setData('worker_count', value)} required />
                                <Field label={t('Hours Worked')} type="number" min="0" step="0.5" value={labourCostForm.data.hours_worked} onChange={(value) => labourCostForm.setData('hours_worked', value)} required />
                                <Field label={t('Rate Per Hour')} type="number" min="0" step="0.01" value={labourCostForm.data.rate_per_hour} onChange={(value) => labourCostForm.setData('rate_per_hour', value)} required />
                                <Field label={t('Notes')} value={labourCostForm.data.notes} onChange={(value) => labourCostForm.setData('notes', value)} />
                                <Button type="submit" disabled={labourCostForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Save Labour Cost')}</Button>
                            </form>
                        </TextileFormCard>

                        <TextileDataTableSection title={t('Labour Cost Records')}>
                            <TextileDataTableCard
                                data={labourCosts}
                                emptyState={<NoRecordsFound icon={Users} title={t('No labour costs found')} description={t('Record shift hours and rates per cost center.')} />}
                                columns={[
                                    { key: 'labour_date', header: t('Date'), render: (value) => formatDate(value) },
                                    { key: 'cost_center_name', header: t('Cost Center'), render: (value) => formatLabel(value) },
                                    { key: 'shift_name', header: t('Shift'), render: (value) => formatLabel(value) },
                                    { key: 'worker_count', header: t('Workers') },
                                    { key: 'hours_worked', header: t('Hours') },
                                    { key: 'total_cost', header: t('Total Cost') },
                                ]}
                            />
                        </TextileDataTableSection>
                    </TabsContent>

                    <TabsContent value="profitability" className="space-y-4">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Profitability Summary')} icon={TrendingUp}>
                                <dl className="space-y-3 text-sm">
                                    <div className="flex justify-between"><dt>{t('Revenue')}</dt><dd className="font-medium">{Number(profitability.total_revenue).toFixed(2)}</dd></div>
                                    <div className="flex justify-between"><dt>{t('Product Cost')}</dt><dd className="font-medium">{Number(profitability.product_cost).toFixed(2)}</dd></div>
                                    <div className="flex justify-between"><dt>{t('Operating Costs')}</dt><dd className="font-medium">{Number(profitability.operating_costs).toFixed(2)}</dd></div>
                                    <div className="flex justify-between border-t pt-2"><dt>{t('Net Margin')}</dt><dd className="font-semibold">{Number(profitability.margin_value).toFixed(2)} ({Number(profitability.margin_percent).toFixed(2)}%)</dd></div>
                                </dl>
                            </TextileFormCard>
                            <TextileFormCard title={t('Operating Cost Breakdown')} icon={IndianRupee}>
                                <dl className="space-y-3 text-sm">
                                    <div className="flex justify-between"><dt>{t('Maintenance')}</dt><dd className="font-medium">{Number(profitability.breakdown.maintenance).toFixed(2)}</dd></div>
                                    <div className="flex justify-between"><dt>{t('Machine')}</dt><dd className="font-medium">{Number(profitability.breakdown.machine).toFixed(2)}</dd></div>
                                    <div className="flex justify-between"><dt>{t('Power')}</dt><dd className="font-medium">{Number(profitability.breakdown.power).toFixed(2)}</dd></div>
                                    <div className="flex justify-between"><dt>{t('Chemical')}</dt><dd className="font-medium">{Number(profitability.breakdown.chemical).toFixed(2)}</dd></div>
                                    <div className="flex justify-between"><dt>{t('Labour')}</dt><dd className="font-medium">{Number(profitability.breakdown.labour).toFixed(2)}</dd></div>
                                </dl>
                            </TextileFormCard>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
