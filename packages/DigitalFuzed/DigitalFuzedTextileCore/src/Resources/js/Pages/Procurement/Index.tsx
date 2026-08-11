import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ShoppingCart, Plus, Check, Truck, FileText, ListChecks, FileEdit, CheckCircle2, Send, LayoutDashboard, Receipt, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormErrors } from '@/components/textile/textile-form-errors';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileSection } from '@/components/textile/textile-section';
import { TextileWorkspace, countSectionStatuses } from '@/components/textile/textile-workspace';
import { TextileInfoPanel, WorkflowStage, SupplierSummary, ActivityItem } from '@/components/textile/textile-info-panel';
import { getTextileWorkspace } from '@/components/textile/textile-workspaces';
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
    purchase_invoice_id?: number | null;
    metadata?: Record<string, unknown>;
}

interface PurchaseInvoiceRecord {
    id: number;
    invoice_number: string;
    vendor_name: string;
    invoice_date: string;
    due_date: string;
    total_amount: string;
    paid_amount: string;
    balance_amount: string;
    status: string;
}

interface ProductItem {
    id: number;
    name: string;
    sku?: string | null;
    unit?: string | null;
    purchase_price?: number | null;
    sale_price?: number | null;
}

interface VendorPriceListEntry {
    product_service_item_id: number;
    product_name?: string | null;
    product_sku?: string | null;
    unit?: string | null;
    unit_price?: number | null;
    min_quantity?: number | null;
    currency_code?: string | null;
}

