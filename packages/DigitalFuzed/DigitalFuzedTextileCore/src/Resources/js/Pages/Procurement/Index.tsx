import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShoppingCart, Plus, Check, Truck } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NoRecordsFound from '@/components/no-records-found';

interface WorkflowDocument {
    id: number;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
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

    const requisitionForm = useForm({
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'kg',
    });

    const requisitionApproveForm = useForm({ requisition_id: '' });
    const purchaseOrderForm = useForm({ requisition_id: '' });
    const purchaseOrderApproveForm = useForm({ purchase_order_id: '' });
    const grnForm = useForm({ purchase_order_id: '' });
    const grnReleaseForm = useForm({ grn_id: '' });
    const incomingQcForm = useForm({ grn_id: '' });
    const incomingQcFinalizeForm = useForm({ incoming_qc_id: '', decision: 'pass' });

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Procurement') }]} pageTitle={t('Textile Procurement')}>
            <Head title={t('Textile Procurement')} />

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <ShoppingCart className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Purchase Requisition')}</h2>
                        </div>

                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                requisitionForm.post(route('textile.procurement.requisitions.store'), {
                                    onSuccess: () => requisitionForm.reset('party_name', 'lot_reference', 'quantity'),
                                });
                            }}
                        >
                            <Field label={t('Supplier/Party')} value={requisitionForm.data.party_name} onChange={(v) => requisitionForm.setData('party_name', v)} />
                            <Field label={t('Lot Reference')} value={requisitionForm.data.lot_reference} onChange={(v) => requisitionForm.setData('lot_reference', v)} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={requisitionForm.data.quantity} onChange={(v) => requisitionForm.setData('quantity', v)} required />
                                <Field label={t('Unit')} value={requisitionForm.data.unit} onChange={(v) => requisitionForm.setData('unit', v)} />
                            </div>
                            <Button type="submit" disabled={requisitionForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Requisition')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                requisitionApproveForm.post(route('textile.procurement.requisitions.approve'), {
                                    onSuccess: () => requisitionApproveForm.reset('requisition_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Approve Requisition ID')} value={requisitionApproveForm.data.requisition_id} onChange={(v) => requisitionApproveForm.setData('requisition_id', v)} options={requisitions.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft requisition')} required />
                            <Button type="submit" variant="outline" disabled={requisitionApproveForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Approve')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <ShoppingCart className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Purchase Order')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                purchaseOrderForm.post(route('textile.procurement.purchase-orders.store'), {
                                    onSuccess: () => purchaseOrderForm.reset('requisition_id'),
                                });
                            }}
                        >
                            <SelectField label={t('From Approved Requisition ID')} value={purchaseOrderForm.data.requisition_id} onChange={(v) => purchaseOrderForm.setData('requisition_id', v)} options={requisitions.filter((row) => row.status === 'approved').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select approved requisition')} required />
                            <Button type="submit" disabled={purchaseOrderForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create PO')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                purchaseOrderApproveForm.post(route('textile.procurement.purchase-orders.approve'), {
                                    onSuccess: () => purchaseOrderApproveForm.reset('purchase_order_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Approve Purchase Order ID')} value={purchaseOrderApproveForm.data.purchase_order_id} onChange={(v) => purchaseOrderApproveForm.setData('purchase_order_id', v)} options={purchaseOrders.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft PO')} required />
                            <Button type="submit" variant="outline" disabled={purchaseOrderApproveForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Approve')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <Truck className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('GRN')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                grnForm.post(route('textile.procurement.grns.store'), {
                                    onSuccess: () => grnForm.reset('purchase_order_id'),
                                });
                            }}
                        >
                            <SelectField label={t('From Approved PO ID')} value={grnForm.data.purchase_order_id} onChange={(v) => grnForm.setData('purchase_order_id', v)} options={purchaseOrders.filter((row) => row.status === 'approved').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select approved PO')} required />
                            <Button type="submit" disabled={grnForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create GRN')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                grnReleaseForm.post(route('textile.procurement.grns.release'), {
                                    onSuccess: () => grnReleaseForm.reset('grn_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Release GRN ID')} value={grnReleaseForm.data.grn_id} onChange={(v) => grnReleaseForm.setData('grn_id', v)} options={grns.filter((row) => ['draft', 'approved'].includes(row.status)).map((row) => String(row.id))} includeEmpty emptyLabel={t('Select releasable GRN')} required />
                            <Button type="submit" variant="outline" disabled={grnReleaseForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Release')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <Check className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Incoming QC')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                incomingQcForm.post(route('textile.procurement.incoming-qc.store'), {
                                    onSuccess: () => incomingQcForm.reset('grn_id'),
                                });
                            }}
                        >
                            <SelectField label={t('From Released GRN ID')} value={incomingQcForm.data.grn_id} onChange={(v) => incomingQcForm.setData('grn_id', v)} options={grns.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released GRN')} required />
                            <Button type="submit" disabled={incomingQcForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Incoming QC')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-3 gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                incomingQcFinalizeForm.post(route('textile.procurement.incoming-qc.finalize'), {
                                    onSuccess: () => incomingQcFinalizeForm.reset('incoming_qc_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Finalize Incoming QC ID')} value={incomingQcFinalizeForm.data.incoming_qc_id} onChange={(v) => incomingQcFinalizeForm.setData('incoming_qc_id', v)} options={incomingQcs.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft incoming QC')} required />
                            <SelectField label={t('Decision')} value={incomingQcFinalizeForm.data.decision} onChange={(v) => incomingQcFinalizeForm.setData('decision', v as 'pass' | 'fail')} options={['pass', 'fail']} required />
                            <Button type="submit" variant="outline" disabled={incomingQcFinalizeForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Finalize')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card><CardContent className="p-0"><DataTable data={requisitions} columns={columns(t)} emptyState={<NoRecordsFound icon={ShoppingCart} title={t('No requisitions found')} description={t('Create procurement requisitions to begin material planning.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={purchaseOrders} columns={columns(t)} emptyState={<NoRecordsFound icon={ShoppingCart} title={t('No purchase orders found')} description={t('Convert approved requisitions into purchase orders.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={grns} columns={columns(t)} emptyState={<NoRecordsFound icon={Truck} title={t('No GRNs found')} description={t('Create and release GRNs against approved purchase orders.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={incomingQcs} columns={columns(t)} emptyState={<NoRecordsFound icon={Check} title={t('No incoming QC records found')} description={t('Create incoming QC entries from released GRNs.')} />} /></CardContent></Card>
            </div>
        </AuthenticatedLayout>
    );
}

function columns(t: (key: string) => string) {
    return [
        { key: 'id', header: t('ID') },
        { key: 'document_number', header: t('Number') },
        { key: 'party_name', header: t('Party'), render: optional },
        { key: 'lot_reference', header: t('Lot'), render: optional },
        { key: 'quantity', header: t('Qty') },
        { key: 'unit', header: t('Unit'), render: optional },
        { key: 'status', header: t('Status') },
    ];
}

function Field({ label, value, onChange, type = 'text', required = false }: { label: string; value: string; onChange: (value: string) => void; type?: string; required?: boolean }) {
    return (
        <div>
            <Label>{label}</Label>
            <Input type={type} value={value} required={required} onChange={(event) => onChange(event.target.value)} />
        </div>
    );
}

function SelectField({ label, value, onChange, options, required = false, includeEmpty = false, emptyLabel = 'Select' }: { label: string; value: string; onChange: (value: string) => void; options: string[]; required?: boolean; includeEmpty?: boolean; emptyLabel?: string }) {
    return (
        <div>
            <Label>{label}</Label>
            <select className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={value} required={required} onChange={(event) => onChange(event.target.value)}>
                {includeEmpty && <option value="">{emptyLabel}</option>}
                {options.map((option) => (
                    <option key={option} value={option}>{option}</option>
                ))}
            </select>
        </div>
    );
}

function optional(value: string | null) {
    return value || '-';
}
