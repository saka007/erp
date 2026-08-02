import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Factory, Plus, Check } from 'lucide-react';
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
    beams,
    productionBatches,
    weavingOutputs,
    wastes,
    reworks,
}: {
    beams: WorkflowDocument[];
    productionBatches: WorkflowDocument[];
    weavingOutputs: WorkflowDocument[];
    wastes: WorkflowDocument[];
    reworks: WorkflowDocument[];
}) {
    const { t } = useTranslation();

    const beamForm = useForm({
        source_reference_type: 'sales_order',
        source_reference_id: '',
        source_action: 'beam_prepare',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const beamApproveForm = useForm({ beam_id: '' });
    const batchForm = useForm({ beam_id: '' });
    const batchReleaseForm = useForm({ batch_id: '' });
    const weavingOutputForm = useForm({ batch_id: '', quantity: '', unit: 'mtr' });
    const wasteForm = useForm({ batch_id: '', quantity: '', unit: 'mtr' });
    const reworkForm = useForm({ weaving_output_id: '', quantity: '', unit: 'mtr' });

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Manufacturing') }]} pageTitle={t('Textile Manufacturing')}>
            <Head title={t('Textile Manufacturing')} />

            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <Factory className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Beam and Production Batch')}</h2>
                        </div>

                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                beamForm.post(route('textile.manufacturing.beams.store'), {
                                    onSuccess: () => beamForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}
                        >
                            <Field label={t('Source Type')} value={beamForm.data.source_reference_type} onChange={(v) => beamForm.setData('source_reference_type', v)} required />
                            <Field label={t('Source ID')} type="number" value={beamForm.data.source_reference_id} onChange={(v) => beamForm.setData('source_reference_id', v)} required />
                            <Field label={t('Source Action')} value={beamForm.data.source_action} onChange={(v) => beamForm.setData('source_action', v)} required />
                            <Field label={t('Party')} value={beamForm.data.party_name} onChange={(v) => beamForm.setData('party_name', v)} />
                            <Field label={t('Lot Reference')} value={beamForm.data.lot_reference} onChange={(v) => beamForm.setData('lot_reference', v)} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={beamForm.data.quantity} onChange={(v) => beamForm.setData('quantity', v)} required />
                                <Field label={t('Unit')} value={beamForm.data.unit} onChange={(v) => beamForm.setData('unit', v)} />
                            </div>
                            <Button type="submit" disabled={beamForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Beam')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                beamApproveForm.post(route('textile.manufacturing.beams.approve'), {
                                    onSuccess: () => beamApproveForm.reset('beam_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Approve Beam ID')} value={beamApproveForm.data.beam_id} onChange={(v) => beamApproveForm.setData('beam_id', v)} options={beams.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft beam')} required />
                            <Button type="submit" variant="outline" disabled={beamApproveForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Approve')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                batchForm.post(route('textile.manufacturing.batches.store'), {
                                    onSuccess: () => batchForm.reset('beam_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Create Batch from Approved Beam ID')} value={batchForm.data.beam_id} onChange={(v) => batchForm.setData('beam_id', v)} options={beams.filter((row) => row.status === 'approved').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select approved beam')} required />
                            <Button type="submit" disabled={batchForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Batch')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                batchReleaseForm.post(route('textile.manufacturing.batches.release'), {
                                    onSuccess: () => batchReleaseForm.reset('batch_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Release Batch ID')} value={batchReleaseForm.data.batch_id} onChange={(v) => batchReleaseForm.setData('batch_id', v)} options={productionBatches.filter((row) => ['draft', 'approved'].includes(row.status)).map((row) => String(row.id))} includeEmpty emptyLabel={t('Select releasable batch')} required />
                            <Button type="submit" variant="outline" disabled={batchReleaseForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Release')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <Factory className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Output, Waste, Rework')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-4 gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                weavingOutputForm.post(route('textile.manufacturing.weaving-output.store'), {
                                    onSuccess: () => weavingOutputForm.reset('batch_id', 'quantity'),
                                });
                            }}
                        >
                            <SelectField label={t('Batch ID')} value={weavingOutputForm.data.batch_id} onChange={(v) => weavingOutputForm.setData('batch_id', v)} options={productionBatches.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released batch')} required />
                            <Field label={t('Output Qty')} type="number" value={weavingOutputForm.data.quantity} onChange={(v) => weavingOutputForm.setData('quantity', v)} />
                            <Field label={t('Unit')} value={weavingOutputForm.data.unit} onChange={(v) => weavingOutputForm.setData('unit', v)} />
                            <Button type="submit" disabled={weavingOutputForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Record Output')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-4 gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                wasteForm.post(route('textile.manufacturing.waste.store'), {
                                    onSuccess: () => wasteForm.reset('batch_id', 'quantity'),
                                });
                            }}
                        >
                            <SelectField label={t('Batch ID')} value={wasteForm.data.batch_id} onChange={(v) => wasteForm.setData('batch_id', v)} options={productionBatches.filter((row) => row.status === 'released').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select released batch')} required />
                            <Field label={t('Waste Qty')} type="number" value={wasteForm.data.quantity} onChange={(v) => wasteForm.setData('quantity', v)} required />
                            <Field label={t('Unit')} value={wasteForm.data.unit} onChange={(v) => wasteForm.setData('unit', v)} />
                            <Button type="submit" variant="outline" disabled={wasteForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Record Waste')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-4 gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                reworkForm.post(route('textile.manufacturing.rework.store'), {
                                    onSuccess: () => reworkForm.reset('weaving_output_id', 'quantity'),
                                });
                            }}
                        >
                            <SelectField label={t('Weaving Output ID')} value={reworkForm.data.weaving_output_id} onChange={(v) => reworkForm.setData('weaving_output_id', v)} options={weavingOutputs.map((row) => String(row.id))} includeEmpty emptyLabel={t('Select weaving output')} required />
                            <Field label={t('Rework Qty')} type="number" value={reworkForm.data.quantity} onChange={(v) => reworkForm.setData('quantity', v)} required />
                            <Field label={t('Unit')} value={reworkForm.data.unit} onChange={(v) => reworkForm.setData('unit', v)} />
                            <Button type="submit" variant="outline" disabled={reworkForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Record Rework')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card><CardContent className="p-0"><DataTable data={beams} columns={columns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No beams found')} description={t('Create beams from approved operational sources.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={productionBatches} columns={columns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No production batches found')} description={t('Create batches from approved beams.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={weavingOutputs} columns={columns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No weaving output found')} description={t('Record weaving output from released batches.')} />} /></CardContent></Card>
                <Card><CardContent className="p-0"><DataTable data={wastes} columns={columns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No waste records found')} description={t('Record waste from released batches.')} />} /></CardContent></Card>
                <Card className="xl:col-span-2"><CardContent className="p-0"><DataTable data={reworks} columns={columns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No rework records found')} description={t('Record rework linked to weaving output.')} />} /></CardContent></Card>
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