export default function Index({
    requisitions,
    rfqs,
    purchaseOrders,
    grns,
    incomingQcs,
    supplierClaims,
    purchaseInvoices,
    unitOptions,
    partyOptions,
    lotReferenceOptions,
    suppliers,
    requisitionSupplierTypeMap,
    items,
    warehouses,
    recentActivity,
}: {
    requisitions: WorkflowDocument[];
    rfqs: WorkflowDocument[];
    purchaseOrders: WorkflowDocument[];
    grns: WorkflowDocument[];
    incomingQcs: WorkflowDocument[];
    supplierClaims: WorkflowDocument[];
    purchaseInvoices: PurchaseInvoiceRecord[];
    unitOptions: string[];
    partyOptions: string[];
    lotReferenceOptions: string[];
    suppliers: SupplierSummary[];
    requisitionSupplierTypeMap?: Record<string, string[]>;
    items: ProductItem[];
    warehouses: Array<{ id: number; name: string; address?: string | null; branch_id?: number | null }>;
    recentActivity: ActivityItem[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const userPermissions = auth.user?.permissions || [];
    const userType = auth.user?.type || '';
    const canSelectAnyWarehouse = userType !== 'staff' && userPermissions.includes('manage-any-warehouses');
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const procurementWorkspace = getTextileWorkspace('procurement')!;
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const activeMenuSection = procurementWorkspace.sections.find((section) => section.id === sectionParam);
    const [viewingRfq, setViewingRfq] = useState<WorkflowDocument | null>(null);
    const [viewingProforma, setViewingProforma] = useState<WorkflowDocument | null>(null);
    const [viewingIncomingQc, setViewingIncomingQc] = useState<WorkflowDocument | null>(null);
    const [viewingSupplierClaim, setViewingSupplierClaim] = useState<WorkflowDocument | null>(null);
    const [deletingRequisition, setDeletingRequisition] = useState<WorkflowDocument | null>(null);

    const requisitionForm = useForm({
        party_name: '',
        vendor_id: '',
        lot_reference: '',
        quantity: '',
        unit: 'kg',
        requisition_type: 'yarn',
        product_service_item_id: '',
        rate: '',
        priority: 'medium',
        required_for: '',
        expected_date: '',
        remarks: '',
        warehouse: '',
        warehouse_id: '',
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
    const approvedRfqs = rfqs.filter((row) => row.status === 'approved' || row.status === 'released');
    const approvedPurchaseOrders = purchaseOrders.filter((row) => row.status === 'approved');
    const releasedGrns = grns.filter((row) => row.status === 'released');
    const accessibleInvoiceIds = new Set(purchaseInvoices.map((invoice) => invoice.id));
    const requisitionType = requisitionForm.data.requisition_type;
    const allowedSupplierTypes = requisitionSupplierTypeMap?.[requisitionType] ?? [];
    const typeRestricted = allowedSupplierTypes.length > 0;
    const filteredSuppliers = typeRestricted
        ? suppliers.filter((supplier) => allowedSupplierTypes.includes(supplier.supplier_type ?? ''))
        : suppliers;
    const filteredSupplierNames = new Set(filteredSuppliers.map((supplier) => supplier.name));
    const resolvedPartyOptions = partyOptions
        .filter((value) => !typeRestricted || filteredSupplierNames.has(value))
        .map((value) => ({ value, label: value }));
    const resolvedLotReferenceOptions = lotReferenceOptions.map((value) => ({ value, label: value }));
    const resolvedUnitOptions = buildUnitOptions(unitOptions);
    const selectedSupplierCredit = (() => {
        const match = suppliers.find((supplier) => supplier.name === requisitionForm.data.party_name);
        return match && match.credit_enabled
            ? { days: match.credit_days ?? 0 }
            : null;
    })();

    const selectedSupplier = suppliers.find((supplier) => supplier.name === requisitionForm.data.party_name) ?? null;
    const selectedVendorPriceLists = selectedSupplier?.price_lists ?? [];
    const resolvedProductOptions = (() => {
        if (selectedVendorPriceLists.length > 0) {
            return selectedVendorPriceLists.map((entry) => ({
                value: String(entry.product_service_item_id),
                label: `${entry.product_name ?? 'Product'}${entry.product_sku ? ` (${entry.product_sku})` : ''}${entry.unit_price != null ? ` @ ${Number(entry.unit_price).toFixed(2)}` : ''}`,
            }));
        }
        return items.map((item) => ({
            value: String(item.id),
            label: `${item.name}${item.sku ? ` (${item.sku})` : ''}${item.purchase_price != null ? ` @ ${Number(item.purchase_price).toFixed(2)}` : ''}`,
        }));
    })();

    const handleVendorChange = (value: string) => {
        const vendor = suppliers.find((supplier) => supplier.name === value);
        requisitionForm.setData((data) => ({
            ...data,
            party_name: value,
            vendor_id: vendor ? String(vendor.id) : '',
            product_service_item_id: '',
            rate: '',
        }));
    };

    const handleProductChange = (value: string) => {
        const productId = Number(value);
        const priceListEntry = selectedVendorPriceLists.find((entry) => entry.product_service_item_id === productId);
        const product = items.find((item) => item.id === productId);
        const prefilledRate = priceListEntry?.unit_price != null
            ? String(priceListEntry.unit_price)
            : (product?.purchase_price != null ? String(product.purchase_price) : '');
        requisitionForm.setData((data) => ({
            ...data,
            product_service_item_id: value,
            rate: prefilledRate,
        }));
    };

    const requisitionAmount = (() => {
        const quantity = Number(requisitionForm.data.quantity) || 0;
        const rate = Number(requisitionForm.data.rate) || 0;
        return quantity > 0 && rate > 0 ? quantity * rate : null;
    })();

    const allDocuments = [...requisitions, ...rfqs, ...purchaseOrders, ...grns, ...incomingQcs, ...supplierClaims];
    const draftCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;

    const approveRequisition = (id: number) => {
        router.post(route('textile.procurement.requisitions.approve'), { requisition_id: id }, { preserveScroll: true });
    };

    const deleteRequisition = () => {
        if (!deletingRequisition) {
            return;
        }

        router.delete(route('textile.procurement.requisitions.destroy', deletingRequisition.id), {
            preserveScroll: true,
            onSuccess: () => setDeletingRequisition(null),
        });
    };

    const sendRfq = (id: number) => {
        router.post(route('textile.procurement.rfqs.send'), { rfq_id: id }, { preserveScroll: true });
    };

    const closeRfq = (id: number) => {
        router.post(route('textile.procurement.rfqs.close'), { rfq_id: id }, { preserveScroll: true });
    };

    const sectionRows: Record<string, WorkflowDocument[]> = {
        overview: allDocuments,
        requisitions,
        rfqs,
        'purchase-orders': purchaseOrders,
        grns,
        'incoming-qc': incomingQcs,
        'supplier-claims': supplierClaims,
        bills: [],
    }
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
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Textile') },
                { label: t('Procurement'), url: route('textile.procurement.index') },
                ...(activeMenuSection ? [{ label: t(activeMenuSection.label) }] : []),
            ]}
            pageTitle={t('Textile Procurement')}
            pageActions={
                <Button
                    onClick={() => router.get(route('textile.procurement.index', { section: 'requisitions' }))}
                    className="bg-emerald-500 text-white hover:bg-emerald-600"
                >
                    <Plus className="mr-2 h-4 w-4" />
                    {t('New Requisition')}
                </Button>
            }
        >
            <Head title={t('Textile Procurement')} />

            <TextileWorkspace
                workspace={procurementWorkspace}
                capabilities={textileCapabilities}
                kpis={(section) => {
                    const counts = countSectionStatuses(sectionRows[section.id] ?? []);
                    return [
                        { label: t('Total'), value: counts.total, hint: t('Records in this section'), icon: ListChecks },
                        { label: t('Draft'), value: counts.draft, hint: t('Waiting action'), icon: FileEdit },
                        { label: t('Approved'), value: counts.approved, hint: t('Ready for next stage'), icon: CheckCircle2 },
                        { label: t('Released'), value: counts.released, hint: t('Operationally posted'), icon: Send },
                    ];
                }}
                aside={(section) => {
                    const stages: WorkflowStage[] = [
                        { id: 'requisitions', label: t('Requisition'), count: requisitions.length, active: section.id === 'requisitions' },
                        { id: 'approval', label: t('Approval'), count: approvedRequisitions.length, active: false },
                        { id: 'rfqs', label: t('RFQ (Request for Quotation)'), count: rfqs.length, active: section.id === 'rfqs' },
                        { id: 'purchase-orders', label: t('Purchase Order'), count: purchaseOrders.length, active: section.id === 'purchase-orders' },
                        { id: 'grns', label: t('GRN (Goods Received Note)'), count: grns.length, active: section.id === 'grns' },
                        { id: 'incoming-qc', label: t('Incoming QC'), count: incomingQcs.length, active: section.id === 'incoming-qc' },
                        { id: 'invoice', label: t('Invoice'), count: grns.filter((row) => row.purchase_invoice_id).length, active: false },
                        { id: 'bills', label: t('Purchase Bills'), count: purchaseInvoices.length, active: section.id === 'bills' },
                    ];
                    return <TextileInfoPanel stages={stages} supplier={suppliers[0] ?? null} activities={recentActivity} />;
                }}
            >
                {(section) => {
                    switch (section.id) {
                        case 'overview': return (
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
                                            { key: 'status', header: t('Status'), render: formatTextileLabel },
                                        ]}
                                        emptyState={<NoRecordsFound icon={LayoutDashboard} title={t('No procurement records yet')} description={t('Create requisitions to start the procurement pipeline.')} />}
                                    />
                                }
                            />
                        );

                        case 'requisitions': return (
                            <TextileSection
                                formTitle={t('Create Requisition')}
                                formIcon={ShoppingCart}
                                form={
                                <form className="space-y-4" onSubmit={(e) => {
                                    e.preventDefault();
                                    requisitionForm.post(route('textile.procurement.requisitions.store'), {
                                        onSuccess: () => requisitionForm.reset('party_name', 'vendor_id', 'lot_reference', 'quantity', 'product_service_item_id', 'rate', 'required_for', 'expected_date', 'remarks', 'warehouse', 'warehouse_id'),
                                    });
                                }}>
                                    <TextileFormErrors errors={requisitionForm.errors} />
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="space-y-3">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Supplier Information')}</p>
                                            <SelectField
                                                label={t('Supplier/Party')}
                                                value={requisitionForm.data.party_name}
                                                onChange={handleVendorChange}
                                                options={resolvedPartyOptions}
                                                includeEmpty
                                                emptyLabel={t('Select supplier/party')}
                                                helperText={typeRestricted
                                                    ? t('Only suppliers of the selected requisition type are listed.')
                                                    : t('Party options are derived from vendor profiles and existing workflow records.')}
                                                disabled={resolvedPartyOptions.length === 0}
                                                disabledReason={t('No party options available yet. Create vendor profile first.')}
                                            />
                                            {selectedSupplierCredit ? (
                                                <p className="text-xs text-emerald-600 font-medium">
                                                    {t('Credit Terms')}: {t('Payment allowed within')} {selectedSupplierCredit.days} {t('days')}
                                                </p>
                                            ) : null}
                                            <SelectField
                                                label={t('Lot Reference')}
                                                value={requisitionForm.data.lot_reference}
                                                onChange={(v) => requisitionForm.setData('lot_reference', v)}
                                                options={resolvedLotReferenceOptions}
                                                includeEmpty
                                                emptyLabel={t('Select lot reference (optional)')}
                                                helperText={t('Optional. If left blank, system auto-generates a lot reference on requisition creation.')}
                                                disabled={resolvedLotReferenceOptions.length === 0}
                                                disabledReason={t('No lot options available yet. You can still submit this requisition without a lot reference.')}
                                            />
                                        </div>
                                        <div className="space-y-3">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Material Details')}</p>
                                            <SelectField
                                                label={t('Requisition Type')}
                                                value={requisitionForm.data.requisition_type}
                                                onChange={(v) => {
                                                    const newAllowedTypes = requisitionSupplierTypeMap?.[v] ?? [];
                                                    const newTypeRestricted = newAllowedTypes.length > 0;
                                                    const currentParty = requisitionForm.data.party_name;
                                                    const stillEligible = currentParty === '' || !newTypeRestricted || suppliers.some(
                                                        (supplier) => supplier.name === currentParty && newAllowedTypes.includes(supplier.supplier_type ?? ''),
                                                    );
                                                    requisitionForm.setData((data) => ({
                                                        ...data,
                                                        requisition_type: v,
                                                        ...(stillEligible ? {} : { party_name: '', vendor_id: '', product_service_item_id: '', rate: '' }),
                                                    }));
                                                }}
                                                options={[
                                                    { value: 'yarn', label: t('Yarn') },
                                                    { value: 'beam', label: t('Beam') },
                                                    { value: 'grey_fabric', label: t('Grey Fabric') },
                                                    { value: 'finished_fabric', label: t('Finished Fabric') },
                                                    { value: 'chemical', label: t('Chemical') },
                                                    { value: 'packing_material', label: t('Packing Material') },
                                                    { value: 'spare_part', label: t('Spare Part') },
                                                    { value: 'service', label: t('Service') },
                                                    { value: 'general', label: t('General') },
                                                ]}
                                                required
                                                helperText={t('Helps manager identify request purpose before lot creation.')}
                                            />
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
                                            <SelectField
                                                label={t('Product')}
                                                value={requisitionForm.data.product_service_item_id}
                                                onChange={handleProductChange}
                                                options={resolvedProductOptions}
                                                includeEmpty
                                                emptyLabel={t('Select product')}
                                                helperText={selectedVendorPriceLists.length > 0
                                                    ? t('Rate auto-fills from this supplier price list.')
                                                    : t('Select supplier first to use their price list; otherwise base purchase price is suggested.')}
                                                disabled={resolvedProductOptions.length === 0}
                                                disabledReason={t('No products available. Create products in ProductService first.')}
                                            />
                                            <Field
                                                label={t('Rate per Unit')}
                                                type="number"
                                                value={requisitionForm.data.rate}
                                                onChange={(v) => requisitionForm.setData('rate', v)}
                                                step="0.01"
                                                helperText={selectedVendorPriceLists.length > 0
                                                    ? t('Auto-filled from this supplier price list. Adjust if needed.')
                                                    : t('Optional. Used to compute the expected purchase amount.')}
                                            />
                                            {requisitionAmount !== null ? (
                                                <p className="text-xs font-medium text-emerald-600">
                                                    {t('Expected Amount')}: {requisitionAmount.toFixed(2)}
                                                </p>
                                            ) : null}
                                            {warehouses.length > 0 ? (
                                                <SelectField
                                                    label={t('Warehouse')}
                                                    value={requisitionForm.data.warehouse_id}
                                                    onChange={(v) => requisitionForm.setData('warehouse_id', v)}
                                                    options={warehouses.map((warehouse) => ({
                                                        value: String(warehouse.id),
                                                        label: warehouse.address ? `${warehouse.name} (${warehouse.address})` : warehouse.name,
                                                    }))}
                                                    includeEmpty
                                                    emptyLabel={t('Select receiving warehouse')}
                                                    helperText={t('Warehouse is scoped to the active branch. The GRN purchase invoice will be posted against this warehouse.')}
                                                />
                                            ) : canSelectAnyWarehouse ? (
                                                <Field label={t('Warehouse')} value={requisitionForm.data.warehouse} onChange={(v) => requisitionForm.setData('warehouse', v)} />
                                            ) : null}
                                            <Field label={t('Expected Date')} type="date" value={requisitionForm.data.expected_date} onChange={(v) => requisitionForm.setData('expected_date', v)} />
                                        </div>
                                        <div className="space-y-3">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('Other Details')}</p>
                                            <SelectField
                                                label={t('Priority')}
                                                value={requisitionForm.data.priority}
                                                onChange={(v) => requisitionForm.setData('priority', v)}
                                                options={[
                                                    { value: 'high', label: t('High') },
                                                    { value: 'medium', label: t('Medium') },
                                                    { value: 'low', label: t('Low') },
                                                ]}
                                                required
                                            />
                                            <Field label={t('Required For')} value={requisitionForm.data.required_for} onChange={(v) => requisitionForm.setData('required_for', v)} />
                                            <Field label={t('Remarks')} value={requisitionForm.data.remarks} onChange={(v) => requisitionForm.setData('remarks', v)} />
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={requisitionForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Requisition')}</Button>
                                </form>
                                }
                                table={
                                    <TextileDataTableCard
                                        data={requisitions}
                                        columns={[
                                            { key: 'document_number', header: t('Document') },
                                            { key: 'party_name', header: t('Party') },
                                            { key: 'lot_reference', header: t('Lot') },
                                            { key: 'item_name', header: t('Item'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.item_name ?? '-') },
                                            { key: 'requisition_type', header: t('Type'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.requisition_type ?? 'general')) },
                                            { key: 'quantity', header: t('Qty') },
                                            { key: 'unit', header: t('Unit') },
                                            { key: 'rate', header: t('Rate'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.rate != null ? String(row.metadata.rate) : '-' },
                                            { key: 'invoice_amount', header: t('Amount'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.invoice_amount != null ? String(row.metadata.invoice_amount) : '-' },
                                            { key: 'priority', header: t('Priority'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.priority ?? '-')) },
                                            { key: 'status', header: t('Status'), render: formatTextileLabel },
                                            {
                                                key: 'actions',
                                                header: t('Actions'),
                                                render: (_value: unknown, row: WorkflowDocument) => (
                                                    <div className="flex items-center gap-2">
                                                        {row.status === 'draft' ? (
                                                            <>
                                                                <Button type="button" variant="outline" size="sm" onClick={() => approveRequisition(row.id)}>
                                                                    <Check className="mr-1 h-3.5 w-3.5" />
                                                                    {t('Approve')}
                                                                </Button>
                                                                <Button type="button" variant="outline" size="sm" className="text-destructive hover:text-destructive" onClick={() => setDeletingRequisition(row)}>
                                                                    <Trash2 className="mr-1 h-3.5 w-3.5" />
                                                                    {t('Delete')}
                                                                </Button>
                                                            </>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">{t('No action')}</span>
                                                        )}
                                                    </div>
                                                ),
                                            },
                                        ]}
                                        emptyState={<NoRecordsFound icon={ShoppingCart} title={t('No requisitions found')} description={t('Create procurement requisitions to begin material planning.')} />}
                                    />
                                }
                            />
                        );

                case 'rfqs': return (
                    <TextileSection
                        formTitle={t('Create RFQ (Request for Quotation)')}
                        formIcon={FileText}
                        form={
                            <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                e.preventDefault();
                                rfqForm.post(route('textile.procurement.rfqs.store'), {
                                    onSuccess: () => rfqForm.reset('requisition_id'),
                                });
                            }}>
                                <div className="col-span-2"><TextileFormErrors errors={rfqForm.errors} /></div>
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
                                }
                                table={
                                    <TextileDataTableCard
                                        data={rfqs}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draft,
                                        actions: [{ label: t('Send RFQ'), icon: Check, onClick: (row) => sendRfq(row.id) }],
                                    },
                                    {
                                        statuses: ['approved'],
                                        actions: [
                                            { label: t('View RFQ'), icon: FileText, onClick: (row) => setViewingRfq(row as WorkflowDocument) },
                                            { label: t('Close RFQ'), icon: Check, onClick: (row) => closeRfq(row.id) },
                                        ],
                                    },
                                    {
                                        statuses: ['released', 'closed'],
                                        actions: [{ label: t('View RFQ'), icon: FileText, onClick: (row) => setViewingRfq(row as WorkflowDocument) }],
                                    },
                                ]),
                            })}
                                emptyState={<NoRecordsFound icon={FileText} title={t('No RFQs found')} description={t('Create RFQ records from approved requisitions.')} />}
                            />
                                }
                            />
                        );

                case 'purchase-orders': return (
                    <TextileSection
                        formTitle={t('Create Purchase Order')}
                        formIcon={ShoppingCart}
                        form={
                                <form className="space-y-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    purchaseOrderForm.post(route('textile.procurement.purchase-orders.store'), {
                                        onSuccess: () => purchaseOrderForm.reset('source_type', 'source_id'),
                                    });
                                }}>
                                    <TextileFormErrors errors={purchaseOrderForm.errors} />
                                    <div className="grid grid-cols-2 gap-2">
                                        <Button
                                            type="button"
                                            variant={purchaseOrderForm.data.source_type === 'requisition' ? 'default' : 'outline'}
                                            className="h-8 text-xs"
                                            onClick={() => purchaseOrderForm.setData('source_type', 'requisition')}
                                        >
                                            {t('From Requisition')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={purchaseOrderForm.data.source_type === 'rfq' ? 'default' : 'outline'}
                                            className="h-8 text-xs"
                                            onClick={() => purchaseOrderForm.setData('source_type', 'rfq')}
                                        >
                                            {t('From RFQ')}
                                        </Button>
                                    </div>
                                    {purchaseOrderForm.data.source_type === 'rfq' ? (
                                        <SelectField
                                            label={t('From Approved RFQ')}
                                            value={purchaseOrderForm.data.source_id}
                                            onChange={(v) => purchaseOrderForm.setData('source_id', v)}
                                            options={createTextileWorkflowSelectOptions(approvedRfqs)}
                                            includeEmpty
                                            emptyLabel={t('Select approved RFQ')}
                                            helperText={t('Purchase order is created from an approved or closed RFQ.')}
                                            disabled={approvedRfqs.length === 0}
                                            disabledReason={t('No approved RFQ found. Send an RFQ first.')}
                                            required
                                        />
                                    ) : (
                                        <SelectField
                                            label={t('From Approved Requisition')}
                                            value={purchaseOrderForm.data.source_id}
                                            onChange={(v) => purchaseOrderForm.setData('source_id', v)}
                                            options={createTextileWorkflowSelectOptions(approvedRequisitions)}
                                            includeEmpty
                                            emptyLabel={t('Select approved requisition')}
                                            helperText={t('Single flow enabled: purchase order is created from approved requisition.')}
                                            disabled={approvedRequisitions.length === 0}
                                            disabledReason={t('No approved requisition found. Approve a requisition first.')}
                                            required
                                        />
                                    )}
                                    <Button type="submit" disabled={purchaseOrderForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create PO')}</Button>
                                </form>
                                }
                                table={
                                    <TextileDataTableCard
                                        data={purchaseOrders}
                            columns={createTextileWorkflowColumns(t, {
                                actions: createTextileWorkflowActions([
                                    {
                                        statuses: textileActionableStatuses.draft,
                                        actions: [{ label: t('Send Proforma'), icon: Send, onClick: (row) => approvePurchaseOrder(row.id) }],
                                    },
                                    {
                                        statuses: ['approved', 'released', 'closed'],
                                        actions: [{ label: t('View Proforma'), icon: FileText, onClick: (row) => setViewingProforma(row as WorkflowDocument) }],
                                    },
                                ]),
                            })}
                                    emptyState={<NoRecordsFound icon={ShoppingCart} title={t('No purchase orders found')} description={t('Convert approved requisitions into purchase orders.')} />}
                                />
                                }
                            />
                        );

                case 'grns': return (
                    <TextileSection
                        formTitle={t('Create GRN (Goods Received Note)')}
                        formIcon={Truck}
                        form={
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    grnForm.post(route('textile.procurement.grns.store'), {
                                        onSuccess: () => grnForm.reset('purchase_order_id'),
                                    });
                                }}>
                                    <div className="col-span-2"><TextileFormErrors errors={grnForm.errors} /></div>
                                    <SelectField
                                        label={t('From Approved PO')}
                                        value={grnForm.data.purchase_order_id}
                                        onChange={(v) => grnForm.setData('purchase_order_id', v)}
                                        options={createTextileWorkflowSelectOptions(approvedPurchaseOrders)}
                                        includeEmpty
                                        emptyLabel={t('Select approved PO')}
                                        helperText={t('Only approved purchase orders are listed.')}
                                        disabled={approvedPurchaseOrders.length === 0}
                                        disabledReason={t('No approved PO found. Send proforma on a purchase order first.')}
                                        required
                                    />
                                    <Button type="submit" disabled={grnForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create GRN')}</Button>
                                </form>
                                }
                                table={
                                    <TextileDataTableCard
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
                                        actions: [
                                            { label: t('Sync Invoice'), icon: Plus, onClick: (row) => syncInvoiceFromGrn(row.id), when: (row) => !row.purchase_invoice_id },
                                            {
                                                label: t('View Invoice'),
                                                icon: Receipt,
                                                onClick: (row) => {
                                                    if (row.purchase_invoice_id && accessibleInvoiceIds.has(row.purchase_invoice_id)) {
                                                        router.get(route('purchase-invoices.show', row.purchase_invoice_id));
                                                    }
                                                },
                                                when: (row) => Boolean(row.purchase_invoice_id && accessibleInvoiceIds.has(row.purchase_invoice_id)),
                                            },
                                        ],
                                    },
                                ]),
                            })}
                                    emptyState={<NoRecordsFound icon={Truck} title={t('No GRNs found')} description={t('Create and release GRNs (Goods Received Notes) against approved purchase orders.')} />}
                                />
                                }
                            />
                        );

                case 'incoming-qc': return (
                    <TextileSection
                        formTitle={t('Create Incoming QC')}
                        formIcon={Plus}
                        form={
                                <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                    e.preventDefault();
                                    incomingQcForm.post(route('textile.procurement.incoming-qc.store'), {
                                        onSuccess: () => incomingQcForm.reset('grn_id'),
                                    });
                                }}>
                                    <div className="col-span-2"><TextileFormErrors errors={incomingQcForm.errors} /></div>
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
                                }
                                table={
                                    <TextileDataTableCard
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
                                    {
                                        statuses: ['approved', 'rejected', 'released', 'closed'],
                                        actions: [{ label: t('View QC'), icon: FileText, onClick: (row) => setViewingIncomingQc(row as WorkflowDocument) }],
                                    },
                                ]),
                            })}
                                    emptyState={<NoRecordsFound icon={Check} title={t('No incoming QC records found')} description={t('Create incoming QC entries from released GRNs.')} />}
                                />
                                }
                            />
                        );

                case 'supplier-claims': return (
                    <TextileSection
                        formTitle={t('Create Supplier Claim')}
                        formIcon={FileText}
                        form={
                            <form className="space-y-3" onSubmit={(e) => {
                                e.preventDefault();
                                supplierClaimForm.post(route('textile.procurement.supplier-claims.store'), {
                                    onSuccess: () => supplierClaimForm.reset('grn_id', 'claim_amount', 'claim_note'),
                                });
                            }}>
                                <TextileFormErrors errors={supplierClaimForm.errors} />
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
                                }
                                table={
                                    <TextileDataTableCard
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
                                                <>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => setViewingSupplierClaim(row)}>
                                                        <FileText className="mr-1 h-3.5 w-3.5" />
                                                        {t('View Claim')}
                                                    </Button>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => approveSupplierClaim(row.id)}>
                                                        <Check className="mr-1 h-3.5 w-3.5" />
                                                        {t('Approve')}
                                                    </Button>
                                                </>
                                            ) : null}
                                            {row.status === 'approved' ? (
                                                <>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => setViewingSupplierClaim(row)}>
                                                        <FileText className="mr-1 h-3.5 w-3.5" />
                                                        {t('View Claim')}
                                                    </Button>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => settleSupplierClaim(row.id)}>
                                                        <Check className="mr-1 h-3.5 w-3.5" />
                                                        {t('Settle')}
                                                    </Button>
                                                </>
                                            ) : null}
                                            {row.status !== 'draft' && row.status !== 'approved' ? (
                                                <Button type="button" variant="outline" size="sm" onClick={() => setViewingSupplierClaim(row)}>
                                                    <FileText className="mr-1 h-3.5 w-3.5" />
                                                    {t('View Claim')}
                                                </Button>
                                            ) : null}
                                        </div>
                                    ),
                                },
                            ]}
                                emptyState={<NoRecordsFound icon={FileText} title={t('No supplier claims found')} description={t('Create supplier claims from released GRNs for quality and settlement tracking.')} />}
                            />
                                }
                            />
                        );

                        case 'bills': return (
                            <TextileSection
                                table={
                                    <TextileDataTableCard
                                        data={purchaseInvoices}
                                        columns={[
                                            { key: 'invoice_number', header: t('Invoice #') },
                                            { key: 'vendor_name', header: t('Vendor') },
                                            { key: 'invoice_date', header: t('Invoice Date') },
                                            { key: 'due_date', header: t('Due Date') },
                                            { key: 'total_amount', header: t('Total') },
                                            { key: 'paid_amount', header: t('Paid') },
                                            { key: 'balance_amount', header: t('Balance') },
                                            { key: 'status', header: t('Status'), render: formatTextileLabel },
                                            {
                                                key: 'actions',
                                                header: t('Actions'),
                                                render: (_value: unknown, row: PurchaseInvoiceRecord) => (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => router.get(route('purchase-invoices.show', row.id))}
                                                    >
                                                        {t('Open')}
                                                    </Button>
                                                ),
                                            },
                                        ]}
                                        emptyState={<NoRecordsFound icon={Receipt} title={t('No purchase bills found')} description={t('Bills are auto-created when you sync invoices from GRNs.')} />}
                                    />
                                }
                            />
                        );

                        default: return null;
                    }
                }}
            </TextileWorkspace>
            <Dialog open={viewingRfq !== null} onOpenChange={(open) => !open && setViewingRfq(null)}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{t('Request for Quotation')}</DialogTitle>
                        <DialogDescription>
                            {viewingRfq?.document_number ?? '-'}
                        </DialogDescription>
                    </DialogHeader>
                    {viewingRfq ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {[
                                [t('Supplier'), viewingRfq.party_name || '-'],
                                [t('Status'), formatTextileLabel(viewingRfq.status)],
                                [t('Lot Reference'), viewingRfq.lot_reference || '-'],
                                [t('Quantity'), `${viewingRfq.quantity} ${viewingRfq.unit || ''}`.trim()],
                            ].map(([label, value]) => (
                                <div key={label} className="border-b border-border pb-3">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p className="mt-1 text-sm font-medium">{value}</p>
                                </div>
                            ))}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
            <Dialog open={viewingProforma !== null} onOpenChange={(open) => !open && setViewingProforma(null)}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{t('Purchase Proforma')}</DialogTitle>
                        <DialogDescription>
                            {viewingProforma?.document_number ?? '-'}
                        </DialogDescription>
                    </DialogHeader>
                    {viewingProforma ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {[
                                [t('Supplier'), viewingProforma.party_name || '-'],
                                [t('Status'), formatTextileLabel(viewingProforma.status)],
                                [t('Lot Reference'), viewingProforma.lot_reference || '-'],
                                [t('Quantity'), `${viewingProforma.quantity} ${viewingProforma.unit || ''}`.trim()],
                            ].map(([label, value]) => (
                                <div key={label} className="border-b border-border pb-3">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p className="mt-1 text-sm font-medium">{value}</p>
                                </div>
                            ))}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
            <Dialog open={viewingIncomingQc !== null} onOpenChange={(open) => !open && setViewingIncomingQc(null)}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{t('Incoming Quality Check')}</DialogTitle>
                        <DialogDescription>{viewingIncomingQc?.document_number ?? '-'}</DialogDescription>
                    </DialogHeader>
                    {viewingIncomingQc ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {[
                                [t('Supplier'), viewingIncomingQc.party_name || '-'],
                                [t('Decision'), formatTextileLabel(viewingIncomingQc.status)],
                                [t('Lot Reference'), viewingIncomingQc.lot_reference || '-'],
                                [t('Inspected Quantity'), `${viewingIncomingQc.quantity} ${viewingIncomingQc.unit || ''}`.trim()],
                            ].map(([label, value]) => (
                                <div key={label} className="border-b border-border pb-3">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p className="mt-1 text-sm font-medium">{value}</p>
                                </div>
                            ))}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
            <Dialog open={viewingSupplierClaim !== null} onOpenChange={(open) => !open && setViewingSupplierClaim(null)}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{t('Supplier Claim')}</DialogTitle>
                        <DialogDescription>{viewingSupplierClaim?.document_number ?? '-'}</DialogDescription>
                    </DialogHeader>
                    {viewingSupplierClaim ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {[
                                [t('Supplier'), viewingSupplierClaim.party_name || '-'],
                                [t('Status'), formatTextileLabel(viewingSupplierClaim.status)],
                                [t('Lot Reference'), viewingSupplierClaim.lot_reference || '-'],
                                [t('Claim Type'), formatTextileLabel(String(viewingSupplierClaim.metadata?.claim_type ?? '-'))],
                                [t('Claim Amount'), String(viewingSupplierClaim.metadata?.claim_amount ?? '-')],
                                [t('Resolution'), formatTextileLabel(String(viewingSupplierClaim.metadata?.resolution_type ?? '-'))],
                            ].map(([label, value]) => (
                                <div key={label} className="border-b border-border pb-3">
                                    <p className="text-xs text-muted-foreground">{label}</p>
                                    <p className="mt-1 text-sm font-medium">{value}</p>
                                </div>
                            ))}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>
            <ConfirmationDialog
                open={deletingRequisition !== null}
                onOpenChange={(open) => !open && setDeletingRequisition(null)}
                title={t('Delete Purchase Requisition')}
                message={t('Delete this accidental draft requisition? This cannot be undone.')}
                confirmText={t('Delete')}
                onConfirm={deleteRequisition}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}
