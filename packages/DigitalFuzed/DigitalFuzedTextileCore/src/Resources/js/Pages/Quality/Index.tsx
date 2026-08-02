import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShieldCheck, Plus, Check } from 'lucide-react';
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
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
}

interface TextileLot {
    id: number;
    lot_reference: string;
    status: string;
}

export default function Index({ inspections, holds, lots }: { inspections: WorkflowDocument[]; holds: WorkflowDocument[]; lots: TextileLot[] }) {
    const { t } = useTranslation();

    const inspectionForm = useForm({
        source_reference_type: '',
        source_reference_id: '',
        source_action: '',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const inspectionFinalizeForm = useForm({ inspection_id: '', decision: 'pass' });
    const holdForm = useForm({ lot_reference: '', reason: '' });
    const releaseForm = useForm({ lot_reference: '', reason: '' });

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Quality') }]} pageTitle={t('Textile Quality')}>
            <Head title={t('Textile Quality')} />

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Inspection')}</h2>
                        </div>

                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                inspectionForm.post(route('textile.quality.inspections.store'), {
                                    onSuccess: () => inspectionForm.reset('source_reference_type', 'source_reference_id', 'source_action', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}
                        >
                            <Field label={t('Source Type')} value={inspectionForm.data.source_reference_type} onChange={(v) => inspectionForm.setData('source_reference_type', v)} />
                            <Field label={t('Source ID')} type="number" value={inspectionForm.data.source_reference_id} onChange={(v) => inspectionForm.setData('source_reference_id', v)} />
                            <Field label={t('Source Action')} value={inspectionForm.data.source_action} onChange={(v) => inspectionForm.setData('source_action', v)} />
                            <Field label={t('Party')} value={inspectionForm.data.party_name} onChange={(v) => inspectionForm.setData('party_name', v)} />
                            <Field label={t('Lot Reference')} value={inspectionForm.data.lot_reference} onChange={(v) => inspectionForm.setData('lot_reference', v)} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={inspectionForm.data.quantity} onChange={(v) => inspectionForm.setData('quantity', v)} required />
                                <Field label={t('Unit')} value={inspectionForm.data.unit} onChange={(v) => inspectionForm.setData('unit', v)} />
                            </div>
                            <Button type="submit" disabled={inspectionForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Inspection')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-3 gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                inspectionFinalizeForm.post(route('textile.quality.inspections.finalize'), {
                                    onSuccess: () => inspectionFinalizeForm.reset('inspection_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Inspection ID')} value={inspectionFinalizeForm.data.inspection_id} onChange={(v) => inspectionFinalizeForm.setData('inspection_id', v)} options={inspections.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft inspection')} required />
                            <SelectField label={t('Decision')} value={inspectionFinalizeForm.data.decision} onChange={(v) => inspectionFinalizeForm.setData('decision', v as 'pass' | 'fail')} options={['pass', 'fail']} required />
                            <Button type="submit" variant="outline" disabled={inspectionFinalizeForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Finalize')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Hold and Release')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-[1fr_1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                holdForm.post(route('textile.quality.lots.hold'), {
                                    onSuccess: () => holdForm.reset('reason'),
                                });
                            }}
                        >
                            <SelectField label={t('Lot Reference')} value={holdForm.data.lot_reference} onChange={(v) => holdForm.setData('lot_reference', v)} options={lots.map((lot) => lot.lot_reference)} includeEmpty emptyLabel={t('Select lot')} required />
                            <Field label={t('Hold Reason')} value={holdForm.data.reason} onChange={(v) => holdForm.setData('reason', v)} />
                            <Button type="submit" variant="outline" disabled={holdForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Apply Hold')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                releaseForm.post(route('textile.quality.lots.release'), {
                                    onSuccess: () => releaseForm.reset('reason'),
                                });
                            }}
                        >
                            <SelectField label={t('Lot Reference')} value={releaseForm.data.lot_reference} onChange={(v) => releaseForm.setData('lot_reference', v)} options={lots.map((lot) => lot.lot_reference)} includeEmpty emptyLabel={t('Select lot')} required />
                            <Field label={t('Release Reason')} value={releaseForm.data.reason} onChange={(v) => releaseForm.setData('reason', v)} />
                            <Button type="submit" disabled={releaseForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Release Lot')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card><CardContent className="p-0"><DataTable data={inspections} columns={columns(t)} emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No inspections found')} description={t('Create inspection records for lot-level quality checks.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={holds} columns={columns(t)} emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No hold/release records found')} description={t('Hold and release events will appear here.')} />} /></CardContent></Card>
            </div>
        </AuthenticatedLayout>
    );
}

function columns(t: (key: string) => string) {
    return [
        { key: 'id', header: t('ID') },
        { key: 'document_number', header: t('Number') },
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
