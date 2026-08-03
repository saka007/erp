import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShoppingCart, Plus, Check, Truck, FileText } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { buildUnitOptions, formatTextileLabel } from '@/components/textile/textile-form-options';
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
    purchase_invoice_id?: number | null;
    metadata?: Record<string, unknown>;
}

export default function Index({
    requisitions,
    rfqs,
    purchaseOrders,
    grns,
    incomingQcs,
    supplierClaims,
    unitOptions,
    partyOptions,
    lotReferenceOptions,
}: {
    requisitions: WorkflowDocument[];
    rfqs: WorkflowDocument[];
    purchaseOrders: WorkflowDocument[];
    grns: WorkflowDocument[];
    incomingQcs: WorkflowDocument[];
    supplierClaims: WorkflowDocument[];
    unitOptions: string[];
    partyOptions: string[];
    lotReferenceOptions: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('procurement_'));
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const visibleSections = hasFineGrainedCapabilities
        ? [
            textileCapabilities.procurement_requisition ? 'requisitions' : null,
            textileCapabilities.procurement_rfq ? 'rfqs' : null,
            textileCapabilities.procurement_purchase_order ? 'purchase-orders' : null,
            textileCapabilities.procurement_grn ? 'grns' : null,
            textileCapabilities.procurement_incoming_qc ? 'incoming-qc' : null,
            textileCapabilities.procurement_supplier_claims ? 'supplier-claims' : null,
        ].filter((value): value is string => value !== null)
        : ['requisitions', 'rfqs', 'purchase-orders', 'grns', 'incoming-qc', 'supplier-claims'];
    const validSections = new Set(visibleSections);
    const activeSection = sectionParam && validSections.has(sectionParam) ? sectionParam : (visibleSections[0] ?? 'requisitions');

    const requisitionForm = useForm({
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'kg',
    });

    const rfqForm = useForm({ requisition_id: '' });
    const purchaseOrderForm = useForm({ source_type: 'requisition', source_id: '' });
    const grnForm = useForm({ purchase_order_id: '' });
    const incomingQcForm = useForm({ grn_id: '' });
    const supplierClaimForm = useForm({
        grn_id: '',
        claim_type: 'quality',
        claim_amount: '',
        resolution_type: 'credit_note',
        claim_note: '',
    });
    const approvedRequisitions = requisitions.filter((row) => row.status === 'approved');
    const actionableRfqs = rfqs.filter((row) => row.status === 'approved' || row.status === 'released');
    const approvedPurchaseOrders = purchaseOrders.filter((row) => row.status === 'approved');
    const releasedGrns = grns.filter((row) => row.status === 'released');
    const resolvedPartyOptions = partyOptions.map((value) => ({ value, label: value }));
    const resolvedLotReferenceOptions = lotReferenceOptions.map((value) => ({ value, label: value }));
    const resolvedUnitOptions = buildUnitOptions(unitOptions);

    const allDocuments = [...requisitions, ...rfqs, ...purchaseOrders, ...grns, ...incomingQcs, ...supplierClaims];
    const draftCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;

    const approveRequisition = (id: number) => {
        router.post(route('textile.procurement.requisitions.approve'), { requisition_id: id }, { preserveScroll: true });
    };

    const sendRfq = (id: number) => {
        router.post(route('textile.procurement.rfqs.send'), { rfq_id: id }, { preserveScroll: true });
    };

    const closeRfq = (id: number) => {
        router.post(route('textile.procurement.rfqs.close'), { rfq_id: id }, { preserveScroll: true });
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

    const approveSupplierClaim = (id: number) => {
        router.post(route('textile.procurement.supplier-claims.approve'), { supplier_claim_id: id }, { preserveScroll: true });
    };

    const settleSupplierClaim = (id: number) => {
        router.post(route('textile.procurement.supplier-claims.settle'), { supplier_claim_id: id }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Procurement') }]} pageTitle={t('Textile Procurement')}>
            <Head title={t('Textile Procurement')} />

            <TextileKpiOverview
                title={t('Procurement Overview')}
                className="mb-6"
                items={[
                    { label: t('Total Documents'), value: allDocuments.length, hint: t('Requisition + RFQ + PO + GRN + Incoming QC + Claims') },
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
                <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-6">
                    {validSections.has('requisitions') ? <TabsTrigger value="requisitions">{t('Requisitions')}</TabsTrigger> : null}
                    {validSections.has('rfqs') ? <TabsTrigger value="rfqs">{t('RFQ')}</TabsTrigger> : null}
                    {validSections.has('purchase-orders') ? <TabsTrigger value="purchase-orders">{t('Purchase Orders')}</TabsTrigger> : null}
                    {validSections.has('grns') ? <TabsTrigger value="grns">{t('GRN')}</TabsTrigger> : null}
                    {validSections.has('incoming-qc') ? <TabsTrigger value="incoming-qc">{t('Incoming QC')}</TabsTrigger> : null}
                    {validSections.has('supplier-claims') ? <TabsTrigger value="supplier-claims">{t('Supplier Claims')}</TabsTrigger> : null}
                </TabsList>

                {validSections.has('requisitions') ? <TabsContent value="requisitions">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Requisition')} icon={ShoppingCart}>
                                <form className="space-y-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    requisitionForm.post(route('textile.procurement.requisitions.store'), {
                                        onSuccess: () => requisitionForm.reset('party_name', 'lot_reference', 'quantity'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('Supplier/Party')}
                                        value={requisitionForm.data.party_name}
                                        onChange={(v) => requisitionForm.setData('party_name', v)}
                                        options={resolvedPartyOptions}
                                        includeEmpty
                                        emptyLabel={t('Select supplier/party')}
                                        helperText={t('Party options are derived from vendor profiles and existing workflow records.')}
                                        disabled={resolvedPartyOptions.length === 0}
                                        disabledReason={t('No party options available yet. Create vendor profile first.')}
                                    />
                                    <SelectField
                                        label={t('Lot Reference')}
                                        value={requisitionForm.data.lot_reference}
                                        onChange={(v) => requisitionForm.setData('lot_reference', v)}
                                        options={resolvedLotReferenceOptions}
                                        includeEmpty
                                        emptyLabel={t('Select lot reference')}
                                        helperText={t('Lot references are derived from active inventory lots and workflow records.')}
                                        disabled={resolvedLotReferenceOptions.length === 0}
                                        disabledReason={t('No lot options available yet. Create active inventory lots first.')}
                                        required
                                    />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity')} type="number" value={requisitionForm.data.quantity} onChange={(v) => requisitionForm.setData('quantity', v)} required />
                                        <SelectField
                                            label={t('Unit')}
                                            value={requisitionForm.data.unit}
                                            onChange={(v) => requisitionForm.setData('unit', v)}
                                            options={resolvedUnitOptions}
                                            includeEmpty
                                            emptyLabel={t('Select unit')}
                                            helperText={t('Units are derived from Unit Conversion master.')}
                                        />
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
                </TabsContent> : null}

                {validSections.has('rfqs') ? <TabsContent value="rfqs">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create RFQ')} icon={FileText}>
                            <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                e.preventDefault();
                                rfqForm.post(route('textile.procurement.rfqs.store'), {
                                    onSuccess: () => rfqForm.reset('requisition_id'),
                                });
                            }}>
                                <SelectField
                                    label={t('From Approved Requisition')}
                                    value={rfqForm.data.requisition_id}
                                    onChange={(v) => rfqForm.setData('requisition_id', v)}
                                    options={createTextileWorkflowSelectOptions(approvedRequisitions)}
                                    includeEmpty
                                    emptyLabel={t('Select approved requisition')}
                                    helperText={t('Only approved requisitions are listed.')}
                                    disabled={approvedRequisitions.length === 0}
                                    disabledReason={t('No approved requisition found. Approve a requisition first.')}
                                    required
                                />
                                <Button type="submit" disabled={rfqForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create RFQ')}</Button>
                            </form>
                        </TextileFormCard>

                        <TextileDataTableCard
                            className="xl:col-span-2"
                            data={rfqs}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draft,
                                        actions: [{ label: t('Send RFQ'), icon: Check, onClick: (row) => sendRfq(row.id) }],
                                    },
                                    {
                                        statuses: ['approved'],
                                        actions: [{ label: t('Close RFQ'), icon: Check, onClick: (row) => closeRfq(row.id) }],
                                    },
                                ]),
                            })}
                            emptyState={<NoRecordsFound icon={FileText} title={t('No RFQs found')} description={t('Create RFQ records from approved requisitions.')} />}
                        />
                    </div>
                </TabsContent> : null}

                {validSections.has('purchase-orders') ? <TabsContent value="purchase-orders">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Purchase Order')} icon={ShoppingCart}>
                                <form className="space-y-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    purchaseOrderForm.post(route('textile.procurement.purchase-orders.store'), {
                                        onSuccess: () => purchaseOrderForm.reset('source_id'),
                                    });
                                }}>
                                    <SelectField
                                        label={t('Source Type')}
                                        value={purchaseOrderForm.data.source_type}
                                        onChange={(v) => {
                                            purchaseOrderForm.setData('source_type', v);
                                            purchaseOrderForm.setData('source_id', '');
                                        }}
                                        options={[
                                            { value: 'requisition', label: t('Approved Requisition') },
                                            { value: 'rfq', label: t('Sent RFQ') },
                                        ]}
                                        required
                                    />
                                    <SelectField
                                        label={purchaseOrderForm.data.source_type === 'rfq' ? t('From Sent RFQ') : t('From Approved Requisition')}
                                        value={purchaseOrderForm.data.source_id}
                                        onChange={(v) => purchaseOrderForm.setData('source_id', v)}
                                        options={createTextileWorkflowSelectOptions(purchaseOrderForm.data.source_type === 'rfq' ? actionableRfqs : approvedRequisitions)}
                                        includeEmpty
                                        emptyLabel={purchaseOrderForm.data.source_type === 'rfq' ? t('Select sent RFQ') : t('Select approved requisition')}
                                        helperText={purchaseOrderForm.data.source_type === 'rfq' ? t('Only sent RFQs are listed.') : t('Only approved requisitions are listed.')}
                                        disabled={(purchaseOrderForm.data.source_type === 'rfq' ? actionableRfqs.length : approvedRequisitions.length) === 0}
                                        disabledReason={purchaseOrderForm.data.source_type === 'rfq' ? t('No sent RFQ found. Send an RFQ first.') : t('No approved requisition found. Approve a requisition first.')}
                                        required
                                    />
                                    <Button type="submit" disabled={purchaseOrderForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create PO')}</Button>
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
                </TabsContent> : null}

                {validSections.has('grns') ? <TabsContent value="grns">
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
                </TabsContent> : null}

                {validSections.has('incoming-qc') ? <TabsContent value="incoming-qc">
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
                </TabsContent> : null}

                {validSections.has('supplier-claims') ? <TabsContent value="supplier-claims">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <TextileFormCard title={t('Create Supplier Claim')} icon={FileText}>
                            <form className="space-y-3" onSubmit={(e) => {
                                e.preventDefault();
                                supplierClaimForm.post(route('textile.procurement.supplier-claims.store'), {
                                    onSuccess: () => supplierClaimForm.reset('grn_id', 'claim_amount', 'claim_note'),
                                });
                            }}>
                                <SelectField
                                    label={t('From Released GRN')}
                                    value={supplierClaimForm.data.grn_id}
                                    onChange={(v) => supplierClaimForm.setData('grn_id', v)}
                                    options={createTextileWorkflowSelectOptions(releasedGrns)}
                                    includeEmpty
                                    emptyLabel={t('Select released GRN')}
                                    helperText={t('Only released GRN entries are listed.')}
                                    disabled={releasedGrns.length === 0}
                                    disabledReason={t('No released GRN found. Release a GRN first.')}
                                    required
                                />
                                <SelectField
                                    label={t('Claim Type')}
                                    value={supplierClaimForm.data.claim_type}
                                    onChange={(v) => supplierClaimForm.setData('claim_type', v)}
                                    options={[
                                        { value: 'quality', label: t('Quality') },
                                        { value: 'quantity', label: t('Quantity') },
                                        { value: 'damage', label: t('Damage') },
                                        { value: 'delay', label: t('Delay') },
                                        { value: 'rate_difference', label: t('Rate Difference') },
                                    ]}
                                    required
                                />
                                <Field label={t('Claim Amount')} type="number" value={supplierClaimForm.data.claim_amount} onChange={(v) => supplierClaimForm.setData('claim_amount', v)} required />
                                <SelectField
                                    label={t('Resolution Type')}
                                    value={supplierClaimForm.data.resolution_type}
                                    onChange={(v) => supplierClaimForm.setData('resolution_type', v)}
                                    options={[
                                        { value: 'replacement', label: t('Replacement') },
                                        { value: 'credit_note', label: t('Credit Note') },
                                        { value: 'debit_adjustment', label: t('Debit Adjustment') },
                                        { value: 'return_to_vendor', label: t('Return To Vendor') },
                                    ]}
                                    required
                                />
                                <Field label={t('Claim Note')} value={supplierClaimForm.data.claim_note} onChange={(v) => supplierClaimForm.setData('claim_note', v)} />
                                <Button type="submit" disabled={supplierClaimForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Claim')}</Button>
                            </form>
                        </TextileFormCard>

                        <TextileDataTableCard
                            className="xl:col-span-2"
                            data={supplierClaims}
                            columns={[
                                { key: 'document_number', header: t('Document') },
                                { key: 'party_name', header: t('Party') },
                                { key: 'lot_reference', header: t('Lot') },
                                { key: 'claim_type', header: t('Claim Type'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.claim_type ?? '')) },
                                { key: 'claim_amount', header: t('Claim Amount'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.claim_amount ?? '-') },
                                { key: 'resolution_type', header: t('Resolution'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.resolution_type ?? '')) },
                                { key: 'status', header: t('Status'), render: formatTextileLabel },
                                {
                                    key: 'actions',
                                    header: t('Actions'),
                                    render: (_value: unknown, row: WorkflowDocument) => (
                                        <div className="flex items-center gap-2">
                                            {row.status === 'draft' ? (
                                                <Button type="button" variant="outline" size="sm" onClick={() => approveSupplierClaim(row.id)}>
                                                    <Check className="mr-1 h-3.5 w-3.5" />
                                                    {t('Approve')}
                                                </Button>
                                            ) : null}
                                            {row.status === 'approved' ? (
                                                <Button type="button" variant="outline" size="sm" onClick={() => settleSupplierClaim(row.id)}>
                                                    <Check className="mr-1 h-3.5 w-3.5" />
                                                    {t('Settle')}
                                                </Button>
                                            ) : null}
                                            {row.status !== 'draft' && row.status !== 'approved' ? <span className="text-xs text-muted-foreground">{t('No action')}</span> : null}
                                        </div>
                                    ),
                                },
                            ]}
                            emptyState={<NoRecordsFound icon={FileText} title={t('No supplier claims found')} description={t('Create supplier claims from released GRNs for quality and settlement tracking.')} />}
                        />
                    </div>
                </TabsContent> : null}
            </Tabs>
        </AuthenticatedLayout>
    );
}
