import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, LayoutDashboard, Navigation, Plus, Truck } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileSection } from '@/components/textile/textile-section';
import { TextileWorkspace, countSectionStatuses } from '@/components/textile/textile-workspace';
import { getTextileWorkspace } from '@/components/textile/textile-workspaces';
import { TextileInfoPanel, MetricSummaryCard } from '@/components/textile/textile-info-panel';
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
    trackingStatusOptions,
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
    trackingStatusOptions: string[];
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
    const dispatchWorkspace = getTextileWorkspace('dispatch')!;
    const activeMenuSection = dispatchWorkspace.sections.find((item) => item.id === sectionParam)
        ?? dispatchWorkspace.sections[0];

    const resolvedSourceTypeOptions = sourceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedSourceActionOptions = sourceActionOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedTrackingStatusOptions = trackingStatusOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedVehicleOptions = vehicleOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedDriverOptions = driverOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedRouteOptions = routeOptions.map((value) => ({ value: String(value.id), label: value.label }));
    const resolvedTransportVendorOptions = transportVendorOptions.map((value) => ({ value: String(value.id), label: value.label }));

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
    const [showTrackingOverrides, setShowTrackingOverrides] = useState(false);
    const selectedTrackingPlan = approvedPlans.find((row) => String(row.id) === trackingForm.data.dispatch_plan_id) ?? null;

    const approvePlan = (id: number) => {
        router.post(route('textile.dispatch.plans.approve'), { dispatch_plan_id: id }, { preserveScroll: true });
    };

    const finalizeTracking = (id: number) => {
        router.post(route('textile.dispatch.trackings.finalize'), { tracking_id: id }, { preserveScroll: true });
    };

    const activeTrackingCount = dispatchTrackings.filter((row) => row.metadata?.tracking_status === 'in_transit').length;
    const deliveredTrackingCount = dispatchTrackings.filter((row) => row.metadata?.tracking_status === 'delivered').length;

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Textile') },
                { label: t('Dispatch') },
                ...(activeMenuSection ? [{ label: t(activeMenuSection.label) }] : []),
            ]}
            pageTitle={t('Textile Dispatch')}
        >
            <Head title={t('Textile Dispatch')} />

            {canDispatch ? (
                <TextileWorkspace
                    workspace={dispatchWorkspace}
                    capabilities={textileCapabilities}
                    kpis={(section) => {
                        if (section.id === 'overview') {
                            return [
                                { label: t('Dispatch Plans'), value: dispatchPlans.length, hint: t('Planning records linked with dispatch sources'), icon: Truck },
                                { label: t('In Transit'), value: activeTrackingCount, hint: t('Tracking entries currently in transit'), icon: Navigation },
                                { label: t('Delivered'), value: deliveredTrackingCount, hint: t('Tracking entries marked delivered'), icon: Check },
                                { label: t('POD Records'), value: pods.length, hint: t('Proof of delivery from Sales flow'), icon: LayoutDashboard },
                            ];
                        }
                        const counts = countSectionStatuses(section.id === 'planning' ? dispatchPlans : dispatchTrackings);
                        return [
                            { label: t('Total'), value: counts.total, hint: t('Records in this section'), icon: LayoutDashboard },
                            { label: t('Draft'), value: counts.draft, hint: t('Awaiting approval'), icon: Check },
                            { label: t('Approved'), value: counts.approved, hint: t('Approved and ready for tracking'), icon: Truck },
                            { label: t('Released'), value: counts.released, hint: t('Released to downstream flow'), icon: Navigation },
                        ];
                    }}
                    aside={(section) => (
                        <>
                            <TextileInfoPanel
                                stages={[
                                    { id: 'planning', label: t('Dispatch Planning'), count: dispatchPlans.length, active: section.id === 'planning' },
                                    { id: 'tracking', label: t('Dispatch Tracking'), count: dispatchTrackings.length, active: section.id === 'tracking' },
                                    { id: 'pod', label: t('POD'), count: pods.length },
                                ]}
                                activities={[]}
                            />
                            <MetricSummaryCard
                                title={t('Dispatch Summary')}
                                rows={[
                                    { label: t('Dispatch Plans'), value: dispatchPlans.length },
                                    { label: t('Tracking Updates'), value: dispatchTrackings.length },
                                    { label: t('In Transit'), value: activeTrackingCount },
                                    { label: t('Delivered'), value: deliveredTrackingCount },
                                    { label: t('POD Records'), value: pods.length },
                                ]}
                            />
                        </>
                    )}
                >
                    {(section) => {
                        switch (section.id) {
                            case 'overview':
                                return (
                                    <TextileSection
                                        table={
                                            <TextileDataTableCard
                                                data={dispatchPlans}
                                                columns={[
                                                    ...createTextileWorkflowColumns(t),
                                                    { key: 'source_type', header: t('Source'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.source_type) },
                                                    { key: 'dispatch_mode', header: t('Mode'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.dispatch_mode) },
                                                    { key: 'truck_number', header: t('Truck'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.truck_number || '-' },
                                                    { key: 'vehicle_number', header: t('Vehicle'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.vehicle_number || '-' },
                                                    { key: 'driver_name', header: t('Driver'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.driver_name || '-' },
                                                    { key: 'route_name', header: t('Route'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.route_name || '-' },
                                                    { key: 'lr_number', header: t('LR No'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.lr_number || '-' },
                                                ]}
                                                emptyState={<NoRecordsFound icon={Truck} title={t('No dispatch plans found')} description={t('Create dispatch plans from approved challans, job-work outward, or yarn dispatch.')} />}
                                            />
                                        }
                                    />
                                );

                            case 'tracking':
                                return (
                                    <div className="grid gap-6 xl:grid-cols-2">
                                        <TextileFormCard title={t('Update Dispatch Tracking')} icon={Navigation}>
                                            <form
                                                className="space-y-3"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    trackingForm.post(route('textile.dispatch.trackings.store'), {
                                                        onSuccess: () => {
                                                            trackingForm.reset('dispatch_plan_id', 'current_location', 'vehicle_id', 'driver_id', 'route_id', 'transport_vendor_id', 'lr_number', 'eway_bill_number', 'notes');
                                                            setShowTrackingOverrides(false);
                                                        },
                                                    });
                                                }}
                                            >
                                                <SelectField label={t('Approved Dispatch Plan')} value={trackingForm.data.dispatch_plan_id} onChange={(value: string) => trackingForm.setData('dispatch_plan_id', value)} options={createTextileWorkflowSelectOptions(approvedPlans)} includeEmpty emptyLabel={t('Select approved dispatch plan')} helperText={t('Only approved plans are listed.')} disabled={approvedPlans.length === 0} disabledReason={t('No approved dispatch plan found. Approve a dispatch plan first.')} required />
                                                <SelectField label={t('Tracking Status')} value={trackingForm.data.tracking_status} onChange={(value: string) => trackingForm.setData('tracking_status', value)} options={resolvedTrackingStatusOptions} includeEmpty emptyLabel={t('Select tracking status')} required />
                                                <Field label={t('Current Location')} value={trackingForm.data.current_location} onChange={(value: string) => trackingForm.setData('current_location', value)} />
                                                {selectedTrackingPlan && (
                                                    <div className="space-y-2 rounded-lg border bg-muted/40 p-3">
                                                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Dispatch Plan Details')}</p>
                                                        <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                                                            <span className="text-muted-foreground">{t('Vehicle')}</span>
                                                            <span>{selectedTrackingPlan.metadata?.vehicle_number || '-'}</span>
                                                            <span className="text-muted-foreground">{t('Driver')}</span>
                                                            <span>{selectedTrackingPlan.metadata?.driver_name || '-'}</span>
                                                            <span className="text-muted-foreground">{t('Route')}</span>
                                                            <span>{selectedTrackingPlan.metadata?.route_name || '-'}</span>
                                                            <span className="text-muted-foreground">{t('Transport Vendor')}</span>
                                                            <span>{selectedTrackingPlan.metadata?.transport_vendor_name || '-'}</span>
                                                            <span className="text-muted-foreground">{t('LR No')}</span>
                                                            <span>{selectedTrackingPlan.metadata?.lr_number || '-'}</span>
                                                            <span className="text-muted-foreground">{t('E-Way')}</span>
                                                            <span>{selectedTrackingPlan.metadata?.eway_bill_number || '-'}</span>
                                                        </div>
                                                        <Button type="button" variant="ghost" className="h-auto p-0 text-xs text-muted-foreground hover:bg-transparent" onClick={() => setShowTrackingOverrides((value) => !value)}>
                                                            {showTrackingOverrides ? t('Hide override fields') : t('Override vehicle/driver for this update (optional)')}
                                                        </Button>
                                                    </div>
                                                )}
                                                {showTrackingOverrides && (
                                                    <div className="space-y-3 rounded-lg border border-dashed p-3">
                                                        {resolvedVehicleOptions.length > 0 && (
                                                            <SelectField label={t('Vehicle')} value={trackingForm.data.vehicle_id} onChange={(value: string) => trackingForm.setData('vehicle_id', value)} options={resolvedVehicleOptions} includeEmpty emptyLabel={t('Select vehicle')} helperText={t('Leave empty to keep the plan vehicle.')} />
                                                        )}
                                                        {resolvedDriverOptions.length > 0 && (
                                                            <SelectField label={t('Driver')} value={trackingForm.data.driver_id} onChange={(value: string) => trackingForm.setData('driver_id', value)} options={resolvedDriverOptions} includeEmpty emptyLabel={t('Select driver')} helperText={t('Leave empty to keep the plan driver.')} />
                                                        )}
                                                        {resolvedRouteOptions.length > 0 && (
                                                            <SelectField label={t('Route')} value={trackingForm.data.route_id} onChange={(value: string) => trackingForm.setData('route_id', value)} options={resolvedRouteOptions} includeEmpty emptyLabel={t('Select route')} helperText={t('Leave empty to keep the plan route.')} />
                                                        )}
                                                        <SelectField
                                                            label={t('Transport Vendor')}
                                                            value={trackingForm.data.transport_vendor_id}
                                                            onChange={(value: string) => trackingForm.setData('transport_vendor_id', value)}
                                                            options={resolvedTransportVendorOptions}
                                                            includeEmpty
                                                            emptyLabel={resolvedTransportVendorOptions.length > 0 ? t('Select transport vendor') : t('Optional — no vendor selected')}
                                                            helperText={t('Leave empty to keep the plan vendor.')}
                                                        />
                                                        <div className="grid grid-cols-2 gap-3">
                                                            <Field label={t('LR Number')} value={trackingForm.data.lr_number} onChange={(value: string) => trackingForm.setData('lr_number', value)} placeholder={t('e.g. LR-1001')} />
                                                            <Field label={t('E-Way Bill')} value={trackingForm.data.eway_bill_number} onChange={(value: string) => trackingForm.setData('eway_bill_number', value)} placeholder={t('e.g. EWB-9001')} />
                                                        </div>
                                                    </div>
                                                )}
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
                                );

                            default:
                                return (
                                    <div className="grid gap-6 xl:grid-cols-2">
                                        <TextileFormCard title={t('Create Dispatch Plan')} icon={Plus}>
                                            <form
                                                className="space-y-3"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    planningForm.post(route('textile.dispatch.plans.store'), {
                                                        onSuccess: () => planningForm.reset('source_type', 'source_id', 'driver_id', 'vehicle_id', 'route_id', 'transport_vendor_id', 'lr_number', 'eway_bill_number', 'freight_amount', 'notes'),
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
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Source type and action are recorded automatically. Just pick the dispatch source above.')}
                                                </p>
                                                {planningForm.data.source_type === 'job_work_outward' ? (
                                                    <SelectField label={t('Released Job-Work Outward')} value={planningForm.data.source_id} onChange={(value: string) => planningForm.setData('source_id', value)} options={jobWorkOutwardOptions} includeEmpty emptyLabel={t('Select released job-work outward')} helperText={t('Yarn issue to weaver / processing vendor dispatch.')} disabled={jobWorkOutwardOptions.length === 0} disabledReason={t('No released job-work outward found. Release one from Processing first.')} required />
                                                ) : planningForm.data.source_type === 'yarn_dispatch' ? (
                                                    <SelectField label={t('Approved Yarn Dispatch Plan')} value={planningForm.data.source_id} onChange={(value: string) => planningForm.setData('source_id', value)} options={yarnDispatchOptions} includeEmpty emptyLabel={t('Select approved yarn dispatch plan')} helperText={t('Yarn dispatch to sizing vendor tracking.')} disabled={yarnDispatchOptions.length === 0} disabledReason={t('No approved yarn dispatch plan found. Create one from Manufacturing first.')} required />
                                                ) : (
                                                    <SelectField label={t('Approved Challan')} value={planningForm.data.source_id} onChange={(value: string) => planningForm.setData('source_id', value)} options={challanOptions} includeEmpty emptyLabel={t('Select approved challan')} helperText={t('Delivery challan is mandatory for sales dispatch planning.')} disabled={challanOptions.length === 0} disabledReason={t('No approved challan found. Approve a challan from Sales first.')} required />
                                                )}
                                                <div className="grid grid-cols-2 gap-3">
                                                    {resolvedDriverOptions.length > 0 && (
                                                        <SelectField label={t('Driver')} value={planningForm.data.driver_id} onChange={(value: string) => planningForm.setData('driver_id', value)} options={resolvedDriverOptions} includeEmpty emptyLabel={t('Select driver')} helperText={t('Managed from Master Setup > Dispatch Setup > Drivers.')} />
                                                    )}
                                                    {resolvedVehicleOptions.length > 0 && (
                                                        <SelectField label={t('Vehicle')} value={planningForm.data.vehicle_id} onChange={(value: string) => planningForm.setData('vehicle_id', value)} options={resolvedVehicleOptions} includeEmpty emptyLabel={t('Select vehicle')} helperText={t('Truck/container mode and vehicle number are recorded automatically from the selected vehicle. Managed from Master Setup > Dispatch Setup > Vehicles.')} />
                                                    )}
                                                </div>
                                                {resolvedRouteOptions.length > 0 && (
                                                    <SelectField label={t('Route')} value={planningForm.data.route_id} onChange={(value: string) => planningForm.setData('route_id', value)} options={resolvedRouteOptions} includeEmpty emptyLabel={t('Select route')} helperText={t('Managed from Master Setup > Dispatch Setup > Routes.')} />
                                                )}
                                                <SelectField
                                                    label={t('Transport Vendor')}
                                                    value={planningForm.data.transport_vendor_id}
                                                    onChange={(value: string) => planningForm.setData('transport_vendor_id', value)}
                                                    options={resolvedTransportVendorOptions}
                                                    includeEmpty
                                                    emptyLabel={resolvedTransportVendorOptions.length > 0 ? t('Select transport vendor') : t('Optional — no vendor selected')}
                                                    helperText={resolvedTransportVendorOptions.length > 0
                                                        ? t('Optional. Leave empty for own-vehicle dispatch.')
                                                        : t('Optional. Leave empty for own-vehicle dispatch. To dispatch via a vendor, create one under Account > Vendors (supplier type Transport Vendor).')}
                                                />
                                                <div className="grid grid-cols-2 gap-3">
                                                    <Field label={t('LR Number')} value={planningForm.data.lr_number} onChange={(value: string) => planningForm.setData('lr_number', value)} placeholder={t('e.g. LR-1001')} />
                                                    <Field label={t('E-Way Bill')} value={planningForm.data.eway_bill_number} onChange={(value: string) => planningForm.setData('eway_bill_number', value)} placeholder={t('e.g. EWB-9001')} />
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
                                            emptyState={<NoRecordsFound icon={Truck} title={t('No dispatch plans found')} description={t('Create dispatch plans from approved challans, job-work outward, or yarn dispatch.')} />}
                                        />
                                    </div>
                                );
                        }
                    }}
                </TextileWorkspace>
            ) : (
                <NoRecordsFound icon={Truck} title={t('Dispatch is not enabled')} description={t('Enable a dispatch source capability in Textile Operating Model (Sales Allocation/Dispatch, Job-Work Outward, or Yarn Dispatch) to use dispatch workflows.')} />
            )}
        </AuthenticatedLayout>
    );
}
