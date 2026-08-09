import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, CheckCircle2, ClipboardCheck, FileEdit, ListChecks, Plus, Send, ScrollText, ShoppingBag, Trash2, Truck } from 'lucide-react';
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
import { TextileInfoPanel, MetricSummaryCard, type ActivityItem } from '@/components/textile/textile-info-panel';
import { TextileWorkflowSteps, workflowStepStatuses, type WorkflowStep } from '@/components/textile/textile-workflow-steps';
import { buildUnitOptions, formatTextileLabel } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';
import { PageProps } from '@/types';

interface WorkflowDocument {
    id: number;
    document_type?: string | null;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    source_reference_id?: number | null;
    metadata?: Record<string, unknown> | null;
}

interface CustomerOption {
    id: number;
    company_name: string;
    operating_model?: string | null;
    material_ownership?: string | null;
    billing_mode?: string | null;
    default_rate?: number | null;
}

interface QuotationRecord {
    id: number;
    quotation_number: string;
    customer_name: string;
    quotation_date: string;
    due_date: string;
    total_amount: string;
    status: string;
    converted_to_invoice: boolean;
}

export default function Index({
    salesOrders,
    allocations,
    dispatches,
    challans,
    pods,
    quotations,
    customers,
    warehouseOptions,
    sellableLotOptions,
    sourceTypeOptions,
    sourceActionOptions,
    unitOptions,
    partyOptions,
    lotReferenceOptions,
    recentActivity,
}: {
    salesOrders: WorkflowDocument[];
    allocations: WorkflowDocument[];
    dispatches: WorkflowDocument[];
    challans: WorkflowDocument[];
    pods: WorkflowDocument[];
    quotations: QuotationRecord[];
    customers: CustomerOption[];
    warehouseOptions: Array<{ value: string; label: string }>;
    sellableLotOptions: Array<{ value: string; label: string; available_quantity: string; material_type: string; unit: string }>;
    sourceTypeOptions: string[];
    sourceActionOptions: string[];
    unitOptions: string[];
    partyOptions: string[];
    lotReferenceOptions: string[];
    recentActivity: ActivityItem[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const salesWorkspace = getTextileWorkspace('sales')!;
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const activeMenuSection = salesWorkspace.sections.find((item) => item.id === sectionParam)
        ?? salesWorkspace.sections[0];
    const [openStep, setOpenStep] = useState<string | null>(null);

    const salesOrderForm = useForm({
        source_reference_type: '',
        source_reference_id: '',
        source_action: '',
        customer_id: '',
        lot_selections: [{ lot_reference: '', quantity: '' }],
        rate: '',
        required_delivery_date: '',
        warehouse: '',
        notes: '',
    });

    const customerOptions = customers.map((customer) => ({
        value: String(customer.id),
        label: `${customer.company_name} | ${customer.operating_model || '-'} | ${customer.material_ownership || '-'} | ${customer.billing_mode || '-'}${customer.default_rate != null ? ` | @ ${Number(customer.default_rate).toFixed(2)}` : ''}`,
    }));

    const allocationForm = useForm({ sales_order_id: '' });
    const dispatchForm = useForm({ allocation_id: '' });
    const challanForm = useForm({ dispatch_id: '' });
    const podForm = useForm({ challan_id: '' });
    const allocatedSalesOrderIds = new Set(allocations.map((row) => Number(row.source_reference_id)));
    const approvedSalesOrders = salesOrders.filter((row) => row.status === 'approved' && !allocatedSalesOrderIds.has(row.id));
    const releasedAllocations = allocations.filter((row) => row.status === 'released');
    const releasedDispatches = dispatches.filter((row) => row.status === 'released');
    const podChallanIds = new Set(pods.map((row) => Number(row.source_reference_id)));
    const pendingPodChallans = challans.filter((row) => !podChallanIds.has(row.id));
    const resolvedUnitOptions = buildUnitOptions(unitOptions);
    const selectedSalesOrder = approvedSalesOrders.find((row) => String(row.id) === allocationForm.data.sales_order_id);
    const selectedTakhaQuantity = salesOrderForm.data.lot_selections.reduce((sum, line) => sum + (Number(line.quantity) || 0), 0);
    const selectedTakhaUnits = Array.from(new Set(salesOrderForm.data.lot_selections.map((line) => sellableLotOptions.find((option) => option.value === line.lot_reference)?.unit).filter(Boolean)));

    const allDocuments = [...salesOrders, ...allocations, ...dispatches, ...challans, ...pods];
    const draftedCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;
    const sectionRows: Record<string, WorkflowDocument[]> = {
        'sales-order': salesOrders,
        quotations: [],
        'allocation-dispatch': [...allocations, ...dispatches],
        'challan-pod': [...challans, ...pods],
    };
    const totalOrderedQuantity = salesOrders.reduce((sum, row) => sum + Number(row.quantity || 0), 0);
    const pendingPods = Math.max(challans.length - pods.length, 0);

    const approveSalesOrder = (id: number) => {
        router.post(route('textile.sales.orders.approve'), { sales_order_id: id }, { preserveScroll: true });
    };

    const releaseAllocation = (id: number) => {
        router.post(route('textile.sales.allocations.release'), { allocation_id: id }, { preserveScroll: true });
    };

    const releaseDispatch = (id: number) => {
        router.post(route('textile.sales.dispatches.release'), { dispatch_id: id }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Textile') },
                { label: t('Sales') },
                ...(activeMenuSection ? [{ label: t(activeMenuSection.label) }] : []),
            ]}
            pageTitle={t('Textile Sales')}
            pageActions={(
                <Button
                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                    onClick={() => router.get(route('textile.sales.index', { section: 'sales-order' }), {}, { preserveState: true, replace: true })}
                >
                    <Plus className="h-4 w-4" />
                    {t('New Sales Order')}
                </Button>
            )}
        >
            <Head title={t('Textile Sales')} />

            <TextileWorkspace
                workspace={salesWorkspace}
                capabilities={textileCapabilities}
                kpis={(section) => {
                    if (section.id === 'overview') {
                        return [
                            { label: t('Total Documents'), value: allDocuments.length, hint: t('SO + Allocation + Dispatch + Challan + POD'), icon: ListChecks },
                            { label: t('Draft'), value: draftedCount, hint: t('Pending first approval'), icon: FileEdit },
                            { label: t('Approved'), value: approvedCount, hint: t('Approved but not released'), icon: CheckCircle2 },
                            { label: t('Released'), value: releasedCount, hint: t('Ready for downstream flow'), icon: Send },
                        ];
                    }

                    const counts = countSectionStatuses(sectionRows[section.id] ?? []);
                    return [
                        { label: t('Total'), value: counts.total, hint: t('Records in this section'), icon: ListChecks },
                        { label: t('Draft'), value: counts.draft, hint: t('Awaiting review'), icon: FileEdit },
                        { label: t('Approved'), value: counts.approved, hint: t('Ready for release'), icon: CheckCircle2 },
                        { label: t('Released'), value: counts.released, hint: t('Posted to downstream flow'), icon: Send },
                    ];
                }}
                aside={(section) => (
                    <>
                        <TextileInfoPanel
                            stages={[
                                { id: 'sales-order', label: t('Sales Order'), count: salesOrders.length, active: section.id === 'sales-order' },
                                { id: 'allocation', label: t('Allocation'), count: allocations.length, active: section.id === 'allocation-dispatch' },
                                { id: 'dispatch', label: t('Dispatch'), count: dispatches.length, active: section.id === 'allocation-dispatch' },
                                { id: 'challan', label: t('Challan'), count: challans.length, active: section.id === 'challan-pod' },
                                { id: 'pod', label: t('POD'), count: pods.length, active: section.id === 'challan-pod' },
                            ]}
                            activities={recentActivity}
                        />
                        <MetricSummaryCard
                            title={t('Sales Summary')}
                            rows={[
                                { label: t('Sales Orders'), value: salesOrders.length },
                                { label: t('Allocations'), value: allocations.length },
                                { label: t('Dispatches'), value: dispatches.length },
                                { label: t('Challans'), value: challans.length },
                                { label: t('PODs'), value: pods.length },
                            ]}
                        />
                        <MetricSummaryCard
                            title={t('Customer Summary')}
                            rows={[
                                { label: t('Customer Profiles'), value: customers.length },
                                { label: t('Total Order Qty'), value: totalOrderedQuantity },
                                { label: t('Pending POD'), value: pendingPods },
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
                                            data={allDocuments}
                                            columns={[
                                                { key: 'document_type', header: t('Type'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(row.document_type ?? '') },
                                                { key: 'document_number', header: t('Document') },
                                                { key: 'party_name', header: t('Party') },
                                                { key: 'lot_reference', header: t('Lot') },
                                                { key: 'quantity', header: t('Qty') },
                                                { key: 'unit', header: t('Unit') },
                                                { key: 'status', header: t('Status'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(row.status) },
                                            ]}
                                            emptyState={<NoRecordsFound icon={ShoppingBag} title={t('No sales records yet')} description={t('Create a sales order to start the sales pipeline.')} />}
                                        />
                                    }
                                />
                            );

                        case 'sales-order': {
                            const salesOrderStatuses = workflowStepStatuses([salesOrders.length]);
                            const steps: WorkflowStep[] = [
                                { id: 'create-sales-order', title: t('Create Sales Order'), icon: ShoppingBag, status: salesOrderStatuses[0], count: salesOrders.length, form: (
                                    <TextileFormCard showHeader={false}>
                                <form className="space-y-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    salesOrderForm.post(route('textile.sales.orders.store'), {
                                        onSuccess: () => salesOrderForm.reset(),
                                    });
                                }}>
                                    <SelectField
                                        label={t('Customer')}
                                        value={salesOrderForm.data.customer_id}
                                        onChange={(value: string) => {
                                            const selected = customers.find((row) => String(row.id) === value);
                                            salesOrderForm.setData((data) => ({
                                                ...data,
                                                customer_id: value,
                                                rate: selected?.default_rate != null ? String(selected.default_rate) : data.rate,
                                            }));
                                        }}
                                        options={customerOptions}
                                        includeEmpty
                                        emptyLabel={t('Select customer')}
                                        helperText={t('Job-work-only customer profiles are blocked from sales-order flow. Rate auto-fills from the customer default rate and stays editable.')}
                                        disabled={customerOptions.length === 0}
                                        disabledReason={t('No customer profile found. Create customer profile first.')}
                                        error={salesOrderForm.errors.customer_id}
                                        required
                                    />
                                    {salesOrderForm.data.customer_id ? (() => {
                                        const customer = customers.find((row) => String(row.id) === salesOrderForm.data.customer_id);
                                        return customer ? <div className="grid grid-cols-3 gap-3 rounded-md border border-border bg-muted/30 p-3 text-sm">
                                            <div><span className="text-muted-foreground">{t('Operating Model')}</span><p className="font-medium">{formatTextileLabel(customer.operating_model || '-')}</p></div>
                                            <div><span className="text-muted-foreground">{t('Material Ownership')}</span><p className="font-medium">{formatTextileLabel(customer.material_ownership || '-')}</p></div>
                                            <div><span className="text-muted-foreground">{t('Billing Mode')}</span><p className="font-medium">{formatTextileLabel(customer.billing_mode || '-')}</p></div>
                                        </div> : null;
                                    })() : null}
                                    <div className="space-y-2">
                                        {salesOrderForm.data.lot_selections.map((line, index) => {
                                            const selectedLots = new Set(salesOrderForm.data.lot_selections.map((row) => row.lot_reference).filter(Boolean));
                                            const lotOptions = sellableLotOptions.map((option) => ({ ...option, disabled: option.value !== line.lot_reference && selectedLots.has(option.value) }));
                                            return <div key={index} className="grid grid-cols-[1fr_180px_40px] gap-2">
                                                <SelectField
                                                    label={index === 0 ? t('Takha Lots') : t('Takha Lot')}
                                                    value={line.lot_reference}
                                                    onChange={(value) => {
                                                        const option = sellableLotOptions.find((row) => row.value === value);
                                                        const rows = [...salesOrderForm.data.lot_selections];
                                                        rows[index] = { lot_reference: value, quantity: option?.available_quantity ?? '' };
                                                        salesOrderForm.setData('lot_selections', rows);
                                                    }}
                                                    options={lotOptions}
                                                    includeEmpty
                                                    emptyLabel={t('Select available Takha')}
                                                    disabled={sellableLotOptions.length === 0}
                                                    disabledReason={t('No produced Takha stock is available in this branch.')}
                                                    required
                                                />
                                                <Field
                                                    label={index === 0 ? t('Sale Quantity') : t('Quantity')}
                                                    type="number"
                                                    value={line.quantity}
                                                    onChange={(value) => {
                                                        const rows = [...salesOrderForm.data.lot_selections];
                                                        rows[index] = { ...line, quantity: value };
                                                        salesOrderForm.setData('lot_selections', rows);
                                                    }}
                                                    step="0.01"
                                                    required
                                                />
                                                <Button type="button" variant="outline" className="mt-6 h-10 w-10 p-0" disabled={salesOrderForm.data.lot_selections.length === 1} onClick={() => salesOrderForm.setData('lot_selections', salesOrderForm.data.lot_selections.filter((_row, rowIndex) => rowIndex !== index))} title={t('Remove Takha')}><Trash2 className="h-4 w-4" /></Button>
                                            </div>;
                                        })}
                                        <div className="flex items-center justify-between gap-3">
                                            <Button type="button" variant="outline" onClick={() => salesOrderForm.setData('lot_selections', [...salesOrderForm.data.lot_selections, { lot_reference: '', quantity: '' }])}><Plus className="mr-2 h-4 w-4" />{t('Add Takha')}</Button>
                                            <p className="text-sm text-muted-foreground">{t('Order Quantity')}: {selectedTakhaQuantity.toFixed(2)} {selectedTakhaUnits.length === 1 ? selectedTakhaUnits[0] : ''}{selectedTakhaUnits.length > 1 ? ` · ${t('Mixed units not allowed')}` : ''}</p>
                                        </div>
                                    </div>
                                    <Field label={t('Rate per Unit')} type="number" value={salesOrderForm.data.rate} onChange={(value: string) => salesOrderForm.setData('rate', value)} step="0.01" required helperText={(() => {
                                        const selected = customers.find((row) => String(row.id) === salesOrderForm.data.customer_id);
                                        return selected?.default_rate != null
                                            ? t('Auto-filled from this customer default rate. Adjust if needed.')
                                            : t('No default rate set for this customer. You can add one in the customer profile.');
                                    })()} />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Required Delivery Date')} type="date" value={salesOrderForm.data.required_delivery_date} onChange={(value: string) => salesOrderForm.setData('required_delivery_date', value)} required />
                                        <SelectField label={t('Delivery Warehouse')} value={salesOrderForm.data.warehouse} onChange={(value: string) => salesOrderForm.setData('warehouse', value)} options={warehouseOptions} includeEmpty emptyLabel={t('Select warehouse')} />
                                    </div>
                                    <Field label={t('Notes')} value={salesOrderForm.data.notes} onChange={(value: string) => salesOrderForm.setData('notes', value)} />
                                    <Button type="submit" disabled={salesOrderForm.processing || selectedTakhaQuantity <= 0 || selectedTakhaUnits.length !== 1} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Sales Order')}</Button>
                                </form>
                                    </TextileFormCard>
                                ) },
                            ];
                            return (
                                <div className="space-y-6">
                                    <TextileWorkflowSteps steps={steps} openId={openStep} onOpenChange={setOpenStep} records={
                                        <TextileDataTableCard
                                            data={salesOrders}
                                            columns={[
                                                { key: 'document_number', header: t('Order') },
                                                { key: 'party_name', header: t('Customer') },
                                                { key: 'item_name', header: t('Item'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.item_name ?? '-') },
                                                { key: 'quantity', header: t('Qty') },
                                                { key: 'unit', header: t('Unit') },
                                                { key: 'rate', header: t('Rate'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.rate ?? '-') },
                                                { key: 'order_value', header: t('Order Value'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.order_value ?? '-') },
                                                { key: 'required_delivery_date', header: t('Delivery'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.required_delivery_date ?? '-') },
                                                { key: 'status', header: t('Status'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(row.status) },
                                                { key: 'actions', header: t('Actions'), render: (_value: unknown, row: WorkflowDocument) => row.status === 'draft' ? <Button type="button" size="sm" variant="outline" onClick={() => approveSalesOrder(row.id)}><Check className="mr-1 h-3.5 w-3.5" />{t('Approve')}</Button> : null },
                                            ]}
                                            emptyState={<NoRecordsFound icon={Truck} title={t('No sales orders found')} description={t('Create sales orders from approved commercial references.')} />}
                                        />
                                    } />
                                </div>
                            );
                        }

                        case 'allocation-dispatch': {
                            const allocationStatuses = workflowStepStatuses([allocations.length, dispatches.length]);
                            const steps: WorkflowStep[] = [
                                { id: 'create-allocation', title: t('Create Allocation'), icon: ClipboardCheck, status: allocationStatuses[0], count: allocations.length, form: (
                                    <TextileFormCard showHeader={false}>
                                <form className="space-y-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    allocationForm.post(route('textile.sales.allocations.store'), {
                                        onSuccess: () => allocationForm.reset(),
                                    });
                                }}>
                                    <SelectField
                                        label={t('From Approved SO')}
                                        value={allocationForm.data.sales_order_id}
                                        onChange={(value: string) => allocationForm.setData('sales_order_id', value)}
                                        options={createTextileWorkflowSelectOptions(approvedSalesOrders)}
                                        includeEmpty
                                        emptyLabel={t('Select approved sales order')}
                                        helperText={t('Only approved sales orders are listed.')}
                                        disabled={approvedSalesOrders.length === 0}
                                        disabledReason={t('No approved sales order found. Approve a sales order first.')}
                                        required
                                    />
                                    {selectedSalesOrder ? <div className="rounded-md border border-border bg-muted/30 p-3">
                                        <p className="mb-2 text-sm font-medium">{t('Takhas selected on this order')}</p>
                                        <div className="space-y-1 text-sm text-muted-foreground">
                                            {((selectedSalesOrder.metadata?.requested_lots as Array<{ lot_reference: string; quantity: number; unit?: string }> | undefined) ?? []).map((line) => <div key={line.lot_reference} className="flex justify-between gap-3"><span>{line.lot_reference}</span><span>{line.quantity} {line.unit || selectedSalesOrder.unit || ''}</span></div>)}
                                        </div>
                                    </div> : null}
                                    <Button type="submit" disabled={allocationForm.processing || !selectedSalesOrder} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Reserve Selected Takhas')}</Button>
                                </form>
                                    </TextileFormCard>
                                ) },
                                { id: 'create-dispatch', title: t('Create Dispatch'), icon: Truck, status: allocationStatuses[1], count: dispatches.length, form: (
                                    <TextileFormCard showHeader={false}>
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    dispatchForm.post(route('textile.sales.dispatches.store'), {
                                        onSuccess: () => dispatchForm.reset('allocation_id'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('From Released Allocation')}
                                        value={dispatchForm.data.allocation_id}
                                        onChange={(value: string) => dispatchForm.setData('allocation_id', value)}
                                        options={createTextileWorkflowSelectOptions(releasedAllocations)}
                                        includeEmpty
                                        emptyLabel={t('Select released allocation')}
                                        helperText={t('Only released allocations are listed.')}
                                        disabled={releasedAllocations.length === 0}
                                        disabledReason={t('No released allocation found. Release an allocation first.')}
                                        required
                                    />
                                    <Button type="submit" disabled={dispatchForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Dispatch')}</Button>
                                </form>
                                    </TextileFormCard>
                                ) },
                            ];
                            const activeAllocationStepId = steps.some((step) => step.id === openStep) ? openStep : steps[0].id;
                            return (
                                <div className="space-y-6">
                                    <TextileWorkflowSteps steps={steps} openId={openStep} onOpenChange={setOpenStep} records={
                                        <div>
                                            {activeAllocationStepId === 'create-allocation' &&
                                            <TextileDataTableSection
                                                title={t('Allocation Records')}
                            data={allocations}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draftOrApproved,
                                        actions: [{ label: t('Release'), icon: Check, onClick: (row) => releaseAllocation(row.id) }],
                                    },
                                ]),
                            }).map((column) => column.key === 'lot_reference' ? {
                                ...column,
                                header: t('Allocated Lots'),
                                render: (_value: unknown, row: WorkflowDocument) => ((row.metadata?.lot_allocations as Array<{ lot_reference: string; quantity: number }> | undefined) ?? []).map((line) => `${line.lot_reference} (${line.quantity})`).join(', ') || row.lot_reference || '-',
                            } : column)}
                            emptyState={<NoRecordsFound icon={ClipboardCheck} title={t('No allocations found')} description={t('Create allocations from approved sales orders.')} />}
                        />}
                                            {activeAllocationStepId === 'create-dispatch' &&
                                            <TextileDataTableSection
                                                title={t('Dispatch Records')}
                                                data={dispatches}
                                                columns={createTextileWorkflowColumns(t, {
                                                    actions: createTextileWorkflowActions([
                                                        {
                                                            statuses: textileActionableStatuses.draftOrApproved,
                                                            actions: [{ label: t('Release'), icon: Check, onClick: (row) => releaseDispatch(row.id) }],
                                                        },
                                                    ]),
                                                })}
                                                emptyState={<NoRecordsFound icon={Truck} title={t('No dispatches found')} description={t('Create dispatches from released allocations.')} />}
                                            />}
                                        </div>
                                    } />
                                </div>
                            );
                        }

                        case 'challan-pod': {
                            const challanStatuses = workflowStepStatuses([challans.length, pods.length]);
                            const steps: WorkflowStep[] = [
                                { id: 'create-challan', title: t('Create Challan'), icon: ScrollText, status: challanStatuses[0], count: challans.length, form: (
                                    <TextileFormCard showHeader={false}>
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    challanForm.post(route('textile.sales.challans.store'), {
                                        onSuccess: () => challanForm.reset('dispatch_id'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('From Released Dispatch')}
                                        value={challanForm.data.dispatch_id}
                                        onChange={(value: string) => challanForm.setData('dispatch_id', value)}
                                        options={createTextileWorkflowSelectOptions(releasedDispatches)}
                                        includeEmpty
                                        emptyLabel={t('Select released dispatch')}
                                        helperText={t('Only released dispatches are listed.')}
                                        disabled={releasedDispatches.length === 0}
                                        disabledReason={t('No released dispatch found. Release a dispatch first.')}
                                        required
                                    />
                                    <Button type="submit" disabled={challanForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Challan')}</Button>
                                </form>
                                    </TextileFormCard>
                                ) },
                                { id: 'mark-pod', title: t('Record POD'), icon: Check, status: challanStatuses[1], count: pods.length, form: (
                                    <TextileFormCard showHeader={false}>
                                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(event) => {
                                            event.preventDefault();
                                            podForm.post(route('textile.sales.challans.pod'), { onSuccess: () => podForm.reset() });
                                        }}>
                                            <SelectField label={t('Delivered Challan')} value={podForm.data.challan_id} onChange={(value) => podForm.setData('challan_id', value)} options={createTextileWorkflowSelectOptions(pendingPodChallans)} includeEmpty emptyLabel={t('Select challan')} disabled={pendingPodChallans.length === 0} disabledReason={t('No challan is awaiting POD.')} required />
                                            <Button type="submit" disabled={podForm.processing} className="self-end"><Check className="mr-2 h-4 w-4" />{t('Mark POD')}</Button>
                                        </form>
                                    </TextileFormCard>
                                ) },
                            ];
                            const activeChallanStepId = steps.some((step) => step.id === openStep) ? openStep : steps[0].id;
                            return (
                                <div className="space-y-6">
                                    <TextileWorkflowSteps steps={steps} openId={openStep} onOpenChange={setOpenStep} records={
                                        <div>
                                            {activeChallanStepId === 'create-challan' &&
                                            <TextileDataTableSection
                                                title={t('Challan Records')}
                            data={challans}
                            columns={createTextileWorkflowColumns(t)}
                            emptyState={<NoRecordsFound icon={ClipboardCheck} title={t('No challans found')} description={t('Create challans for released dispatches.')} />}
                        />}
                                            {activeChallanStepId === 'mark-pod' &&
                                            <TextileDataTableSection
                                                title={t('POD Records')}
                                                data={pods}
                                                columns={createTextileWorkflowColumns(t)}
                                                emptyState={<NoRecordsFound icon={Check} title={t('No POD records found')} description={t('Mark POD to complete challan lifecycle.')} />}
                                            />}
                                        </div>
                                    } />
                                </div>
                            );
                        }

                        case 'quotations': return (
                            <TextileSection
                                table={
                                    <TextileDataTableCard
                                        data={quotations}
                                        columns={[
                                            { key: 'quotation_number', header: t('Quotation #') },
                                            { key: 'customer_name', header: t('Customer') },
                                            { key: 'quotation_date', header: t('Date') },
                                            { key: 'due_date', header: t('Due Date') },
                                            { key: 'total_amount', header: t('Total') },
                                            { key: 'status', header: t('Status'), render: formatTextileLabel },
                                            { key: 'converted_to_invoice', header: t('Invoiced'), render: (_v: unknown, row: QuotationRecord) => row.converted_to_invoice ? t('Yes') : t('No') },
                                        ]}
                                        emptyState={<NoRecordsFound icon={ScrollText} title={t('No quotations found')} description={t('Create quotations from the Quotations page.')} />}
                                    />
                                }
                            />
                        );

                        default:
                            return null;
                    }
                }}
            </TextileWorkspace>
        </AuthenticatedLayout>
    );
}

