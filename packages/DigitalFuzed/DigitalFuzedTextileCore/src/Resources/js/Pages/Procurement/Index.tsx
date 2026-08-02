import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShoppingCart, Plus, Check, Truck } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';

interface WorkflowDocument {
    id: number;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    purchase_invoice_id?: number | null;
}

export default function Index({
    requisitions,
    purchaseOrders,
    grns,
    incomingQcs,
}: {
    requisitions: WorkflowDocument[];
    purchaseOrders: WorkflowDocument[];
    grns: WorkflowDocument[];
    incomingQcs: WorkflowDocument[];
}) {
    const { t } = useTranslation();
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const validSections = new Set(['requisitions', 'purchase-orders', 'grns', 'incoming-qc']);
    const activeSection = sectionParam && validSections.has(sectionParam) ? sectionParam : 'requisitions';

    const requisitionForm = useForm({
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'kg',
    });

    const purchaseOrderForm = useForm({ requisition_id: '' });
    const grnForm = useForm({ purchase_order_id: '' });
    const incomingQcForm = useForm({ grn_id: '' });
    const approvedRequisitions = requisitions.filter((row) => row.status === 'approved');
    const approvedPurchaseOrders = purchaseOrders.filter((row) => row.status === 'approved');
    const releasedGrns = grns.filter((row) => row.status === 'released');

    const allDocuments = [...requisitions, ...purchaseOrders, ...grns, ...incomingQcs];
    const draftCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;

    const approveRequisition = (id: number) => {
        router.post(route('textile.procurement.requisitions.approve'), { requisition_id: id }, { preserveScroll: true });
    };

    const approvePurchaseOrder = (id: number) => {
        router.post(route('textile.procurement.purchase-orders.approve'), { purchase_order_id: id }, { preserveScroll: true });
    };

    const releaseGrn = (id: number) => {
        router.post(route('textile.procurement.grns.release'), { grn_id: id }, { preserveScroll: true });
    };

    const syncInvoiceFromGrn = (id: number) => {
        router.post(route('textile.procurement.invoices.from-grn'), { grn_id: id }, { preserveScroll: true });
    };

    const finalizeIncomingQc = (id: number, decision: 'pass' | 'fail') => {
        router.post(route('textile.procurement.incoming-qc.finalize'), { incoming_qc_id: id, decision }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Procurement') }]} pageTitle={t('Textile Procurement')}>
            <Head title={t('Textile Procurement')} />

            <TextileKpiOverview
                title={t('Procurement Overview')}
                className="mb-6"
                items={[
                    { label: t('Total Documents'), value: allDocuments.length, hint: t('Requisition + PO + GRN + Incoming QC') },
                    { label: t('Draft'), value: draftCount, hint: t('Waiting action') },
                    { label: t('Approved'), value: approvedCount, hint: t('Ready for next stage') },
                    { label: t('Released'), value: releasedCount, hint: t('Operationally posted') },
                ]}
            />

            <Tabs
                value={activeSection}
                onValueChange={(value) => router.get(route('textile.procurement.index', { section: value }), {}, { preserveState: true, replace: true })}
                className="space-y-6"
            >
                <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-4">
                    <TabsTrigger value="requisitions">{t('Requisitions')}</TabsTrigger>
                    <TabsTrigger value="purchase-orders">{t('Purchase Orders')}</TabsTrigger>
                    <TabsTrigger value="grns">{t('GRN')}</TabsTrigger>
                    <TabsTrigger value="incoming-qc">{t('Incoming QC')}</TabsTrigger>
                </TabsList>

                <TabsContent value="requisitions">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Requisition')} icon={ShoppingCart}>
                                <form className="space-y-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    requisitionForm.post(route('textile.procurement.requisitions.store'), {
                                        onSuccess: () => requisitionForm.reset('party_name', 'lot_reference', 'quantity'),
                                    });
                                }}>
                                    <Field label={t('Supplier/Party')} value={requisitionForm.data.party_name} onChange={(v) => requisitionForm.setData('party_name', v)} />
                                    <Field label={t('Lot Reference')} value={requisitionForm.data.lot_reference} onChange={(v) => requisitionForm.setData('lot_reference', v)} required />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity')} type="number" value={requisitionForm.data.quantity} onChange={(v) => requisitionForm.setData('quantity', v)} required />
                                        <Field label={t('Unit')} value={requisitionForm.data.unit} onChange={(v) => requisitionForm.setData('unit', v)} />
                                    </div>
                                    <Button type="submit" disabled={requisitionForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Requisition')}</Button>
                                </form>
                        </TextileFormCard>

                        <TextileDataTableCard
                            className="xl:col-span-2"
                            data={requisitions}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draft,
                                        actions: [{ label: t('Approve'), icon: Check, onClick: (row) => approveRequisition(row.id) }],
                                    },
                                ]),
                            })}
                            emptyState={<NoRecordsFound icon={ShoppingCart} title={t('No requisitions found')} description={t('Create procurement requisitions to begin material planning.')} />}
                        />
                    </div>
                </TabsContent>

                <TabsContent value="purchase-orders">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Purchase Order')} icon={ShoppingCart}>
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    purchaseOrderForm.post(route('textile.procurement.purchase-orders.store'), {
                                        onSuccess: () => purchaseOrderForm.reset('requisition_id'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('From Approved Requisition')}
                                        value={purchaseOrderForm.data.requisition_id}
                                        onChange={(v) => purchaseOrderForm.setData('requisition_id', v)}
                                        options={createTextileWorkflowSelectOptions(approvedRequisitions)}
                                        includeEmpty
                                        emptyLabel={t('Select approved requisition')}
                                        helperText={t('Only approved requisitions are listed.')}
                                        disabled={approvedRequisitions.length === 0}
                                        disabledReason={t('No approved requisition found. Approve a requisition first.')}
                                        required
                                    />
                                    <Button type="submit" disabled={purchaseOrderForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create PO')}</Button>
                                </form>
                        </TextileFormCard>
                        <TextileDataTableCard
                            className="xl:col-span-2"
                            data={purchaseOrders}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draft,
                                        actions: [{ label: t('Approve'), icon: Check, onClick: (row) => approvePurchaseOrder(row.id) }],
                                    },
                                ]),
                            })}
                            emptyState={<NoRecordsFound icon={ShoppingCart} title={t('No purchase orders found')} description={t('Convert approved requisitions into purchase orders.')} />}
                        />
                    </div>
                </TabsContent>

                <TabsContent value="grns">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create GRN')} icon={Truck}>
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    grnForm.post(route('textile.procurement.grns.store'), {
                                        onSuccess: () => grnForm.reset('purchase_order_id'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('From Approved PO')}
                                        value={grnForm.data.purchase_order_id}
                                        onChange={(v) => grnForm.setData('purchase_order_id', v)}
                                        options={createTextileWorkflowSelectOptions(approvedPurchaseOrders)}
                                        includeEmpty
                                        emptyLabel={t('Select approved PO')}
                                        helperText={t('Only approved purchase orders are listed.')}
                                        disabled={approvedPurchaseOrders.length === 0}
                                        disabledReason={t('No approved PO found. Approve a purchase order first.')}
                                        required
                                    />
                                    <Button type="submit" disabled={grnForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create GRN')}</Button>
                                </form>
                        </TextileFormCard>
                        <TextileDataTableCard
                            className="xl:col-span-2"
                            data={grns}
                            columns={createTextileWorkflowColumns(t, {
                                includeInvoiceId: true,
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draftOrApproved,
                                        actions: [{ label: t('Release'), icon: Check, onClick: (row) => releaseGrn(row.id) }],
                                    },
                                    {
                                        statuses: ['released'],
                                        noVisibleActionContent: (row) =>
                                            row.purchase_invoice_id ? t('Invoice already synced') : t('No action'),
                                        actions: [{ label: t('Sync Invoice'), icon: Plus, onClick: (row) => syncInvoiceFromGrn(row.id), when: (row) => !row.purchase_invoice_id }],
                                    },
                                ]),
                            })}
                            emptyState={<NoRecordsFound icon={Truck} title={t('No GRNs found')} description={t('Create and release GRNs against approved purchase orders.')} />}
                        />
                    </div>
                </TabsContent>

                <TabsContent value="incoming-qc">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Incoming QC')} icon={Plus}>
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    incomingQcForm.post(route('textile.procurement.incoming-qc.store'), {
                                        onSuccess: () => incomingQcForm.reset('grn_id'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('From Released GRN')}
                                        value={incomingQcForm.data.grn_id}
                                        onChange={(v) => incomingQcForm.setData('grn_id', v)}
                                        options={createTextileWorkflowSelectOptions(releasedGrns)}
                                        includeEmpty
                                        emptyLabel={t('Select released GRN')}
                                        helperText={t('Only released GRN entries are listed.')}
                                        disabled={releasedGrns.length === 0}
                                        disabledReason={t('No released GRN found. Release a GRN first.')}
                                        required
                                    />
                                    <Button type="submit" disabled={incomingQcForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Incoming QC')}</Button>
                                </form>
                        </TextileFormCard>
                        <TextileDataTableCard
                            className="xl:col-span-2"
                            data={incomingQcs}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draft,
                                        actions: [
                                            { label: t('Pass'), icon: Check, onClick: (row) => finalizeIncomingQc(row.id, 'pass') },
                                            { label: t('Fail'), icon: Check, onClick: (row) => finalizeIncomingQc(row.id, 'fail') },
                                        ],
                                    },
                                ]),
                            })}
                            emptyState={<NoRecordsFound icon={Check} title={t('No incoming QC records found')} description={t('Create incoming QC entries from released GRNs.')} />}
                        />
                    </div>
                </TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}
