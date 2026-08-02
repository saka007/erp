import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShieldCheck, Plus, Check } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { buildUnitOptions, textileSourceTypeOptions } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';

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

export default function Index({ inspections, holds, lots, sourceTypeOptions, unitOptions }: { inspections: WorkflowDocument[]; holds: WorkflowDocument[]; lots: TextileLot[]; sourceTypeOptions: string[]; unitOptions: string[] }) {
    const { t } = useTranslation();
    const resolvedSourceTypeOptions = sourceTypeOptions.length > 0
        ? sourceTypeOptions.map((value) => ({ value, label: value }))
        : textileSourceTypeOptions;
    const resolvedUnitOptions = buildUnitOptions(unitOptions);

    const inspectionForm = useForm({
        source_reference_type: '',
        source_reference_id: '',
        source_action: '',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const holdForm = useForm({ lot_reference: '', reason: '' });
    const releaseForm = useForm({ lot_reference: '', reason: '' });

    const finalizeInspection = (id: number, decision: 'pass' | 'fail') => {
        router.post(route('textile.quality.inspections.finalize'), { inspection_id: id, decision }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Quality') }]} pageTitle={t('Textile Quality')}>
            <Head title={t('Textile Quality')} />

            <div className="grid gap-6 xl:grid-cols-2">
                <TextileFormCard title={t('Inspection')} icon={ShieldCheck}>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                inspectionForm.post(route('textile.quality.inspections.store'), {
                                    onSuccess: () => inspectionForm.reset('source_reference_type', 'source_reference_id', 'source_action', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}
                        >
                            <SelectField
                                label={t('Source Type')}
                                value={inspectionForm.data.source_reference_type}
                                onChange={(v) => inspectionForm.setData('source_reference_type', v)}
                                options={resolvedSourceTypeOptions}
                                includeEmpty
                                emptyLabel={t('Select source type')}
                                helperText={t('Source types are managed from Textile Master Setup.')}
                            />
                            <Field label={t('Source ID')} type="number" value={inspectionForm.data.source_reference_id} onChange={(v) => inspectionForm.setData('source_reference_id', v)} />
                            <Field label={t('Source Action')} value={inspectionForm.data.source_action} onChange={(v) => inspectionForm.setData('source_action', v)} />
                            <Field label={t('Party')} value={inspectionForm.data.party_name} onChange={(v) => inspectionForm.setData('party_name', v)} />
                            <Field label={t('Lot Reference')} value={inspectionForm.data.lot_reference} onChange={(v) => inspectionForm.setData('lot_reference', v)} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={inspectionForm.data.quantity} onChange={(v) => inspectionForm.setData('quantity', v)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={inspectionForm.data.unit}
                                    onChange={(v) => inspectionForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                            </div>
                            <Button type="submit" disabled={inspectionForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Inspection')}
                            </Button>
                        </form>

                </TextileFormCard>

                <TextileFormCard title={t('Hold and Release')} icon={ShieldCheck}>
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
                </TextileFormCard>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <TextileDataTableCard
                    data={inspections}
                    columns={createTextileWorkflowColumns(t, {
                        actions: createTextileWorkflowActions([
                            {
                                statuses: textileActionableStatuses.draft,
                                actions: [
                                    { label: t('Pass'), icon: Check, onClick: (row) => finalizeInspection(row.id, 'pass') },
                                    { label: t('Fail'), icon: Check, onClick: (row) => finalizeInspection(row.id, 'fail') },
                                ],
                            },
                        ]),
                    })}
                    emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No inspections found')} description={t('Create inspection records for lot-level quality checks.')} />}
                />
                <TextileDataTableCard
                    data={holds}
                    columns={createTextileWorkflowColumns(t)}
                    emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No hold/release records found')} description={t('Hold and release events will appear here.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
