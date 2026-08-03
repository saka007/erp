import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Wrench, AlertTriangle, CalendarClock, Package, IndianRupee, History, Plus } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { formatTextileOptionLabel } from '@/components/textile/textile-form-options';
import { PageProps } from '@/types';

interface PmSchedule {
    id: number;
    pm_code?: string | null;
    scheduled_date: string;
    next_due_date?: string | null;
    machine_name?: string | null;
    machine_type?: string | null;
    maintenance_type?: string | null;
    frequency_type?: string | null;
    frequency_value: string;
    task_description?: string | null;
    last_completed_date?: string | null;
    status?: string | null;
    notes?: string | null;
}

interface Breakdown {
    id: number;
    breakdown_code?: string | null;
    breakdown_date: string;
    machine_name?: string | null;
    machine_type?: string | null;
    fault_description?: string | null;
    symptom?: string | null;
    downtime_minutes: number;
    impact?: string | null;
    status?: string | null;
    resolved_date?: string | null;
    notes?: string | null;
}

interface ServiceSchedule {
    id: number;
    schedule_code?: string | null;
    scheduled_date: string;
    machine_name?: string | null;
    machine_type?: string | null;
    technician_name?: string | null;
    status?: string | null;
    completion_notes?: string | null;
    notes?: string | null;
}

interface SparePartUsage {
    id: number;
    usage_code?: string | null;
    usage_date: string;
    maintenance_ref_type?: string | null;
    machine_name?: string | null;
    part_name?: string | null;
    part_code?: string | null;
    quantity: string;
    unit_cost: string;
    total_cost: string;
    notes?: string | null;
}

interface MaintenanceCost {
    id: number;
    cost_code?: string | null;
    cost_date: string;
    machine_name?: string | null;
    machine_type?: string | null;
    labor_cost: string;
    parts_cost: string;
    external_cost: string;
    total_cost: string;
    notes?: string | null;
}

interface MachineHistoryEvent {
    event_date?: string | null;
    machine_name?: string | null;
    event_type: string;
    summary: string;
}

interface EntityOption {
    id: number;
    label: string;
}

const MAINTENANCE_SECTIONS = ['pm', 'breakdown', 'service', 'spare-parts', 'cost', 'history'] as const;
type MaintenanceSection = typeof MAINTENANCE_SECTIONS[number];

function formatLabel(value?: string | null) {
    return value ? formatTextileOptionLabel(value) : '-';
}

function formatDate(value?: string | null) {
    return value ? value.slice(0, 10) : '-';
}

