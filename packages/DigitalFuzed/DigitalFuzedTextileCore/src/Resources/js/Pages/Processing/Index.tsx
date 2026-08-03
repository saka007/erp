import { Head, router, useForm } from '@inertiajs/react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { RefreshCw, Plus, CheckCircle2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { buildUnitOptions, formatTextileOptionLabel, textileSourceTypeOptions } from '@/components/textile/textile-form-options';
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
}

export default function Index({
    outwards,
    batches,
    inwards,
    reconciliations,
    sourceTypeOptions,
    sourceActionOptions,
    unitOptions,
    partyOptions,
    lotReferenceOptions,
}: {
    outwards: WorkflowDocument[];
    batches: WorkflowDocument[];
    inwards: WorkflowDocument[];
    reconciliations: WorkflowDocument[];
    sourceTypeOptions: string[];
    sourceActionOptions: string[];
    unitOptions: string[];
    partyOptions: string[];
    lotReferenceOptions: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('processing_'));
    const canOutward = !hasFineGrainedCapabilities || textileCapabilities.processing_outward;
    const canBatch = !hasFineGrainedCapabilities || textileCapabilities.processing_batch;
    const canInward = !hasFineGrainedCapabilities || textileCapabilities.processing_inward;
    const canReconcile = !hasFineGrainedCapabilities || textileCapabilities.processing_reconciliation;

    const outwardForm = useForm({
        source_reference_type: 'processing_order',
        source_reference_id: '',
        source_action: 'job_work_issue',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const batchForm = useForm({ outward_id: '' });
    const inwardForm = useForm({ batch_id: '', quantity: '', unit: 'mtr' });
    const reconcileForm = useForm({ outward_id: '', inward_id: '', notes: '' });
    const releasedOutwards = outwards.filter((row) => row.status === 'released');
    const releasedBatches = batches.filter((row) => row.status === 'released');
    const approvedInwards = inwards.filter((row) => row.status === 'approved');
    const resolvedSourceTypeOptions = sourceTypeOptions.length > 0
        ? sourceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : textileSourceTypeOptions;
    const resolvedSourceActionOptions = sourceActionOptions.length > 0
        ? sourceActionOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : [
            { value: 'job_work_issue', label: formatTextileOptionLabel('job_work_issue') },
            { value: 'processing_start', label: formatTextileOptionLabel('processing_start') },
            { value: 'job_work_receive', label: formatTextileOptionLabel('job_work_receive') },
        ];
    const resolvedPartyOptions = partyOptions.map((value) => ({ value, label: value }));
    const resolvedLotReferenceOptions = lotReferenceOptions.map((value) => ({ value, label: value }));
    const resolvedUnitOptions = buildUnitOptions(unitOptions);

    const allDocuments = [...outwards, ...batches, ...inwards, ...reconciliations];
    const draftCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;

    const releaseOutward = (id: number) => {
        router.post(route('textile.processing.outward.release'), { outward_id: id }, { preserveScroll: true });
    };

    const releaseBatch = (id: number) => {
        router.post(route('textile.processing.batches.release'), { batch_id: id }, { preserveScroll: true });
    };

    const finalizeInward = (id: number, decision: 'pass' | 'fail') => {
        router.post(route('textile.processing.inward.finalize'), { inward_id: id, decision }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Processing') }]} pageTitle={t('Textile Processing')}>
            <Head title={t('Textile Processing')} />

            <TextileKpiOverview
                title={t('Processing Overview')}
                className="mb-6"
                items={[
                    { label: t('Total Documents'), value: allDocuments.length, hint: t('Outward + Batch + Inward + Reconciliation') },
                    { label: t('Draft'), value: draftCount, hint: t('In preparation stage') },
                    { label: t('Approved'), value: approvedCount, hint: t('Validated processing records') },
                    { label: t('Released'), value: releasedCount, hint: t('Custody movement active') },
                ]}
            />

            <div className="grid gap-6 xl:grid-cols-2">
                {canOutward ? (
                    <TextileFormCard title={t('Job Work Outward')} icon={RefreshCw}>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                outwardForm.post(route('textile.processing.outward.store'), {
                                    onSuccess: () => outwardForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}
                        >
                            <SelectField label={t('Source Type')} value={outwardForm.data.source_reference_type} onChange={(value) => outwardForm.setData('source_reference_type', value)} options={resolvedSourceTypeOptions} includeEmpty emptyLabel={t('Select source type')} helperText={t('Source types are managed from Master Setup > Processing Setup > Source Types.')} required />
                            <Field label={t('Source ID')} type="number" value={outwardForm.data.source_reference_id} onChange={(value) => outwardForm.setData('source_reference_id', value)} required />
                            <SelectField label={t('Source Action')} value={outwardForm.data.source_action} onChange={(value) => outwardForm.setData('source_action', value)} options={resolvedSourceActionOptions} includeEmpty emptyLabel={t('Select source action')} helperText={t('Source actions are managed from Master Setup > Processing Setup > Source Actions.')} required />
                            <SelectField label={t('Processor/Party')} value={outwardForm.data.party_name} onChange={(value) => outwardForm.setData('party_name', value)} options={resolvedPartyOptions} includeEmpty emptyLabel={t('Select processor/party')} helperText={t('Party options are derived from vendor profiles and existing workflow records.')} disabled={resolvedPartyOptions.length === 0} disabledReason={t('No party options available yet. Create vendor profile first.')} />
                            <SelectField label={t('Lot Reference')} value={outwardForm.data.lot_reference} onChange={(value) => outwardForm.setData('lot_reference', value)} options={resolvedLotReferenceOptions} includeEmpty emptyLabel={t('Select lot reference')} helperText={t('Lot references are derived from active inventory lots and workflow records.')} disabled={resolvedLotReferenceOptions.length === 0} disabledReason={t('No lot options available yet. Create active inventory lots first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={outwardForm.data.quantity} onChange={(value) => outwardForm.setData('quantity', value)} required />
                                <SelectField label={t('Unit')} value={outwardForm.data.unit} onChange={(value) => outwardForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            </div>
                            <Button type="submit" disabled={outwardForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Outward')}
                            </Button>
                        </form>
                    </TextileFormCard>
                ) : null}

                {canBatch ? (
                    <TextileFormCard title={t('Processing Batch')} icon={RefreshCw}>
                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                batchForm.post(route('textile.processing.batches.store'), {
                                    onSuccess: () => batchForm.reset('outward_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Create Batch from Released Outward')} value={batchForm.data.outward_id} onChange={(value) => batchForm.setData('outward_id', value)} options={createTextileWorkflowSelectOptions(releasedOutwards)} includeEmpty emptyLabel={t('Select released outward')} helperText={t('Only released outwards are listed.')} disabled={releasedOutwards.length === 0} disabledReason={t('No released outward found. Release an outward first.')} required />
                            <Button type="submit" disabled={batchForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Batch')}
                            </Button>
                        </form>
                    </TextileFormCard>
                ) : null}

                {canInward ? (
                    <TextileFormCard title={t('Job Work Inward')} icon={RefreshCw}>
                        <form
                            className="grid grid-cols-4 gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                inwardForm.post(route('textile.processing.inward.store'), {
                                    onSuccess: () => inwardForm.reset('batch_id', 'quantity'),
                                });
                            }}
                        >
                            <SelectField label={t('Batch')} value={inwardForm.data.batch_id} onChange={(value) => inwardForm.setData('batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} helperText={t('Only released processing batches are listed.')} disabled={releasedBatches.length === 0} disabledReason={t('No released batch found. Release a batch first.')} required />
                            <Field label={t('Quantity')} type="number" value={inwardForm.data.quantity} onChange={(value) => inwardForm.setData('quantity', value)} />
                            <SelectField label={t('Unit')} value={inwardForm.data.unit} onChange={(value) => inwardForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            <Button type="submit" disabled={inwardForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Inward')}
                            </Button>
                        </form>
                    </TextileFormCard>
                ) : null}

                {canReconcile ? (
                    <TextileFormCard title={t('Reconciliation')} icon={CheckCircle2}>
                        <form
                            className="grid grid-cols-3 gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                reconcileForm.post(route('textile.processing.reconcile'), {
                                    onSuccess: () => reconcileForm.reset('outward_id', 'inward_id', 'notes'),
                                });
                            }}
                        >
                            <SelectField label={t('Outward')} value={reconcileForm.data.outward_id} onChange={(value) => reconcileForm.setData('outward_id', value)} options={createTextileWorkflowSelectOptions(releasedOutwards)} includeEmpty emptyLabel={t('Select released outward')} helperText={t('Only released outwards are listed.')} disabled={releasedOutwards.length === 0} disabledReason={t('No released outward found. Release an outward first.')} required />
                            <SelectField label={t('Inward')} value={reconcileForm.data.inward_id} onChange={(value) => reconcileForm.setData('inward_id', value)} options={createTextileWorkflowSelectOptions(approvedInwards)} includeEmpty emptyLabel={t('Select approved inward')} helperText={t('Only approved inwards are listed.')} disabled={approvedInwards.length === 0} disabledReason={t('No approved inward found. Finalize an inward first.')} required />
                            <Field label={t('Notes')} value={reconcileForm.data.notes} onChange={(value) => reconcileForm.setData('notes', value)} />
                            <Button type="submit" disabled={reconcileForm.processing} className="col-span-3">
                                <CheckCircle2 className="mr-2 h-4 w-4" />{t('Reconcile')}
                            </Button>
                        </form>
                    </TextileFormCard>
                ) : null}
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                {canOutward ? (
                <TextileDataTableSection
                    title={t('Outward Records')}
                    data={outwards}
                    columns={createTextileWorkflowColumns(t, {
                        actions: createTextileWorkflowActions([
                            {
                                statuses: textileActionableStatuses.draftOrApproved,
                                actions: [{ label: t('Release'), icon: CheckCircle2, onClick: (row) => releaseOutward(row.id) }],
                            },
                        ]),
                    })}
                    emptyState={<NoRecordsFound icon={RefreshCw} title={t('No outward records found')} description={t('Create job-work outward documents to start processing custody flow.')} />}
                />
                ) : null}
                {canBatch ? (
                <TextileDataTableSection
                    title={t('Batch Records')}
                    data={batches}
                    columns={createTextileWorkflowColumns(t, {
                        actions: createTextileWorkflowActions([
                            {
                                statuses: textileActionableStatuses.draftOrApproved,
                                actions: [{ label: t('Release'), icon: CheckCircle2, onClick: (row) => releaseBatch(row.id) }],
                            },
                        ]),
                    })}
                    emptyState={<NoRecordsFound icon={RefreshCw} title={t('No processing batches found')} description={t('Create processing batches from released outwards.')} />}
                />
                ) : null}
                {canInward ? (
                <TextileDataTableSection
                    title={t('Inward Records')}
                    data={inwards}
                    columns={createTextileWorkflowColumns(t, {
                        actions: createTextileWorkflowActions([
                            {
                                statuses: textileActionableStatuses.draft,
                                actions: [
                                    { label: t('Pass'), icon: CheckCircle2, onClick: (row) => finalizeInward(row.id, 'pass') },
                                    { label: t('Fail'), icon: CheckCircle2, onClick: (row) => finalizeInward(row.id, 'fail') },
                                ],
                            },
                        ]),
                    })}
                    emptyState={<NoRecordsFound icon={RefreshCw} title={t('No inward records found')} description={t('Create and finalize inward records for returned processed stock.')} />}
                />
                ) : null}
                {canReconcile ? (
                <TextileDataTableSection
                    title={t('Reconciliation Records')}
                    data={reconciliations}
                    columns={createTextileWorkflowColumns(t)}
                    emptyState={<NoRecordsFound icon={CheckCircle2} title={t('No reconciliations found')} description={t('Reconciliation entries appear after outward-inward matching.')} />}
                />
                ) : null}
            </div>
        </AuthenticatedLayout>
    );
}
