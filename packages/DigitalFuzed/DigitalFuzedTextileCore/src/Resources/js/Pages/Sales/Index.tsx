import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Truck, Plus, Check, ClipboardCheck } from 'lucide-react';
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
    salesOrders,
    allocations,
    dispatches,
    challans,
    pods,
}: {
    salesOrders: WorkflowDocument[];
    allocations: WorkflowDocument[];
    dispatches: WorkflowDocument[];
    challans: WorkflowDocument[];
    pods: WorkflowDocument[];
}) {
    const { t } = useTranslation();

    const salesOrderForm = useForm({
        source_reference_type: 'sales_quotation',
        source_reference_id: '',
        source_action: 'convert',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const salesOrderApproveForm = useForm({ sales_order_id: '' });
    const allocationForm = useForm({ sales_order_id: '' });
    const allocationReleaseForm = useForm({ allocation_id: '' });
    const dispatchForm = useForm({ allocation_id: '' });
    const dispatchReleaseForm = useForm({ dispatch_id: '' });
    const challanForm = useForm({ dispatch_id: '' });
    const podForm = useForm({ challan_id: '' });

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Sales') }]} pageTitle={t('Textile Sales')}>
            <Head title={t('Textile Sales')} />

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <Truck className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Sales Order')}</h2>
                        </div>

                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                salesOrderForm.post(route('textile.sales.orders.store'), {
                                    onSuccess: () => salesOrderForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}
                        >
                            <Field label={t('Source Type')} value={salesOrderForm.data.source_reference_type} onChange={(v) => salesOrderForm.setData('source_reference_type', v)} required />
                            <Field label={t('Source ID')} type="number" value={salesOrderForm.data.source_reference_id} onChange={(v) => salesOrderForm.setData('source_reference_id', v)} required />
                            <Field label={t('Source Action')} value={salesOrderForm.data.source_action} onChange={(v) => salesOrderForm.setData('source_action', v)} required />
                            <Field label={t('Customer/Party')} value={salesOrderForm.data.party_name} onChange={(v) => salesOrderForm.setData('party_name', v)} />
                            <Field label={t('Lot Reference')} value={salesOrderForm.data.lot_reference} onChange={(v) => salesOrderForm.setData('lot_reference', v)} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={salesOrderForm.data.quantity} onChange={(v) => salesOrderForm.setData('quantity', v)} required />
                                <Field label={t('Unit')} value={salesOrderForm.data.unit} onChange={(v) => salesOrderForm.setData('unit', v)} />
                            </div>
                            <Button type="submit" disabled={salesOrderForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Sales Order')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                salesOrderApproveForm.post(route('textile.sales.orders.approve'), {
                                    onSuccess: () => salesOrderApproveForm.reset('sales_order_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Approve Sales Order ID')} value={salesOrderApproveForm.data.sales_order_id} onChange={(v) => salesOrderApproveForm.setData('sales_order_id', v)} options={salesOrders.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft sales order')} required />
                            <Button type="submit" variant="outline" disabled={salesOrderApproveForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Approve')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Allocation and Dispatch')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                allocationForm.post(route('textile.sales.allocations.store'), {
                                    onSuccess: () => allocationForm.reset('sales_order_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Create Allocation from Approved SO ID')} value={allocationForm.data.sales_order_id} onChange={(v) => allocationForm.setData('sales_order_id', v)} options={salesOrders.filter((row) => row.status === 'approved').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select approved sales order')} required />
                            <Button type="submit" disabled={allocationForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Allocation')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                allocationReleaseForm.post(route('textile.sales.allocations.release'), {
                                    onSuccess: () => allocationReleaseForm.reset('allocation_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Release Allocation ID')} value={allocationReleaseForm.data.allocation_id} onChange={(v) => allocationReleaseForm.setData('allocation_id', v)} options={allocations.filter((row) => ['draft', 'approved'].includes(row.status)).map((row) => String(row.id))} includeEmpty emptyLabel={t('Select releasable allocation')} required />
                            <Button type="submit" variant="outline" disabled={allocationReleaseForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Release')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                dispatchForm.post(route('textile.sales.dispatches.store'), {
                                    onSuccess: () => dispatchForm.reset('allocation_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Create Dispatch from Released Allocation ID')} value={dispatchForm.data.allocation_id} onChange={(v) => dispatchForm.setData('allocation_id', v)} options={allocations.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released allocation')} required />
                            <Button type="submit" disabled={dispatchForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Dispatch')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                dispatchReleaseForm.post(route('textile.sales.dispatches.release'), {
                                    onSuccess: () => dispatchReleaseForm.reset('dispatch_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Release Dispatch ID')} value={dispatchReleaseForm.data.dispatch_id} onChange={(v) => dispatchReleaseForm.setData('dispatch_id', v)} options={dispatches.filter((row) => ['draft', 'approved'].includes(row.status)).map((row) => String(row.id))} includeEmpty emptyLabel={t('Select releasable dispatch')} required />
                            <Button type="submit" variant="outline" disabled={dispatchReleaseForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Release')}</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="xl:col-span-2">
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Challan and POD')}</h2>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            <form
                                className="grid grid-cols-[1fr_auto] gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    challanForm.post(route('textile.sales.challans.store'), {
                                        onSuccess: () => challanForm.reset('dispatch_id'),
                                    });
                                }}
                            >
                                <SelectField label={t('Create Challan from Released Dispatch ID')} value={challanForm.data.dispatch_id} onChange={(v) => challanForm.setData('dispatch_id', v)} options={dispatches.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released dispatch')} required />
                                <Button type="submit" disabled={challanForm.processing} className="self-end">
                                    <Plus className="mr-2 h-4 w-4" />{t('Create Challan')}
                                </Button>
                            </form>

                            <form
                                className="grid grid-cols-[1fr_auto] gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    podForm.post(route('textile.sales.challans.pod'), {
                                        onSuccess: () => podForm.reset('challan_id'),
                                    });
                                }}
                            >
                                <SelectField label={t('Mark POD for Challan ID')} value={podForm.data.challan_id} onChange={(v) => podForm.setData('challan_id', v)} options={challans.filter((row) => ['draft', 'approved', 'released'].includes(row.status)).map((row) => String(row.id))} includeEmpty emptyLabel={t('Select challan')} required />
                                <Button type="submit" variant="outline" disabled={podForm.processing} className="self-end">
                                    <Check className="mr-2 h-4 w-4" />{t('Mark POD')}
                                </Button>
                            </form>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card><CardContent className="p-0"><DataTable data={salesOrders} columns={columns(t)} emptyState={<NoRecordsFound icon={Truck} title={t('No sales orders found')} description={t('Create sales orders from approved commercial references.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={allocations} columns={columns(t)} emptyState={<NoRecordsFound icon={ClipboardCheck} title={t('No allocations found')} description={t('Create allocations from approved sales orders.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={dispatches} columns={columns(t)} emptyState={<NoRecordsFound icon={Truck} title={t('No dispatches found')} description={t('Create dispatches from released allocations.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={challans} columns={columns(t)} emptyState={<NoRecordsFound icon={ClipboardCheck} title={t('No challans found')} description={t('Create challans for released dispatches.')} />} /></CardContent></Card>
                <Card className="xl:col-span-2"><CardContent className="p-0"><DataTable data={pods} columns={columns(t)} emptyState={<NoRecordsFound icon={Check} title={t('No POD records found')} description={t('Mark POD to complete challan lifecycle.')} />} /></CardContent></Card>
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
