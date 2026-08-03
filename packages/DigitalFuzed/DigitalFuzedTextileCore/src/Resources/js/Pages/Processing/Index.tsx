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
    internalProcessings,
    dyeings,
    printings,
    bleachings,
    calendarings,
    compactings,
    finishings,
    shadeCards,
    processCosts,
    sourceTypeOptions,
    sourceActionOptions,
    unitOptions,
    processStageOptions,
    partyOptions,
    lotReferenceOptions,
}: {
    outwards: WorkflowDocument[];
    batches: WorkflowDocument[];
    inwards: WorkflowDocument[];
    reconciliations: WorkflowDocument[];
    internalProcessings: WorkflowDocument[];
    dyeings: WorkflowDocument[];
    printings: WorkflowDocument[];
    bleachings: WorkflowDocument[];
    calendarings: WorkflowDocument[];
    compactings: WorkflowDocument[];
    finishings: WorkflowDocument[];
    shadeCards: WorkflowDocument[];
    processCosts: WorkflowDocument[];
    sourceTypeOptions: string[];
    sourceActionOptions: string[];
    unitOptions: string[];
    processStageOptions: string[];
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
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const visibleSections = [
        canOutward ? 'job-work-outward' : null,
        canBatch ? 'processing-batch' : null,
        canInward ? 'job-work-inward' : null,
        canReconcile ? 'reconciliation' : null,
        canBatch ? 'internal-processing' : null,
        canBatch ? 'dyeing' : null,
        canBatch ? 'printing' : null,
        canBatch ? 'bleaching' : null,
        canBatch ? 'calendaring' : null,
        canBatch ? 'compacting' : null,
        canBatch ? 'finishing' : null,
        canInward ? 'shade-card' : null,
        canReconcile ? 'process-cost' : null,
    ].filter((value): value is string => value !== null);
    const validSections = new Set(visibleSections);
    const activeSection = sectionParam && validSections.has(sectionParam)
        ? sectionParam
        : (visibleSections[0] ?? 'job-work-outward');
    const isSectionVisible = (...sections: string[]) => sections.includes(activeSection);

    const showOutward = canOutward && isSectionVisible('job-work-outward');
    const showBatch = canBatch && isSectionVisible('processing-batch');
    const showInward = canInward && isSectionVisible('job-work-inward');
    const showReconcile = canReconcile && isSectionVisible('reconciliation');
    const showInternalProcessing = canBatch && isSectionVisible('internal-processing');
    const showDyeing = canBatch && isSectionVisible('dyeing');
    const showPrinting = canBatch && isSectionVisible('printing');
    const showBleaching = canBatch && isSectionVisible('bleaching');
    const showCalendaring = canBatch && isSectionVisible('calendaring');
    const showCompacting = canBatch && isSectionVisible('compacting');
    const showFinishing = canBatch && isSectionVisible('finishing');
    const showShadeCard = canInward && isSectionVisible('shade-card');
    const showProcessCost = canReconcile && isSectionVisible('process-cost');

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
    const internalProcessingForm = useForm({ processing_batch_id: '', recipe_code: '', quantity: '', unit: 'mtr', notes: '' });
    const dyeingForm = useForm({ processing_batch_id: '', recipe_code: '', quantity: '', unit: 'mtr', notes: '' });
    const printingForm = useForm({ processing_batch_id: '', recipe_code: '', quantity: '', unit: 'mtr', notes: '' });
    const bleachingForm = useForm({ processing_batch_id: '', recipe_code: '', quantity: '', unit: 'mtr', notes: '' });
    const calendaringForm = useForm({ processing_batch_id: '', recipe_code: '', quantity: '', unit: 'mtr', notes: '' });
    const compactingForm = useForm({ processing_batch_id: '', recipe_code: '', quantity: '', unit: 'mtr', notes: '' });
    const finishingForm = useForm({ processing_batch_id: '', recipe_code: '', quantity: '', unit: 'mtr', notes: '' });
    const shadeCardForm = useForm({ processing_batch_id: '', shade_code: '', shade_family: '', quantity: '', unit: 'mtr', notes: '' });
    const processCostForm = useForm({ processing_batch_id: '', process_stage: 'internal_processing', cost_amount: '', quantity: '', unit: 'mtr', notes: '' });
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
    const resolvedProcessStageOptions = processStageOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));

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
                {showOutward ? (
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

                {showBatch ? (
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

                {showInward ? (
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

                {showReconcile ? (
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

                    {showInternalProcessing ? (
                    <TextileFormCard title={t('Internal Processing')} icon={RefreshCw}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            internalProcessingForm.post(route('textile.processing.internal-processing.store'), {
                                onSuccess: () => internalProcessingForm.reset('processing_batch_id', 'recipe_code', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={internalProcessingForm.data.processing_batch_id} onChange={(value) => internalProcessingForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} helperText={t('Only released processing batches are listed.')} disabled={releasedBatches.length === 0} disabledReason={t('No released batch found. Release a batch first.')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Recipe Code')} value={internalProcessingForm.data.recipe_code} onChange={(value) => internalProcessingForm.setData('recipe_code', value)} />
                                <Field label={t('Quantity')} type="number" value={internalProcessingForm.data.quantity} onChange={(value) => internalProcessingForm.setData('quantity', value)} />
                                <SelectField label={t('Unit')} value={internalProcessingForm.data.unit} onChange={(value) => internalProcessingForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            </div>
                            <Field label={t('Notes')} value={internalProcessingForm.data.notes} onChange={(value) => internalProcessingForm.setData('notes', value)} />
                            <Button type="submit" disabled={internalProcessingForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Internal Processing')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showDyeing ? (
                    <TextileFormCard title={t('Dyeing')} icon={RefreshCw}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            dyeingForm.post(route('textile.processing.dyeing.store'), {
                                onSuccess: () => dyeingForm.reset('processing_batch_id', 'recipe_code', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={dyeingForm.data.processing_batch_id} onChange={(value) => dyeingForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Recipe Code')} value={dyeingForm.data.recipe_code} onChange={(value) => dyeingForm.setData('recipe_code', value)} />
                                <Field label={t('Quantity')} type="number" value={dyeingForm.data.quantity} onChange={(value) => dyeingForm.setData('quantity', value)} />
                                <SelectField label={t('Unit')} value={dyeingForm.data.unit} onChange={(value) => dyeingForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                            </div>
                            <Field label={t('Notes')} value={dyeingForm.data.notes} onChange={(value) => dyeingForm.setData('notes', value)} />
                            <Button type="submit" disabled={dyeingForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Dyeing')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showPrinting ? (
                    <TextileFormCard title={t('Printing')} icon={RefreshCw}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            printingForm.post(route('textile.processing.printing.store'), {
                                onSuccess: () => printingForm.reset('processing_batch_id', 'recipe_code', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={printingForm.data.processing_batch_id} onChange={(value) => printingForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Recipe Code')} value={printingForm.data.recipe_code} onChange={(value) => printingForm.setData('recipe_code', value)} />
                                <Field label={t('Quantity')} type="number" value={printingForm.data.quantity} onChange={(value) => printingForm.setData('quantity', value)} />
                                <SelectField label={t('Unit')} value={printingForm.data.unit} onChange={(value) => printingForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                            </div>
                            <Field label={t('Notes')} value={printingForm.data.notes} onChange={(value) => printingForm.setData('notes', value)} />
                            <Button type="submit" disabled={printingForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Printing')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showBleaching ? (
                    <TextileFormCard title={t('Bleaching')} icon={RefreshCw}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            bleachingForm.post(route('textile.processing.bleaching.store'), {
                                onSuccess: () => bleachingForm.reset('processing_batch_id', 'recipe_code', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={bleachingForm.data.processing_batch_id} onChange={(value) => bleachingForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Recipe Code')} value={bleachingForm.data.recipe_code} onChange={(value) => bleachingForm.setData('recipe_code', value)} />
                                <Field label={t('Quantity')} type="number" value={bleachingForm.data.quantity} onChange={(value) => bleachingForm.setData('quantity', value)} />
                                <SelectField label={t('Unit')} value={bleachingForm.data.unit} onChange={(value) => bleachingForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                            </div>
                            <Field label={t('Notes')} value={bleachingForm.data.notes} onChange={(value) => bleachingForm.setData('notes', value)} />
                            <Button type="submit" disabled={bleachingForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Bleaching')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showCalendaring ? (
                    <TextileFormCard title={t('Calendaring')} icon={RefreshCw}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            calendaringForm.post(route('textile.processing.calendaring.store'), {
                                onSuccess: () => calendaringForm.reset('processing_batch_id', 'recipe_code', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={calendaringForm.data.processing_batch_id} onChange={(value) => calendaringForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Recipe Code')} value={calendaringForm.data.recipe_code} onChange={(value) => calendaringForm.setData('recipe_code', value)} />
                                <Field label={t('Quantity')} type="number" value={calendaringForm.data.quantity} onChange={(value) => calendaringForm.setData('quantity', value)} />
                                <SelectField label={t('Unit')} value={calendaringForm.data.unit} onChange={(value) => calendaringForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                            </div>
                            <Field label={t('Notes')} value={calendaringForm.data.notes} onChange={(value) => calendaringForm.setData('notes', value)} />
                            <Button type="submit" disabled={calendaringForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Calendaring')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showCompacting ? (
                    <TextileFormCard title={t('Compacting')} icon={RefreshCw}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            compactingForm.post(route('textile.processing.compacting.store'), {
                                onSuccess: () => compactingForm.reset('processing_batch_id', 'recipe_code', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={compactingForm.data.processing_batch_id} onChange={(value) => compactingForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Recipe Code')} value={compactingForm.data.recipe_code} onChange={(value) => compactingForm.setData('recipe_code', value)} />
                                <Field label={t('Quantity')} type="number" value={compactingForm.data.quantity} onChange={(value) => compactingForm.setData('quantity', value)} />
                                <SelectField label={t('Unit')} value={compactingForm.data.unit} onChange={(value) => compactingForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                            </div>
                            <Field label={t('Notes')} value={compactingForm.data.notes} onChange={(value) => compactingForm.setData('notes', value)} />
                            <Button type="submit" disabled={compactingForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Compacting')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showFinishing ? (
                    <TextileFormCard title={t('Finishing')} icon={RefreshCw}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            finishingForm.post(route('textile.processing.finishing.store'), {
                                onSuccess: () => finishingForm.reset('processing_batch_id', 'recipe_code', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={finishingForm.data.processing_batch_id} onChange={(value) => finishingForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Recipe Code')} value={finishingForm.data.recipe_code} onChange={(value) => finishingForm.setData('recipe_code', value)} />
                                <Field label={t('Quantity')} type="number" value={finishingForm.data.quantity} onChange={(value) => finishingForm.setData('quantity', value)} />
                                <SelectField label={t('Unit')} value={finishingForm.data.unit} onChange={(value) => finishingForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                            </div>
                            <Field label={t('Notes')} value={finishingForm.data.notes} onChange={(value) => finishingForm.setData('notes', value)} />
                            <Button type="submit" disabled={finishingForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Finishing')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showShadeCard ? (
                    <TextileFormCard title={t('Shade Card')} icon={CheckCircle2}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            shadeCardForm.post(route('textile.processing.shade-cards.store'), {
                                onSuccess: () => shadeCardForm.reset('processing_batch_id', 'shade_code', 'shade_family', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={shadeCardForm.data.processing_batch_id} onChange={(value) => shadeCardForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Shade Code')} value={shadeCardForm.data.shade_code} onChange={(value) => shadeCardForm.setData('shade_code', value)} required />
                                <Field label={t('Shade Family')} value={shadeCardForm.data.shade_family} onChange={(value) => shadeCardForm.setData('shade_family', value)} />
                                <Field label={t('Quantity')} type="number" value={shadeCardForm.data.quantity} onChange={(value) => shadeCardForm.setData('quantity', value)} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Unit')} value={shadeCardForm.data.unit} onChange={(value) => shadeCardForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                                <Field label={t('Notes')} value={shadeCardForm.data.notes} onChange={(value) => shadeCardForm.setData('notes', value)} />
                            </div>
                            <Button type="submit" disabled={shadeCardForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Shade Card')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}

                    {showProcessCost ? (
                    <TextileFormCard title={t('Process Cost')} icon={CheckCircle2}>
                        <form className="space-y-3" onSubmit={(event) => {
                            event.preventDefault();
                            processCostForm.post(route('textile.processing.process-costs.store'), {
                                onSuccess: () => processCostForm.reset('processing_batch_id', 'cost_amount', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={processCostForm.data.processing_batch_id} onChange={(value) => processCostForm.setData('processing_batch_id', value)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <SelectField label={t('Process Stage')} value={processCostForm.data.process_stage} onChange={(value) => processCostForm.setData('process_stage', value)} options={resolvedProcessStageOptions} required />
                                <Field label={t('Cost Amount')} type="number" value={processCostForm.data.cost_amount} onChange={(value) => processCostForm.setData('cost_amount', value)} required />
                                <Field label={t('Quantity')} type="number" value={processCostForm.data.quantity} onChange={(value) => processCostForm.setData('quantity', value)} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Unit')} value={processCostForm.data.unit} onChange={(value) => processCostForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} />
                                <Field label={t('Notes')} value={processCostForm.data.notes} onChange={(value) => processCostForm.setData('notes', value)} />
                            </div>
                            <Button type="submit" disabled={processCostForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Process Cost')}</Button>
                        </form>
                    </TextileFormCard>
                    ) : null}
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                {showOutward ? (
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
                {showBatch ? (
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
                {showInward ? (
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
                {showReconcile ? (
                <TextileDataTableSection
                    title={t('Reconciliation Records')}
                    data={reconciliations}
                    columns={createTextileWorkflowColumns(t)}
                    emptyState={<NoRecordsFound icon={CheckCircle2} title={t('No reconciliations found')} description={t('Reconciliation entries appear after outward-inward matching.')} />}
                />
                ) : null}
                {showInternalProcessing ? <TextileDataTableSection title={t('Internal Processing Records')} data={internalProcessings} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No internal processing found')} description={t('Record internal processing stages on released batches.')} />} /> : null}
                {showDyeing ? <TextileDataTableSection title={t('Dyeing Records')} data={dyeings} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No dyeing records found')} description={t('Record dyeing stages on released batches.')} />} /> : null}
                {showPrinting ? <TextileDataTableSection title={t('Printing Records')} data={printings} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No printing records found')} description={t('Record printing stages on released batches.')} />} /> : null}
                {showBleaching ? <TextileDataTableSection title={t('Bleaching Records')} data={bleachings} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No bleaching records found')} description={t('Record bleaching stages on released batches.')} />} /> : null}
                {showCalendaring ? <TextileDataTableSection title={t('Calendaring Records')} data={calendarings} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No calendaring records found')} description={t('Record calendaring stages on released batches.')} />} /> : null}
                {showCompacting ? <TextileDataTableSection title={t('Compacting Records')} data={compactings} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No compacting records found')} description={t('Record compacting stages on released batches.')} />} /> : null}
                {showFinishing ? <TextileDataTableSection title={t('Finishing Records')} data={finishings} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={RefreshCw} title={t('No finishing records found')} description={t('Record finishing stages on released batches.')} />} /> : null}
                {showShadeCard ? <TextileDataTableSection title={t('Shade Card Records')} data={shadeCards} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={CheckCircle2} title={t('No shade cards found')} description={t('Record shade cards against processing batches.')} />} /> : null}
                {showProcessCost ? <TextileDataTableSection title={t('Process Cost Records')} data={processCosts} columns={[{ key: 'document_number', header: t('Number') }, { key: 'source_reference_id', header: t('Batch ID') }, { key: 'process_stage', header: t('Stage'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.process_stage ?? '-') }, { key: 'cost_amount', header: t('Cost Amount'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.cost_amount ?? '-') }, { key: 'cost_per_unit', header: t('Cost/Unit'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.cost_per_unit ?? '-') }, { key: 'quantity', header: t('Qty') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={CheckCircle2} title={t('No process costs found')} description={t('Record stage-wise process cost on completed processing batches.')} />} /> : null}
            </div>
        </AuthenticatedLayout>
    );
}
