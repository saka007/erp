import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Truck, Plus, Check, Navigation } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { TextileFormErrors } from '@/components/textile/textile-form-errors';
import { formatTextileOptionLabel } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';
import { PageProps } from '@/types';

interface WorkflowDocument {
    id: number;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    metadata?: {
        source_type?: string | null;
        dispatch_mode?: string | null;
        truck_number?: string | null;
        container_number?: string | null;
        driver_name?: string | null;
        vehicle_number?: string | null;
        route_name?: string | null;
        transport_vendor_name?: string | null;
        lr_number?: string | null;
        eway_bill_number?: string | null;
        freight_amount?: number | null;
        tracking_status?: string | null;
        current_location?: string | null;
    } | null;
}

interface EntityOption {
    id: number;
    label: string;
}

const DISPATCH_SECTIONS = ['planning', 'tracking'] as const;
type DispatchSection = typeof DISPATCH_SECTIONS[number];

function metadataLabel(value?: string | null) {
    return value ? formatTextileOptionLabel(value) : '-';
}

export default function Index({
    dispatchPlans,
    dispatchTrackings,
    challans,
    jobWorkOutwards,
    yarnDispatches,
    pods,
    dispatchSourceFlags,
    sourceTypeOptions,
    sourceActionOptions,
    dispatchModeOptions,
    trackingStatusOptions,
    truckNumberOptions,
    containerNumberOptions,
    vehicleOptions,
    driverOptions,
    routeOptions,
    transportVendorOptions,
    lrNumberOptions,
    ewayBillOptions,
}: {
    dispatchPlans: WorkflowDocument[];
    dispatchTrackings: WorkflowDocument[];
    challans: WorkflowDocument[];
    jobWorkOutwards: WorkflowDocument[];
    yarnDispatches: WorkflowDocument[];
    pods: WorkflowDocument[];
    dispatchSourceFlags?: {
        challan: boolean;
        job_work_outward: boolean;
        yarn_dispatch: boolean;
    };
    sourceTypeOptions: string[];
    sourceActionOptions: string[];
    dispatchModeOptions: string[];
    trackingStatusOptions: string[];
    truckNumberOptions: string[];
    containerNumberOptions: string[];
    vehicleOptions: EntityOption[];
    driverOptions: EntityOption[];
    routeOptions: EntityOption[];
    transportVendorOptions: EntityOption[];
    lrNumberOptions: string[];
    ewayBillOptions: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('sales_') || key.startsWith('dispatch_source_'));
    const sourceFlags = dispatchSourceFlags ?? {
        challan: !hasFineGrainedCapabilities || textileCapabilities.sales_allocation_dispatch !== false,
        job_work_outward: !hasFineGrainedCapabilities || textileCapabilities.dispatch_source_job_work !== false,
        yarn_dispatch: !hasFineGrainedCapabilities || textileCapabilities.dispatch_source_yarn !== false,
    };
    const canDispatch = sourceFlags.challan || sourceFlags.job_work_outward || sourceFlags.yarn_dispatch;

    const dispatchSourceToggleOptions = [
        ...(sourceFlags.challan ? [{ value: 'challan', label: t('From Challan') }] : []),
        ...(sourceFlags.job_work_outward ? [{ value: 'job_work_outward', label: t('From Job-Work Outward') }] : []),
        ...(sourceFlags.yarn_dispatch ? [{ value: 'yarn_dispatch', label: t('From Yarn Dispatch') }] : []),
    ];

    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const sourceTypeParam = new URLSearchParams(window.location.search).get('source_type');
    const sourceIdParam = new URLSearchParams(window.location.search).get('source_id');
    const visibleSections: DispatchSection[] = canDispatch ? [...DISPATCH_SECTIONS] : [];
    const activeSection = sectionParam && visibleSections.includes(sectionParam as DispatchSection)
        ? sectionParam as DispatchSection
        : (visibleSections[0] ?? 'planning');

    const resolvedSourceTypeOptions = sourceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedSourceActionOptions = sourceActionOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedDispatchModeOptions = dispatchModeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedTrackingStatusOptions = trackingStatusOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedTruckNumberOptions = truckNumberOptions.map((value) => ({ value, label: value }));
    const resolvedContainerNumberOptions = containerNumberOptions.map((value) => ({ value, label: value }));
    const resolvedVehicleOptions = vehicleOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedDriverOptions = driverOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedRouteOptions = routeOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedTransportVendorOptions = transportVendorOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedLrNumberOptions = lrNumberOptions.map((value) => ({ value, label: value }));
    const resolvedEwayBillOptions = ewayBillOptions.map((value) => ({ value, label: value }));

    const challanOptions = createTextileWorkflowSelectOptions(challans);
    const jobWorkOutwardOptions = createTextileWorkflowSelectOptions(jobWorkOutwards);
    const yarnDispatchOptions = createTextileWorkflowSelectOptions(yarnDispatches);
    const approvedPlans = dispatchPlans.filter((row) => ['approved', 'released', 'closed'].includes(row.status));

    const initialSourceType = dispatchSourceToggleOptions.some((option) => option.value === sourceTypeParam)
        ? (sourceTypeParam as string)
        : (dispatchSourceToggleOptions[0]?.value ?? 'challan');
    const initialSourceId = sourceTypeParam && sourceIdParam ? sourceIdParam : '';

    const planningForm = useForm({
        source_type: initialSourceType,
        source_id: initialSourceId,
        source_reference_type: resolvedSourceTypeOptions[0]?.value ?? 'dispatch_plan',
        source_action: resolvedSourceActionOptions[0]?.value ?? 'dispatch_plan',
        dispatch_mode: resolvedDispatchModeOptions[0]?.value ?? 'truck',
        truck_number: '',
        container_number: '',
        driver_id: '',
        vehicle_id: '',
        route_id: '',
        transport_vendor_id: '',
        lr_number: '',
        eway_bill_number: '',
        freight_amount: '',
        notes: '',
    });

    const trackingForm = useForm({
        dispatch_plan_id: '',
        source_action: resolvedSourceActionOptions[0]?.value ?? 'tracking_update',
        tracking_status: resolvedTrackingStatusOptions[0]?.value ?? 'planned',
        current_location: '',
        vehicle_id: '',
        driver_id: '',
        route_id: '',
        transport_vendor_id: '',
        lr_number: '',
        eway_bill_number: '',
        notes: '',
    });

    const approvePlan = (id: number) => {
        router.post(route('textile.dispatch.plans.approve'), { dispatch_plan_id: id }, { preserveScroll: true });
    };

    const finalizeTracking = (id: number) => {
        router.post(route('textile.dispatch.trackings.finalize'), { tracking_id: id }, { preserveScroll: true });
    };

    const activeTrackingCount = dispatchTrackings.filter((row) => row.metadata?.tracking_status === 'in_transit').length;
    const deliveredTrackingCount = dispatchTrackings.filter((row) => row.metadata?.tracking_status === 'delivered').length;

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Dispatch') }]} pageTitle={t('Textile Dispatch')}>
            <Head title={t('Textile Dispatch')} />

            <TextileKpiOverview
                title={t('Dispatch Overview')}
                className="mb-6"
                items={[
                    { label: t('Dispatch Plans'), value: dispatchPlans.length, hint: t('Planning records linked with dispatch sources') },
                    { label: t('In Transit'), value: activeTrackingCount, hint: t('Tracking entries currently in transit') },
                    { label: t('Delivered'), value: deliveredTrackingCount, hint: t('Tracking entries marked delivered') },
                    { label: t('POD Records'), value: pods.length, hint: t('Proof of delivery from Sales flow') },
                ]}
            />

            {canDispatch ? (
                <Tabs
                    value={activeSection}
                    onValueChange={(value: string) => router.get(route('textile.dispatch.index', { section: value }), {}, { preserveState: true, replace: true })}
                    className="space-y-6"
                >
                    <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-2">
                        <TabsTrigger value="planning">{t('Dispatch Planning')}</TabsTrigger>
                        <TabsTrigger value="tracking">{t('Dispatch Tracking')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="planning">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Create Dispatch Plan')} icon={Plus}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        planningForm.post(route('textile.dispatch.plans.store'), {
                                            onSuccess: () => planningForm.reset('source_type', 'source_id', 'truck_number', 'container_number', 'driver_id', 'vehicle_id', 'route_id', 'transport_vendor_id', 'lr_number', 'eway_bill_number', 'freight_amount', 'notes'),
                                        });
                                    }}
                                >
                                    <TextileFormErrors errors={planningForm.errors} />
                                    {dispatchSourceToggleOptions.length > 1 && (
                                        <div className="grid gap-2">
                                            <span className="text-sm font-medium">{t('Dispatch Source')}</span>
                                            <div className="grid grid-cols-3 gap-2">
                                                {dispatchSourceToggleOptions.map((option) => (
                                                    <Button
                                                        key={option.value}
                                                        type="button"
                                                        variant={planningForm.data.source_type === option.value ? 'default' : 'outline'}
                                                        className="h-8 text-xs"
                                                        onClick={() => {
                                                            planningForm.setData('source_type', option.value);
                                                            planningForm.setData('source_id', '');
                                                            const url = new URL(window.location.href);
                                                            url.searchParams.set('source_type', option.value);
                                                            url.searchParams.delete('source_id');
                                                            window.history.replaceState({}, '', url.toString());
                                                        }}
                                                    >
                                                        {t(option.label)}
                                                    </Button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    <SelectField label={t('Source Type')} value={planningForm.data.source_reference_type} onChange={(value: string) => planningForm.setData('source_reference_type', value)} options={resolvedSourceTypeOptions} includeEmpty emptyLabel={t('Select source type')} helperText={t('Source types are managed from Master Setup > Dispatch Setup > Source Types.')} required />
                                    <SelectField label={t('Source Action')} value={planningForm.data.source_action} onChange={(value: string) => planningForm.setData('source_action', value)} options={resolvedSourceActionOptions} includeEmpty emptyLabel={t('Select source action')} helperText={t('Source actions are managed from Master Setup > Dispatch Setup > Source Actions.')} required />
                                    {planningForm.data.source_type === 'job_work_outward' ? (
                                        <SelectField label={t('Released Job-Work Outward')} value={planningForm.data.source_id} onChange={(value: string) => planningForm.setData('source_id', value)} options={jobWorkOutwardOptions} includeEmpty emptyLabel={t('Select released job-work outward')} helperText={t('Yarn issue to weaver / processing vendor dispatch.')} disabled={jobWorkOutwardOptions.length === 0} disabledReason={t('No released job-work outward found. Release one from Processing first.')} required />
                                    ) : planningForm.data.source_type === 'yarn_dispatch' ? (
                                        <SelectField label={t('Approved Yarn Dispatch Plan')} value={planningForm.data.source_id} onChange={(value: string) => planningForm.setData('source_id', value)} options={yarnDispatchOptions} includeEmpty emptyLabel={t('Select approved yarn dispatch plan')} helperText={t('Yarn dispatch to sizing vendor tracking.')} disabled={yarnDispatchOptions.length === 0} disabledReason={t('No approved yarn dispatch plan found. Create one from Manufacturing first.')} required />
                                    ) : (
                                        <SelectField label={t('Released Challan')} value={planningForm.data.source_id} onChange={(value: string) => planningForm.setData('source_id', value)} options={challanOptions} includeEmpty emptyLabel={t('Select released challan')} helperText={t('Delivery challan is mandatory for sales dispatch planning.')} disabled={challanOptions.length === 0} disabledReason={t('No released challan found. Release challan from Sales first.')} required />
                                    )}
                                    <SelectField label={t('Dispatch Mode')} value={planningForm.data.dispatch_mode} onChange={(value: string) => planningForm.setData('dispatch_mode', value)} options={resolvedDispatchModeOptions} includeEmpty emptyLabel={t('Select mode')} helperText={t('Truck and container dispatch are both supported.')} required />
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Truck Number')} value={planningForm.data.truck_number} onChange={(value: string) => planningForm.setData('truck_number', value)} options={resolvedTruckNumberOptions} includeEmpty emptyLabel={t('Select truck number')} helperText={t('Managed from Master Setup > Dispatch Setup > Truck Numbers.')} disabled={resolvedTruckNumberOptions.length === 0} disabledReason={t('No truck number master found. Create dispatch truck numbers first.')} />
                                        <SelectField label={t('Container Number')} value={planningForm.data.container_number} onChange={(value: string) => planningForm.setData('container_number', value)} options={resolvedContainerNumberOptions} includeEmpty emptyLabel={t('Select container number')} helperText={t('Managed from Master Setup > Dispatch Setup > Container Numbers.')} disabled={resolvedContainerNumberOptions.length === 0} disabledReason={t('No container number master found. Create dispatch container numbers first.')} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Driver')} value={planningForm.data.driver_id} onChange={(value: string) => planningForm.setData('driver_id', value)} options={resolvedDriverOptions} includeEmpty emptyLabel={t('Select driver')} helperText={t('Managed from Master Setup > Dispatch Setup > Drivers.')} disabled={resolvedDriverOptions.length === 0} disabledReason={t('No driver record found. Create dispatch drivers first.')} />
                                        <SelectField label={t('Vehicle')} value={planningForm.data.vehicle_id} onChange={(value: string) => planningForm.setData('vehicle_id', value)} options={resolvedVehicleOptions} includeEmpty emptyLabel={t('Select vehicle')} helperText={t('Managed from Master Setup > Dispatch Setup > Vehicles.')} disabled={resolvedVehicleOptions.length === 0} disabledReason={t('No vehicle record found. Create dispatch vehicles first.')} />
                                    </div>
                                    <SelectField label={t('Route')} value={planningForm.data.route_id} onChange={(value: string) => planningForm.setData('route_id', value)} options={resolvedRouteOptions} includeEmpty emptyLabel={t('Select route')} helperText={t('Managed from Master Setup > Dispatch Setup > Routes.')} disabled={resolvedRouteOptions.length === 0} disabledReason={t('No route record found. Create dispatch routes first.')} />
                                    <SelectField label={t('Transport Vendor')} value={planningForm.data.transport_vendor_id} onChange={(value: string) => planningForm.setData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Use Account > Vendors with supplier type Transport Vendor.')} disabled={resolvedTransportVendorOptions.length === 0} disabledReason={t('No transport vendor found. Create one under Supplier Setup first.')} />
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('LR Number')} value={planningForm.data.lr_number} onChange={(value: string) => planningForm.setData('lr_number', value)} options={resolvedLrNumberOptions} includeEmpty emptyLabel={t('Select LR number')} helperText={t('Managed from Master Setup > Dispatch Setup > LR Numbers.')} disabled={resolvedLrNumberOptions.length === 0} disabledReason={t('No LR master found. Create dispatch LR numbers first.')} />
                                        <SelectField label={t('E-Way Bill')} value={planningForm.data.eway_bill_number} onChange={(value: string) => planningForm.setData('eway_bill_number', value)} options={resolvedEwayBillOptions} includeEmpty emptyLabel={t('Select E-Way bill')} helperText={t('Managed from Master Setup > Dispatch Setup > E-Way Bills.')} disabled={resolvedEwayBillOptions.length === 0} disabledReason={t('No E-Way master found. Create dispatch E-Way bills first.')} />
                                    </div>
                                    <Field label={t('Freight Amount')} type="number" value={planningForm.data.freight_amount} onChange={(value: string) => planningForm.setData('freight_amount', value)} />
                                    <Field label={t('Notes')} value={planningForm.data.notes} onChange={(value: string) => planningForm.setData('notes', value)} />
                                    <Button type="submit" disabled={planningForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Dispatch Plan')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableCard
                                data={dispatchPlans}
                                columns={[
                                    ...createTextileWorkflowColumns(t, {
                                        actions: createTextileWorkflowActions([
                                            {
                                                statuses: textileActionableStatuses.draft,
                                                actions: [{ label: t('Approve Plan'), icon: Check, onClick: (row) => approvePlan(row.id) }],
                                            },
                                        ]),
                                    }),
                                    { key: 'source_type', header: t('Source'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.source_type) },
                                    { key: 'dispatch_mode', header: t('Mode'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.dispatch_mode) },
                                    { key: 'truck_number', header: t('Truck'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.truck_number || '-' },
                                    { key: 'container_number', header: t('Container'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.container_number || '-' },
                                    { key: 'driver_name', header: t('Driver'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.driver_name || '-' },
                                    { key: 'vehicle_number', header: t('Vehicle'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.vehicle_number || '-' },
                                    { key: 'route_name', header: t('Route'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.route_name || '-' },
                                    { key: 'transport_vendor_name', header: t('Transport Vendor'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.transport_vendor_name || '-' },
                                    { key: 'lr_number', header: t('LR No'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.lr_number || '-' },
                                    { key: 'eway_bill_number', header: t('E-Way'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.eway_bill_number || '-' },
                                    { key: 'freight_amount', header: t('Freight'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.freight_amount ?? '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={Truck} title={t('No dispatch plans found')} description={t('Create dispatch plans from released challans, job-work outward, or yarn dispatch.')} />}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="tracking">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Update Dispatch Tracking')} icon={Navigation}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        trackingForm.post(route('textile.dispatch.trackings.store'), {
                                            onSuccess: () => trackingForm.reset('dispatch_plan_id', 'current_location', 'vehicle_id', 'driver_id', 'route_id', 'transport_vendor_id', 'lr_number', 'eway_bill_number', 'notes'),
                                        });
                                    }}
                                >
                                    <SelectField label={t('Approved Dispatch Plan')} value={trackingForm.data.dispatch_plan_id} onChange={(value: string) => trackingForm.setData('dispatch_plan_id', value)} options={createTextileWorkflowSelectOptions(approvedPlans)} includeEmpty emptyLabel={t('Select approved dispatch plan')} helperText={t('Only approved plans are listed.')} disabled={approvedPlans.length === 0} disabledReason={t('No approved dispatch plan found. Approve a dispatch plan first.')} required />
                                    <SelectField label={t('Source Action')} value={trackingForm.data.source_action} onChange={(value: string) => trackingForm.setData('source_action', value)} options={resolvedSourceActionOptions} includeEmpty emptyLabel={t('Select source action')} helperText={t('Source actions are managed from Master Setup > Dispatch Setup > Source Actions.')} required />
                                    <SelectField label={t('Tracking Status')} value={trackingForm.data.tracking_status} onChange={(value: string) => trackingForm.setData('tracking_status', value)} options={resolvedTrackingStatusOptions} includeEmpty emptyLabel={t('Select tracking status')} required />
                                    <Field label={t('Current Location')} value={trackingForm.data.current_location} onChange={(value: string) => trackingForm.setData('current_location', value)} />
                                    <SelectField label={t('Vehicle')} value={trackingForm.data.vehicle_id} onChange={(value: string) => trackingForm.setData('vehicle_id', value)} options={resolvedVehicleOptions} includeEmpty emptyLabel={t('Select vehicle')} helperText={t('Managed from Master Setup > Dispatch Setup > Vehicles.')} disabled={resolvedVehicleOptions.length === 0} disabledReason={t('No vehicle record found. Create dispatch vehicles first.')} />
                                    <SelectField label={t('Driver')} value={trackingForm.data.driver_id} onChange={(value: string) => trackingForm.setData('driver_id', value)} options={resolvedDriverOptions} includeEmpty emptyLabel={t('Select driver')} helperText={t('Managed from Master Setup > Dispatch Setup > Drivers.')} disabled={resolvedDriverOptions.length === 0} disabledReason={t('No driver record found. Create dispatch drivers first.')} />
                                    <SelectField label={t('Route')} value={trackingForm.data.route_id} onChange={(value: string) => trackingForm.setData('route_id', value)} options={resolvedRouteOptions} includeEmpty emptyLabel={t('Select route')} helperText={t('Managed from Master Setup > Dispatch Setup > Routes.')} disabled={resolvedRouteOptions.length === 0} disabledReason={t('No route record found. Create dispatch routes first.')} />
                                    <SelectField label={t('Transport Vendor')} value={trackingForm.data.transport_vendor_id} onChange={(value: string) => trackingForm.setData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Use Account > Vendors with supplier type Transport Vendor.')} disabled={resolvedTransportVendorOptions.length === 0} disabledReason={t('No transport vendor found. Create one under Supplier Setup first.')} />
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('LR Number')} value={trackingForm.data.lr_number} onChange={(value: string) => trackingForm.setData('lr_number', value)} options={resolvedLrNumberOptions} includeEmpty emptyLabel={t('Select LR number')} helperText={t('Managed from Master Setup > Dispatch Setup > LR Numbers.')} disabled={resolvedLrNumberOptions.length === 0} disabledReason={t('No LR master found. Create dispatch LR numbers first.')} />
                                        <SelectField label={t('E-Way Bill')} value={trackingForm.data.eway_bill_number} onChange={(value: string) => trackingForm.setData('eway_bill_number', value)} options={resolvedEwayBillOptions} includeEmpty emptyLabel={t('Select E-Way bill')} helperText={t('Managed from Master Setup > Dispatch Setup > E-Way Bills.')} disabled={resolvedEwayBillOptions.length === 0} disabledReason={t('No E-Way master found. Create dispatch E-Way bills first.')} />
                                    </div>
                                    <Field label={t('Notes')} value={trackingForm.data.notes} onChange={(value: string) => trackingForm.setData('notes', value)} />
                                    <Button type="submit" disabled={trackingForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Post Tracking Update')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Tracking Records')}
                                data={dispatchTrackings}
                                columns={[
                                    ...createTextileWorkflowColumns(t, {
                                        actions: createTextileWorkflowActions([
                                            {
                                                statuses: textileActionableStatuses.draft,
                                                actions: [{ label: t('Finalize'), icon: Check, onClick: (row) => finalizeTracking(row.id) }],
                                            },
                                        ]),
                                    }),
                                    { key: 'tracking_status', header: t('Status'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.tracking_status) },
                                    { key: 'current_location', header: t('Location'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.current_location || '-' },
                                    { key: 'vehicle_number', header: t('Vehicle'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.vehicle_number || '-' },
                                    { key: 'driver_name', header: t('Driver'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.driver_name || '-' },
                                    { key: 'route_name', header: t('Route'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.route_name || '-' },
                                    { key: 'transport_vendor_name', header: t('Transport Vendor'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.transport_vendor_name || '-' },
                                    { key: 'lr_number', header: t('LR No'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.lr_number || '-' },
                                    { key: 'eway_bill_number', header: t('E-Way'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.eway_bill_number || '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={Navigation} title={t('No dispatch tracking found')} description={t('Post tracking updates from approved plans.')} />}
                            />

                            <TextileDataTableSection
                                title={t('POD Records')}
                                data={pods}
                                columns={createTextileWorkflowColumns(t)}
                                emptyState={<NoRecordsFound icon={Check} title={t('No POD records found')} description={t('POD records are generated from challan completion in Sales flow.')} />}
                            />
                        </div>
                    </TabsContent>
                </Tabs>
            ) : (
                <NoRecordsFound icon={Truck} title={t('Dispatch is not enabled')} description={t('Enable a dispatch source capability in Textile Operating Model (Sales Allocation/Dispatch, Job-Work Outward, or Yarn Dispatch) to use dispatch workflows.')} />
            )}
        </AuthenticatedLayout>
    );
}
