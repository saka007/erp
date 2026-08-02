import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Truck, Plus, Check, ClipboardCheck } from 'lucide-react';
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
import { buildUnitOptions, textileSourceTypeOptions } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';

interface WorkflowDocument {
    id: number;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
}

interface CustomerOption {
    id: number;
    company_name: string;
    operating_model?: string | null;
    material_ownership?: string | null;
    billing_mode?: string | null;
}

export default function Index({
    salesOrders,
    allocations,
    dispatches,
    challans,
    pods,
    customers,
    sourceTypeOptions,
    unitOptions,
}: {
    salesOrders: WorkflowDocument[];
    allocations: WorkflowDocument[];
    dispatches: WorkflowDocument[];
    challans: WorkflowDocument[];
    pods: WorkflowDocument[];
    customers: CustomerOption[];
    sourceTypeOptions: string[];
    unitOptions: string[];
}) {
    const { t } = useTranslation();
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const validSections = new Set(['sales-order', 'allocation-dispatch', 'challan-pod']);
    const activeSection = sectionParam && validSections.has(sectionParam) ? sectionParam : 'sales-order';

    const salesOrderForm = useForm({
        source_reference_type: 'sales_quotation',
        source_reference_id: '',
        source_action: 'convert',
        customer_id: '',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const customerOptions = customers.map((customer) => ({
        value: String(customer.id),
        label: `${customer.company_name} | ${customer.operating_model || '-'} | ${customer.material_ownership || '-'} | ${customer.billing_mode || '-'}`,
    }));

    const allocationForm = useForm({ sales_order_id: '' });
    const dispatchForm = useForm({ allocation_id: '' });
    const challanForm = useForm({ dispatch_id: '' });
    const approvedSalesOrders = salesOrders.filter((row) => row.status === 'approved');
    const releasedAllocations = allocations.filter((row) => row.status === 'released');
    const releasedDispatches = dispatches.filter((row) => row.status === 'released');
    const resolvedSourceTypeOptions = sourceTypeOptions.length > 0
        ? sourceTypeOptions.map((value) => ({ value, label: value }))
        : textileSourceTypeOptions;
    const resolvedUnitOptions = buildUnitOptions(unitOptions);

    const allDocuments = [...salesOrders, ...allocations, ...dispatches, ...challans, ...pods];
    const draftedCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;

    const approveSalesOrder = (id: number) => {
        router.post(route('textile.sales.orders.approve'), { sales_order_id: id }, { preserveScroll: true });
    };

    const releaseAllocation = (id: number) => {
        router.post(route('textile.sales.allocations.release'), { allocation_id: id }, { preserveScroll: true });
    };

    const releaseDispatch = (id: number) => {
        router.post(route('textile.sales.dispatches.release'), { dispatch_id: id }, { preserveScroll: true });
    };

    const markPod = (id: number) => {
        router.post(route('textile.sales.challans.pod'), { challan_id: id }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Sales') }]} pageTitle={t('Textile Sales')}>
            <Head title={t('Textile Sales')} />

            <TextileKpiOverview
                title={t('Sales Overview')}
                className="mb-6"
                items={[
                    { label: t('Total Documents'), value: allDocuments.length, hint: t('SO + Allocation + Dispatch + Challan + POD') },
                    { label: t('Draft'), value: draftedCount, hint: t('Pending first approval') },
                    { label: t('Approved'), value: approvedCount, hint: t('Approved but not released') },
                    { label: t('Released'), value: releasedCount, hint: t('Ready for downstream flow') },
                ]}
            />

            <Tabs
                value={activeSection}
                onValueChange={(value: string) => router.get(route('textile.sales.index', { section: value }), {}, { preserveState: true, replace: true })}
                className="space-y-6"
            >
                <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-3">
                    <TabsTrigger value="sales-order">{t('Sales Order')}</TabsTrigger>
                    <TabsTrigger value="allocation-dispatch">{t('Allocation & Dispatch')}</TabsTrigger>
                    <TabsTrigger value="challan-pod">{t('Challan & POD')}</TabsTrigger>
                </TabsList>

                <TabsContent value="sales-order">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Sales Order')} icon={Plus}>
                                <form className="space-y-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    salesOrderForm.post(route('textile.sales.orders.store'), {
                                        onSuccess: () => salesOrderForm.reset('source_reference_id', 'customer_id', 'party_name', 'lot_reference', 'quantity'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('Source Type')}
                                        value={salesOrderForm.data.source_reference_type}
                                        onChange={(value: string) => salesOrderForm.setData('source_reference_type', value)}
                                        options={resolvedSourceTypeOptions}
                                        includeEmpty
                                        emptyLabel={t('Select source type')}
                                        helperText={t('Source types are managed from Textile Master Setup.')}
                                        required
                                    />
                                    <Field label={t('Source ID')} type="number" value={salesOrderForm.data.source_reference_id} onChange={(value: string) => salesOrderForm.setData('source_reference_id', value)} required />
                                    <Field label={t('Source Action')} value={salesOrderForm.data.source_action} onChange={(value: string) => salesOrderForm.setData('source_action', value)} required />
                                    <SelectField
                                        label={t('Customer Profile')}
                                        value={salesOrderForm.data.customer_id}
                                        onChange={(value: string) => {
                                            salesOrderForm.setData('customer_id', value);
                                            const selectedCustomer = customers.find((row) => String(row.id) === value);
                                            if (selectedCustomer) {
                                                salesOrderForm.setData('party_name', selectedCustomer.company_name);
                                            }
                                        }}
                                        options={customerOptions}
                                        includeEmpty
                                        emptyLabel={t('Select customer profile')}
                                        helperText={t('Job-work-only customer profiles are blocked from sales-order flow.')}
                                        disabled={customerOptions.length === 0}
                                        disabledReason={t('No customer profile found. Create customer profile first.')}
                                    />
                                    <Field label={t('Customer/Party')} value={salesOrderForm.data.party_name} onChange={(value: string) => salesOrderForm.setData('party_name', value)} />
                                    <Field label={t('Lot Reference')} value={salesOrderForm.data.lot_reference} onChange={(value: string) => salesOrderForm.setData('lot_reference', value)} required />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity')} type="number" value={salesOrderForm.data.quantity} onChange={(value: string) => salesOrderForm.setData('quantity', value)} required />
                                        <SelectField
                                            label={t('Unit')}
                                            value={salesOrderForm.data.unit}
                                            onChange={(value: string) => salesOrderForm.setData('unit', value)}
                                            options={resolvedUnitOptions}
                                            includeEmpty
                                            emptyLabel={t('Select unit')}
                                            helperText={t('Units are derived from Unit Conversion master.')}
                                        />
                                    </div>
                                    <Button type="submit" disabled={salesOrderForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Sales Order')}</Button>
                                </form>
                        </TextileFormCard>
                        <TextileDataTableCard
                            className="xl:col-span-2"
                            data={salesOrders}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draft,
                                        actions: [{ label: t('Approve'), icon: Check, onClick: (row) => approveSalesOrder(row.id) }],
                                    },
                                ]),
                            })}
                            emptyState={<NoRecordsFound icon={Truck} title={t('No sales orders found')} description={t('Create sales orders from approved commercial references.')} />}
                        />
                    </div>
                </TabsContent>

                <TabsContent value="allocation-dispatch">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Allocation')} icon={ClipboardCheck}>
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    allocationForm.post(route('textile.sales.allocations.store'), {
                                        onSuccess: () => allocationForm.reset('sales_order_id'),
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
                                    <Button type="submit" disabled={allocationForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Allocation')}</Button>
                                </form>
                        </TextileFormCard>
                        <TextileFormCard title={t('Dispatch')} icon={Truck}>
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
                            })}
                            emptyState={<NoRecordsFound icon={ClipboardCheck} title={t('No allocations found')} description={t('Create allocations from approved sales orders.')} />}
                        />
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
                        />
                    </div>
                </TabsContent>

                <TabsContent value="challan-pod">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Challan')} icon={Plus} contentClassName="p-5 space-y-4">
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
                        <TextileDataTableSection
                            title={t('Challan Records')}
                            data={challans}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draftApprovedOrReleased,
                                        actions: [{ label: t('Mark POD'), icon: Check, onClick: (row) => markPod(row.id) }],
                                    },
                                ]),
                            })}
                            emptyState={<NoRecordsFound icon={ClipboardCheck} title={t('No challans found')} description={t('Create challans for released dispatches.')} />}
                        />
                        <TextileDataTableSection
                            title={t('POD Records')}
                            data={pods}
                            columns={createTextileWorkflowColumns(t)}
                            emptyState={<NoRecordsFound icon={Check} title={t('No POD records found')} description={t('Mark POD to complete challan lifecycle.')} />}
                        />
                    </div>
                </TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}

