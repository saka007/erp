import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { RefreshCw, Plus, CheckCircle2 } from 'lucide-react';
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
    outwards,
    batches,
    inwards,
    reconciliations,
}: {
    outwards: WorkflowDocument[];
    batches: WorkflowDocument[];
    inwards: WorkflowDocument[];
    reconciliations: WorkflowDocument[];
}) {
    const { t } = useTranslation();

    const outwardForm = useForm({
        source_reference_type: 'processing_order',
        source_reference_id: '',
        source_action: 'job_work_issue',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const outwardReleaseForm = useForm({ outward_id: '' });
    const batchForm = useForm({ outward_id: '' });
    const batchReleaseForm = useForm({ batch_id: '' });
    const inwardForm = useForm({ batch_id: '', quantity: '', unit: 'mtr' });
    const inwardFinalizeForm = useForm({ inward_id: '', decision: 'pass' });
    const reconcileForm = useForm({ outward_id: '', inward_id: '', notes: '' });

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Processing') }]} pageTitle={t('Textile Processing')}>
            <Head title={t('Textile Processing')} />

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <RefreshCw className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Job Work Outward')}</h2>
                        </div>

                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                outwardForm.post(route('textile.processing.outward.store'), {
                                    onSuccess: () => outwardForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}
                        >
                            <Field label={t('Source Type')} value={outwardForm.data.source_reference_type} onChange={(value) => outwardForm.setData('source_reference_type', value)} required />
                            <Field label={t('Source ID')} type="number" value={outwardForm.data.source_reference_id} onChange={(value) => outwardForm.setData('source_reference_id', value)} required />
                            <Field label={t('Source Action')} value={outwardForm.data.source_action} onChange={(value) => outwardForm.setData('source_action', value)} required />
                            <Field label={t('Processor/Party')} value={outwardForm.data.party_name} onChange={(value) => outwardForm.setData('party_name', value)} />
                            <Field label={t('Lot Reference')} value={outwardForm.data.lot_reference} onChange={(value) => outwardForm.setData('lot_reference', value)} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={outwardForm.data.quantity} onChange={(value) => outwardForm.setData('quantity', value)} required />
                                <Field label={t('Unit')} value={outwardForm.data.unit} onChange={(value) => outwardForm.setData('unit', value)} />
                            </div>
                            <Button type="submit" disabled={outwardForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Outward')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                outwardReleaseForm.post(route('textile.processing.outward.release'), {
                                    onSuccess: () => outwardReleaseForm.reset('outward_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Release Outward ID')} value={outwardReleaseForm.data.outward_id} onChange={(value) => outwardReleaseForm.setData('outward_id', value)} options={outwards.filter((row) => ['draft', 'approved'].includes(row.status)).map((row) => String(row.id))} includeEmpty emptyLabel={t('Select releasable outward')} required />
                            <Button type="submit" variant="outline" disabled={outwardReleaseForm.processing} className="self-end">
                                <CheckCircle2 className="mr-2 h-4 w-4" />{t('Release')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <RefreshCw className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Processing Batch')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                batchForm.post(route('textile.processing.batches.store'), {
                                    onSuccess: () => batchForm.reset('outward_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Create Batch from Released Outward ID')} value={batchForm.data.outward_id} onChange={(value) => batchForm.setData('outward_id', value)} options={outwards.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released outward')} required />
                            <Button type="submit" disabled={batchForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Batch')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                batchReleaseForm.post(route('textile.processing.batches.release'), {
                                    onSuccess: () => batchReleaseForm.reset('batch_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Release Batch ID')} value={batchReleaseForm.data.batch_id} onChange={(value) => batchReleaseForm.setData('batch_id', value)} options={batches.filter((row) => ['draft', 'approved'].includes(row.status)).map((row) => String(row.id))} includeEmpty emptyLabel={t('Select releasable batch')} required />
                            <Button type="submit" variant="outline" disabled={batchReleaseForm.processing} className="self-end">
                                <CheckCircle2 className="mr-2 h-4 w-4" />{t('Release')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <RefreshCw className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Job Work Inward')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-4 gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                inwardForm.post(route('textile.processing.inward.store'), {
                                    onSuccess: () => inwardForm.reset('batch_id', 'quantity'),
                                });
                            }}
                        >
                            <SelectField label={t('Batch ID')} value={inwardForm.data.batch_id} onChange={(value) => inwardForm.setData('batch_id', value)} options={batches.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released batch')} required />
                            <Field label={t('Quantity')} type="number" value={inwardForm.data.quantity} onChange={(value) => inwardForm.setData('quantity', value)} />
                            <Field label={t('Unit')} value={inwardForm.data.unit} onChange={(value) => inwardForm.setData('unit', value)} />
                            <Button type="submit" disabled={inwardForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Inward')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-3 gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                inwardFinalizeForm.post(route('textile.processing.inward.finalize'), {
                                    onSuccess: () => inwardFinalizeForm.reset('inward_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Finalize Inward ID')} value={inwardFinalizeForm.data.inward_id} onChange={(value) => inwardFinalizeForm.setData('inward_id', value)} options={inwards.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft inward')} required />
                            <SelectField label={t('Decision')} value={inwardFinalizeForm.data.decision} onChange={(value) => inwardFinalizeForm.setData('decision', value as 'pass' | 'fail')} options={['pass', 'fail']} required />
                            <Button type="submit" variant="outline" disabled={inwardFinalizeForm.processing} className="self-end">
                                <CheckCircle2 className="mr-2 h-4 w-4" />{t('Finalize')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <CheckCircle2 className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Reconciliation')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-3 gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                reconcileForm.post(route('textile.processing.reconcile'), {
                                    onSuccess: () => reconcileForm.reset('outward_id', 'inward_id', 'notes'),
                                });
                            }}
                        >
                            <SelectField label={t('Outward ID')} value={reconcileForm.data.outward_id} onChange={(value) => reconcileForm.setData('outward_id', value)} options={outwards.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released outward')} required />
                            <SelectField label={t('Inward ID')} value={reconcileForm.data.inward_id} onChange={(value) => reconcileForm.setData('inward_id', value)} options={inwards.filter((row) => row.status === 'approved').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select approved inward')} required />
                            <Field label={t('Notes')} value={reconcileForm.data.notes} onChange={(value) => reconcileForm.setData('notes', value)} />
                            <Button type="submit" disabled={reconcileForm.processing} className="col-span-3">
                                <CheckCircle2 className="mr-2 h-4 w-4" />{t('Reconcile')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card><CardContent className="p-0"><DataTable data={outwards} columns={columns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No outward records found')} description={t('Create job-work outward documents to start processing custody flow.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={batches} columns={columns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No processing batches found')} description={t('Create processing batches from released outwards.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={inwards} columns={columns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No inward records found')} description={t('Create and finalize inward records for returned processed stock.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={reconciliations} columns={columns(t)} emptyState={<NoRecordsFound icon={CheckCircle2} title={t('No reconciliations found')} description={t('Reconciliation entries appear after outward-inward matching.')} />} /></CardContent></Card>
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