export default function Index({
    pmSchedules,
    breakdowns,
    serviceSchedules,
    sparePartUsages,
    maintenanceCosts,
    machineOptions,
    machineTypeOptions,
    maintenanceTypeOptions,
    breakdownReasonOptions,
    frequencyTypeOptions,
    pmOptions,
    breakdownOptions,
    serviceScheduleOptions,
    machineHistory,
}: {
    pmSchedules: PmSchedule[];
    breakdowns: Breakdown[];
    serviceSchedules: ServiceSchedule[];
    sparePartUsages: SparePartUsage[];
    maintenanceCosts: MaintenanceCost[];
    machineOptions: EntityOption[];
    machineTypeOptions: string[];
    maintenanceTypeOptions: string[];
    breakdownReasonOptions: string[];
    frequencyTypeOptions: string[];
    pmOptions: EntityOption[];
    breakdownOptions: EntityOption[];
    serviceScheduleOptions: EntityOption[];
    machineHistory: MachineHistoryEvent[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('maintenance_'));
    const canMaintenance = !hasFineGrainedCapabilities || textileCapabilities.maintenance_operations;

    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const visibleSections: MaintenanceSection[] = canMaintenance ? [...MAINTENANCE_SECTIONS] : [];
    const activeSection = sectionParam && visibleSections.includes(sectionParam as MaintenanceSection)
        ? sectionParam as MaintenanceSection
        : (visibleSections[0] ?? 'pm');

    const resolvedMachineOptions = machineOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedMachineTypeOptions = machineTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedMaintenanceTypeOptions = maintenanceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedBreakdownReasonOptions = breakdownReasonOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedFrequencyTypeOptions = frequencyTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedPmOptions = pmOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedBreakdownOptions = breakdownOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedServiceScheduleOptions = serviceScheduleOptions.map((value) => ({ value: String(value.id), label: value.label }));

    const pmForm = useForm({
        pm_code: '',
        scheduled_date: '',
        next_due_date: '',
        machine_id: '',
        machine_type: '',
        maintenance_type: resolvedMaintenanceTypeOptions[0]?.value ?? 'general_service',
        frequency_type: resolvedFrequencyTypeOptions[0]?.value ?? 'days',
        frequency_value: '',
        task_description: '',
        last_completed_date: '',
        status: 'planned',
        notes: '',
    });

    const breakdownForm = useForm({
        breakdown_code: '',
        breakdown_date: '',
        machine_id: '',
        machine_type: '',
        fault_description: '',
        symptom: '',
        downtime_minutes: '',
        impact: '',
        status: 'reported',
        resolved_date: '',
        notes: '',
    });

    const serviceForm = useForm({
        schedule_code: '',
        scheduled_date: '',
        pm_schedule_id: '',
        machine_id: '',
        machine_type: '',
        technician_name: '',
        status: 'scheduled',
        completion_notes: '',
        notes: '',
    });

    const sparePartForm = useForm({
        usage_code: '',
        usage_date: '',
        maintenance_ref_type: '',
        maintenance_ref_id: '',
        machine_name: '',
        part_name: '',
        part_code: '',
        quantity: '',
        unit_cost: '',
        notes: '',
    });

    const costForm = useForm({
        cost_code: '',
        cost_date: '',
        machine_id: '',
        machine_type: '',
        labor_cost: '',
        parts_cost: '',
        external_cost: '',
        notes: '',
    });

    const [historyMachine, setHistoryMachine] = useState('all');

    const overduePms = pmSchedules.filter((row) => {
        if (!row.next_due_date || row.status === 'completed') {
            return false;
        }
        return row.next_due_date.slice(0, 10) <= new Date().toISOString().slice(0, 10);
    }).length;

    const openBreakdowns = breakdowns.filter((row) => row.status !== 'resolved').length;
    const totalDowntimeMinutes = breakdowns.reduce((sum, row) => sum + Number(row.downtime_minutes || 0), 0);
    const totalMaintenanceCost = maintenanceCosts.reduce((sum, row) => sum + Number(row.total_cost || 0), 0);

    const filteredHistory = historyMachine === 'all'
        ? machineHistory
        : machineHistory.filter((event) => String(event.machine_name) === historyMachine);

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Maintenance') }]}
            pageTitle={t('Textile Maintenance')}
        >
            <Head title={t('Textile Maintenance')} />

            <div className="space-y-6">
                <TextileKpiOverview
                    title={t('Maintenance Overview')}
                    className="mb-6"
                    items={[
                        { label: t('PM Schedules'), value: String(pmSchedules.length), hint: t('Preventive maintenance schedules') },
                        { label: t('Overdue PMs'), value: String(overduePms), hint: t('Schedules with next due date reached') },
                        { label: t('Open Breakdowns'), value: String(openBreakdowns), hint: t('Breakdowns not yet resolved') },
                        { label: t('Total Downtime'), value: `${totalDowntimeMinutes} min`, hint: t('Downtime across breakdowns') },
                        { label: t('Maintenance Cost'), value: totalMaintenanceCost.toFixed(2), hint: t('Total maintenance spend') },
                    ]}
                />

                {canMaintenance && (
                    <Tabs value={activeSection} onValueChange={(value) => {
                        const search = new URLSearchParams(window.location.search);
                        search.set('section', value);
                        router.get(route('textile.maintenance.index'), Object.fromEntries(search), { preserveState: true, replace: true });
                    }}>
                        <TabsList>
                            <TabsTrigger value="pm">{t('Preventive Maintenance')}</TabsTrigger>
                            <TabsTrigger value="breakdown">{t('Breakdowns')}</TabsTrigger>
                            <TabsTrigger value="service">{t('Service Schedule')}</TabsTrigger>
                            <TabsTrigger value="spare-parts">{t('Spare Parts')}</TabsTrigger>
                            <TabsTrigger value="cost">{t('Maintenance Cost')}</TabsTrigger>
                            <TabsTrigger value="history">{t('Machine History')}</TabsTrigger>
                        </TabsList>

                        <TabsContent value="pm" className="space-y-4">
                            <TextileFormCard title={t('Schedule Preventive Maintenance')} icon={Wrench}>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        pmForm.post(route('textile.maintenance.pm-schedules.store'), {
                                            onSuccess: () => pmForm.reset(),
                                        });
                                    }}
                                    className="grid gap-4 md:grid-cols-2"
                                >
                                    <Field label={t('PM Code')} value={pmForm.data.pm_code} onChange={(value) => pmForm.setData('pm_code', value)} />
                                    <Field label={t('Scheduled Date')} type="date" value={pmForm.data.scheduled_date} onChange={(value) => pmForm.setData('scheduled_date', value)} required />
                                    <SelectField label={t('Machine')} value={pmForm.data.machine_id} onChange={(value) => pmForm.setData('machine_id', value)} options={resolvedMachineOptions} includeEmpty emptyLabel={t('Select machine')} helperText={t('Registered loom masters are listed.')} disabled={resolvedMachineOptions.length === 0} disabledReason={t('No active machine found. Register loom master first.')} required />
                                    <SelectField label={t('Machine Type')} value={pmForm.data.machine_type} onChange={(value) => pmForm.setData('machine_type', value)} options={resolvedMachineTypeOptions} includeEmpty emptyLabel={t('Select machine type')} />
                                    <SelectField label={t('Maintenance Type')} value={pmForm.data.maintenance_type} onChange={(value) => pmForm.setData('maintenance_type', value)} options={resolvedMaintenanceTypeOptions} includeEmpty emptyLabel={t('Select maintenance type')} />
                                    <SelectField label={t('Frequency Type')} value={pmForm.data.frequency_type} onChange={(value) => pmForm.setData('frequency_type', value)} options={resolvedFrequencyTypeOptions} />
                                    <Field label={t('Frequency Value')} type="number" min="0" step="0.01" value={pmForm.data.frequency_value} onChange={(value) => pmForm.setData('frequency_value', value)} />
                                    <Field label={t('Next Due Date')} type="date" value={pmForm.data.next_due_date} onChange={(value) => pmForm.setData('next_due_date', value)} />
                                    <Field label={t('Task Description')} value={pmForm.data.task_description} onChange={(value) => pmForm.setData('task_description', value)} required />
                                    <Field label={t('Last Completed Date')} type="date" value={pmForm.data.last_completed_date} onChange={(value) => pmForm.setData('last_completed_date', value)} />
                                    <Field label={t('Status')} value={pmForm.data.status} onChange={(value) => pmForm.setData('status', value)} />
                                    <div className="md:col-span-2">
                                        <Field label={t('Notes')} value={pmForm.data.notes} onChange={(value) => pmForm.setData('notes', value)} />
                                    </div>
                                    <Button type="submit" disabled={pmForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Create PM Schedule')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('PM Schedules')}
                                data={pmSchedules}
                                columns={[
                                    { key: 'pm_code', header: t('Code'), render: (value: string) => value || '-' },
                                    { key: 'scheduled_date', header: t('Scheduled'), render: (value: string) => formatDate(value) },
                                    { key: 'next_due_date', header: t('Next Due'), render: (value: string) => formatDate(value) },
                                    { key: 'machine_name', header: t('Machine'), render: (value: string) => value || '-' },
                                    { key: 'maintenance_type', header: t('Type'), render: (value: string) => formatLabel(value) },
                                    { key: 'frequency_type', header: t('Frequency'), render: (_value: string, row: PmSchedule) => `${row.frequency_type} / ${row.frequency_value}` },
                                    { key: 'task_description', header: t('Task') },
                                    { key: 'status', header: t('Status'), render: (value: string) => formatLabel(value) },
                                ]}
                                emptyState={<NoRecordsFound icon={Wrench} title={t('No PM schedules found')} description={t('Schedule preventive maintenance for your machines.')} />}
                            />
                        </TabsContent>

                        <TabsContent value="breakdown" className="space-y-4">
                            <TextileFormCard title={t('Log Breakdown')} icon={AlertTriangle}>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        breakdownForm.post(route('textile.maintenance.breakdowns.store'), {
                                            onSuccess: () => breakdownForm.reset(),
                                        });
                                    }}
                                    className="grid gap-4 md:grid-cols-2"
                                >
                                    <Field label={t('Breakdown Code')} value={breakdownForm.data.breakdown_code} onChange={(value) => breakdownForm.setData('breakdown_code', value)} />
                                    <Field label={t('Breakdown Date')} type="date" value={breakdownForm.data.breakdown_date} onChange={(value) => breakdownForm.setData('breakdown_date', value)} required />
                                    <SelectField label={t('Machine')} value={breakdownForm.data.machine_id} onChange={(value) => breakdownForm.setData('machine_id', value)} options={resolvedMachineOptions} includeEmpty emptyLabel={t('Select machine')} disabled={resolvedMachineOptions.length === 0} disabledReason={t('No active machine found. Register loom master first.')} required />
                                    <SelectField label={t('Machine Type')} value={breakdownForm.data.machine_type} onChange={(value) => breakdownForm.setData('machine_type', value)} options={resolvedMachineTypeOptions} includeEmpty emptyLabel={t('Select machine type')} />
                                    <Field label={t('Fault Description')} value={breakdownForm.data.fault_description} onChange={(value) => breakdownForm.setData('fault_description', value)} required />
                                    <SelectField label={t('Symptom')} value={breakdownForm.data.symptom} onChange={(value) => breakdownForm.setData('symptom', value)} options={resolvedBreakdownReasonOptions} includeEmpty emptyLabel={t('Select symptom')} />
                                    <Field label={t('Downtime (minutes)')} type="number" min="0" step="1" value={breakdownForm.data.downtime_minutes} onChange={(value) => breakdownForm.setData('downtime_minutes', value)} />
                                    <Field label={t('Impact')} value={breakdownForm.data.impact} onChange={(value) => breakdownForm.setData('impact', value)} />
                                    <Field label={t('Status')} value={breakdownForm.data.status} onChange={(value) => breakdownForm.setData('status', value)} />
                                    <Field label={t('Resolved Date')} type="date" value={breakdownForm.data.resolved_date} onChange={(value) => breakdownForm.setData('resolved_date', value)} />
                                    <div className="md:col-span-2">
                                        <Field label={t('Notes')} value={breakdownForm.data.notes} onChange={(value) => breakdownForm.setData('notes', value)} />
                                    </div>
                                    <Button type="submit" disabled={breakdownForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Log Breakdown')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Breakdowns')}
                                data={breakdowns}
                                columns={[
                                    { key: 'breakdown_code', header: t('Code'), render: (value: string) => value || '-' },
                                    { key: 'breakdown_date', header: t('Date'), render: (value: string) => formatDate(value) },
                                    { key: 'machine_name', header: t('Machine'), render: (value: string) => value || '-' },
                                    { key: 'fault_description', header: t('Fault') },
                                    { key: 'symptom', header: t('Symptom'), render: (value: string) => formatLabel(value) },
                                    { key: 'downtime_minutes', header: t('Downtime (min)'), render: (value: number) => String(value ?? 0) },
                                    { key: 'impact', header: t('Impact'), render: (value: string) => value || '-' },
                                    { key: 'status', header: t('Status'), render: (value: string) => formatLabel(value) },
                                ]}
                                emptyState={<NoRecordsFound icon={AlertTriangle} title={t('No breakdowns found')} description={t('Log machine breakdowns to track downtime and repairs.')} />}
                            />
                        </TabsContent>

                        <TabsContent value="service" className="space-y-4">
                            <TextileFormCard title={t('Schedule Service')} icon={CalendarClock}>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        serviceForm.post(route('textile.maintenance.service-schedules.store'), {
                                            onSuccess: () => serviceForm.reset(),
                                        });
                                    }}
                                    className="grid gap-4 md:grid-cols-2"
                                >
                                    <Field label={t('Schedule Code')} value={serviceForm.data.schedule_code} onChange={(value) => serviceForm.setData('schedule_code', value)} />
                                    <Field label={t('Scheduled Date')} type="date" value={serviceForm.data.scheduled_date} onChange={(value) => serviceForm.setData('scheduled_date', value)} required />
                                    <SelectField label={t('Linked PM Schedule')} value={serviceForm.data.pm_schedule_id} onChange={(value) => serviceForm.setData('pm_schedule_id', value)} options={resolvedPmOptions} includeEmpty emptyLabel={t('None')} helperText={t('Optional link to a preventive maintenance schedule.')} />
                                    <SelectField label={t('Machine')} value={serviceForm.data.machine_id} onChange={(value) => serviceForm.setData('machine_id', value)} options={resolvedMachineOptions} includeEmpty emptyLabel={t('Select machine')} disabled={resolvedMachineOptions.length === 0} disabledReason={t('No active machine found. Register loom master first.')} required />
                                    <SelectField label={t('Machine Type')} value={serviceForm.data.machine_type} onChange={(value) => serviceForm.setData('machine_type', value)} options={resolvedMachineTypeOptions} includeEmpty emptyLabel={t('Select machine type')} />
                                    <Field label={t('Technician')} value={serviceForm.data.technician_name} onChange={(value) => serviceForm.setData('technician_name', value)} required />
                                    <Field label={t('Status')} value={serviceForm.data.status} onChange={(value) => serviceForm.setData('status', value)} />
                                    <div className="md:col-span-2">
                                        <Field label={t('Completion Notes')} value={serviceForm.data.completion_notes} onChange={(value) => serviceForm.setData('completion_notes', value)} />
                                    </div>
                                    <div className="md:col-span-2">
                                        <Field label={t('Notes')} value={serviceForm.data.notes} onChange={(value) => serviceForm.setData('notes', value)} />
                                    </div>
                                    <Button type="submit" disabled={serviceForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Create Service Schedule')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Service Schedules')}
                                data={serviceSchedules}
                                columns={[
                                    { key: 'schedule_code', header: t('Code'), render: (value: string) => value || '-' },
                                    { key: 'scheduled_date', header: t('Scheduled'), render: (value: string) => formatDate(value) },
                                    { key: 'machine_name', header: t('Machine'), render: (value: string) => value || '-' },
                                    { key: 'technician_name', header: t('Technician'), render: (value: string) => value || '-' },
                                    { key: 'status', header: t('Status'), render: (value: string) => formatLabel(value) },
                                    { key: 'completion_notes', header: t('Completion Notes'), render: (value: string) => value || '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={CalendarClock} title={t('No service schedules found')} description={t('Plan service visits and technician assignments.')} />}
                            />
                        </TabsContent>

                        <TabsContent value="spare-parts" className="space-y-4">
                            <TextileFormCard title={t('Record Spare Part Usage')} icon={Package}>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        sparePartForm.post(route('textile.maintenance.spare-part-usages.store'), {
                                            onSuccess: () => sparePartForm.reset(),
                                        });
                                    }}
                                    className="grid gap-4 md:grid-cols-2"
                                >
                                    <Field label={t('Usage Code')} value={sparePartForm.data.usage_code} onChange={(value) => sparePartForm.setData('usage_code', value)} />
                                    <Field label={t('Usage Date')} type="date" value={sparePartForm.data.usage_date} onChange={(value) => sparePartForm.setData('usage_date', value)} required />
                                    <SelectField label={t('Linked To')} value={sparePartForm.data.maintenance_ref_type} onChange={(value) => sparePartForm.setData('maintenance_ref_type', value)} options={[{ value: 'pm', label: t('PM Schedule') }, { value: 'breakdown', label: t('Breakdown') }, { value: 'service', label: t('Service Schedule') }]} includeEmpty emptyLabel={t('None')} />
                                    {sparePartForm.data.maintenance_ref_type === 'pm' && (
                                        <SelectField label={t('PM Schedule')} value={sparePartForm.data.maintenance_ref_id} onChange={(value) => sparePartForm.setData('maintenance_ref_id', value)} options={resolvedPmOptions} includeEmpty emptyLabel={t('Select PM schedule')} />
                                    )}
                                    {sparePartForm.data.maintenance_ref_type === 'breakdown' && (
                                        <SelectField label={t('Breakdown')} value={sparePartForm.data.maintenance_ref_id} onChange={(value) => sparePartForm.setData('maintenance_ref_id', value)} options={resolvedBreakdownOptions} includeEmpty emptyLabel={t('Select breakdown')} />
                                    )}
                                    {sparePartForm.data.maintenance_ref_type === 'service' && (
                                        <SelectField label={t('Service Schedule')} value={sparePartForm.data.maintenance_ref_id} onChange={(value) => sparePartForm.setData('maintenance_ref_id', value)} options={resolvedServiceScheduleOptions} includeEmpty emptyLabel={t('Select service schedule')} />
                                    )}
                                    <Field label={t('Machine Name')} value={sparePartForm.data.machine_name} onChange={(value) => sparePartForm.setData('machine_name', value)} />
                                    <Field label={t('Part Name')} value={sparePartForm.data.part_name} onChange={(value) => sparePartForm.setData('part_name', value)} required />
                                    <Field label={t('Part Code')} value={sparePartForm.data.part_code} onChange={(value) => sparePartForm.setData('part_code', value)} />
                                    <Field label={t('Quantity')} type="number" min="0" step="0.01" value={sparePartForm.data.quantity} onChange={(value) => sparePartForm.setData('quantity', value)} required />
                                    <Field label={t('Unit Cost')} type="number" min="0" step="0.01" value={sparePartForm.data.unit_cost} onChange={(value) => sparePartForm.setData('unit_cost', value)} required />
                                    <div className="md:col-span-2">
                                        <Field label={t('Notes')} value={sparePartForm.data.notes} onChange={(value) => sparePartForm.setData('notes', value)} />
                                    </div>
                                    <Button type="submit" disabled={sparePartForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Save Spare Part Usage')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Spare Part Usages')}
                                data={sparePartUsages}
                                columns={[
                                    { key: 'usage_code', header: t('Code'), render: (value: string) => value || '-' },
                                    { key: 'usage_date', header: t('Date'), render: (value: string) => formatDate(value) },
                                    { key: 'machine_name', header: t('Machine'), render: (value: string) => value || '-' },
                                    { key: 'part_name', header: t('Part'), render: (value: string) => value || '-' },
                                    { key: 'part_code', header: t('Part Code'), render: (value: string) => value || '-' },
                                    { key: 'quantity', header: t('Qty'), render: (value: string) => String(value ?? 0) },
                                    { key: 'unit_cost', header: t('Unit Cost'), render: (value: string) => Number(value || 0).toFixed(2) },
                                    { key: 'total_cost', header: t('Total'), render: (value: string) => Number(value || 0).toFixed(2) },
                                ]}
                                emptyState={<NoRecordsFound icon={Package} title={t('No spare part usages found')} description={t('Record parts consumed during maintenance and repairs.')} />}
                            />
                        </TabsContent>

                        <TabsContent value="cost" className="space-y-4">
                            <TextileFormCard title={t('Record Maintenance Cost')} icon={IndianRupee}>
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        costForm.post(route('textile.maintenance.maintenance-costs.store'), {
                                            onSuccess: () => costForm.reset(),
                                        });
                                    }}
                                    className="grid gap-4 md:grid-cols-2"
                                >
                                    <Field label={t('Cost Code')} value={costForm.data.cost_code} onChange={(value) => costForm.setData('cost_code', value)} />
                                    <Field label={t('Cost Date')} type="date" value={costForm.data.cost_date} onChange={(value) => costForm.setData('cost_date', value)} required />
                                    <SelectField label={t('Machine')} value={costForm.data.machine_id} onChange={(value) => costForm.setData('machine_id', value)} options={resolvedMachineOptions} includeEmpty emptyLabel={t('Select machine')} disabled={resolvedMachineOptions.length === 0} disabledReason={t('No active machine found. Register loom master first.')} required />
                                    <SelectField label={t('Machine Type')} value={costForm.data.machine_type} onChange={(value) => costForm.setData('machine_type', value)} options={resolvedMachineTypeOptions} includeEmpty emptyLabel={t('Select machine type')} />
                                    <Field label={t('Labor Cost')} type="number" min="0" step="0.01" value={costForm.data.labor_cost} onChange={(value) => costForm.setData('labor_cost', value)} required />
                                    <Field label={t('Parts Cost')} type="number" min="0" step="0.01" value={costForm.data.parts_cost} onChange={(value) => costForm.setData('parts_cost', value)} />
                                    <Field label={t('External Cost')} type="number" min="0" step="0.01" value={costForm.data.external_cost} onChange={(value) => costForm.setData('external_cost', value)} />
                                    <div className="md:col-span-2">
                                        <Field label={t('Notes')} value={costForm.data.notes} onChange={(value) => costForm.setData('notes', value)} />
                                    </div>
                                    <Button type="submit" disabled={costForm.processing} className="w-full md:col-span-2"><Plus className="mr-2 h-4 w-4" />{t('Save Maintenance Cost')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Maintenance Costs')}
                                data={maintenanceCosts}
                                columns={[
                                    { key: 'cost_code', header: t('Code'), render: (value: string) => value || '-' },
                                    { key: 'cost_date', header: t('Date'), render: (value: string) => formatDate(value) },
                                    { key: 'machine_name', header: t('Machine'), render: (value: string) => value || '-' },
                                    { key: 'machine_type', header: t('Machine Type'), render: (value: string) => formatLabel(value) },
                                    { key: 'labor_cost', header: t('Labor'), render: (value: string) => Number(value || 0).toFixed(2) },
                                    { key: 'parts_cost', header: t('Parts'), render: (value: string) => Number(value || 0).toFixed(2) },
                                    { key: 'external_cost', header: t('External'), render: (value: string) => Number(value || 0).toFixed(2) },
                                    { key: 'total_cost', header: t('Total'), render: (value: string) => Number(value || 0).toFixed(2) },
                                ]}
                                emptyState={<NoRecordsFound icon={IndianRupee} title={t('No maintenance costs found')} description={t('Track labor, parts and external service costs per machine.')} />}
                            />
                        </TabsContent>

                        <TabsContent value="history" className="space-y-4">
                            <div className="flex items-center gap-4">
                                <SelectField
                                    label={t('Machine')}
                                    value={historyMachine}
                                    onChange={setHistoryMachine}
                                    options={[{ value: 'all', label: t('All Machines') }, ...machineOptions.map((machine) => ({ value: String(machine.label), label: machine.label }))]}
                                />
                            </div>

                            <TextileDataTableSection
                                title={t('Machine Timeline')}
                                data={filteredHistory}
                                columns={[
                                    { key: 'event_date', header: t('Date'), render: (value: string) => formatDate(value) },
                                    { key: 'machine_name', header: t('Machine'), render: (value: string) => value || '-' },
                                    { key: 'event_type', header: t('Event'), render: (value: string) => formatLabel(value) },
                                    { key: 'summary', header: t('Details'), render: (value: string) => value || '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={History} title={t('No machine history found')} description={t('PM schedules, breakdowns, service visits and costs appear here as a timeline.')} />}
                            />
                        </TabsContent>
                    </Tabs>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
