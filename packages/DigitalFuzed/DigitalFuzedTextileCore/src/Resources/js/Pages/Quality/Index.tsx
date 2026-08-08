import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Award, Check, FileQuestion, Plus, RotateCcw, ShieldCheck, TicketCheck, XCircle } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileSection } from '@/components/textile/textile-section';
import { TextileWorkspace } from '@/components/textile/textile-workspace';
import { getTextileWorkspace } from '@/components/textile/textile-workspaces';
import { TextileInfoPanel, MetricSummaryCard, type ActivityItem } from '@/components/textile/textile-info-panel';
import { TextileWorkflowSteps, type WorkflowStep } from '@/components/textile/textile-workflow-steps';
import { buildUnitOptions, formatTextileLabel, formatTextileOptionLabel } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';
import { PageProps } from '@/types';

interface WorkflowDocument {
    id: number;
    document_number: string;
    source_reference_type?: string | null;
    source_action?: string | null;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    metadata?: {
        qc_stage?: string | null;
        inspection_result?: string | null;
        final_decision?: string | null;
        defects?: string[];
        shade_reference?: string | null;
        certificate_number?: string | null;
        inspection_id?: number | null;
        notes?: string | null;
    } | null;
}

interface TextileLot {
    id: number;
    lot_reference: string;
    status: string;
}

const QUALITY_SECTIONS = ['inspection', 'hold-release', 'certificates'] as const;
type QualitySection = typeof QUALITY_SECTIONS[number];

function normalizeDecisionFilter(row: WorkflowDocument): 'pass' | 'fail' | 'rework' | 'pending' {
    if (row.metadata?.final_decision === 'pass' || row.status === 'approved') {
        return 'pass';
    }

    if (row.metadata?.final_decision === 'rework') {
        return 'rework';
    }

    if (row.metadata?.final_decision === 'fail' || row.status === 'rejected') {
        return 'fail';
    }

    return 'pending';
}

export default function Index({
    inspections,
    holds,
    certificates,
    lots,
    sourceTypeOptions,
    sourceActionOptions,
    qcStageOptions,
    inspectionResultOptions,
    fabricDefectOptions,
    unitOptions,
    partyOptions,
    lotReferenceOptions,
    recentActivity,
}: {
    inspections: WorkflowDocument[];
    holds: WorkflowDocument[];
    certificates: WorkflowDocument[];
    lots: TextileLot[];
    sourceTypeOptions: string[];
    sourceActionOptions: string[];
    qcStageOptions: string[];
    inspectionResultOptions: string[];
    fabricDefectOptions: string[];
    unitOptions: string[];
    partyOptions: string[];
    lotReferenceOptions: string[];
    recentActivity: ActivityItem[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('quality_'));
    const canInspection = !hasFineGrainedCapabilities || textileCapabilities.quality_inspection;
    const canHoldRelease = !hasFineGrainedCapabilities || textileCapabilities.quality_hold_release;

    const queryParams = new URLSearchParams(window.location.search);
    const sectionParam = queryParams.get('section');
    const qcStageParam = queryParams.get('qc_stage');
    const decisionParam = queryParams.get('decision');

    const qualityWorkspace = getTextileWorkspace('quality')!;
    const activeMenuSection = qualityWorkspace.sections.find((section) => section.id === sectionParam);
    const [openStep, setOpenStep] = useState<string | null>(queryParams.get('sub'));

    const visibleSections: QualitySection[] = [
        canInspection ? 'inspection' : null,
        canHoldRelease ? 'hold-release' : null,
        canInspection ? 'certificates' : null,
    ].filter((value): value is QualitySection => value !== null);

    const activeSection = sectionParam && visibleSections.includes(sectionParam as QualitySection)
        ? sectionParam as QualitySection
        : (visibleSections[0] ?? 'inspection');

    const resolvedQcStageOptions = qcStageOptions.length > 0
        ? qcStageOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : [
            { value: 'incoming_qc', label: formatTextileOptionLabel('incoming_qc') },
            { value: 'in_process_qc', label: formatTextileOptionLabel('in_process_qc') },
            { value: 'final_qc', label: formatTextileOptionLabel('final_qc') },
            { value: 'shade_matching', label: formatTextileOptionLabel('shade_matching') },
        ];

    const defaultQcStage = qcStageParam && resolvedQcStageOptions.some((option) => option.value === qcStageParam)
        ? qcStageParam
        : (resolvedQcStageOptions[0]?.value ?? 'incoming_qc');

    const resolvedSourceTypeOptions = sourceTypeOptions.length > 0
        ? sourceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : resolvedQcStageOptions;

    const resolvedSourceActionOptions = sourceActionOptions.length > 0
        ? sourceActionOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : [
            { value: 'incoming_qc', label: formatTextileOptionLabel('incoming_qc') },
            { value: 'in_process_qc', label: formatTextileOptionLabel('in_process_qc') },
            { value: 'final_qc', label: formatTextileOptionLabel('final_qc') },
            { value: 'shade_matching', label: formatTextileOptionLabel('shade_matching') },
            { value: 'quality_certificate', label: formatTextileOptionLabel('quality_certificate') },
            { value: 'hold', label: formatTextileOptionLabel('hold') },
            { value: 'release', label: formatTextileOptionLabel('release') },
        ];

    const resolvedInspectionResultOptions = inspectionResultOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedFabricDefectOptions = fabricDefectOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedPartyOptions = partyOptions.map((value) => ({ value, label: value }));
    const resolvedLotReferenceOptions = lotReferenceOptions.map((value) => ({ value, label: value }));
    const resolvedUnitOptions = buildUnitOptions(unitOptions);

    const filteredInspections = inspections.filter((row) => {
        const rowQcStage = String(row.metadata?.qc_stage ?? row.source_reference_type ?? '').trim();
        const rowDecision = normalizeDecisionFilter(row);

        if (qcStageParam && rowQcStage !== qcStageParam) {
            return false;
        }

        if (decisionParam === 'fail' && rowDecision !== 'fail') {
            return false;
        }

        if (decisionParam === 'pass' && rowDecision !== 'pass') {
            return false;
        }

        return true;
    });

    const issuedCertificates = certificates.filter((row) => row.status === 'approved').length;
    const rejectedInspections = filteredInspections.filter((row) => normalizeDecisionFilter(row) === 'fail').length;
    const passedInspections = filteredInspections.filter((row) => normalizeDecisionFilter(row) === 'pass').length;
    const reworkInspections = filteredInspections.filter((row) => normalizeDecisionFilter(row) === 'rework').length;
    const pendingInspections = filteredInspections.filter((row) => normalizeDecisionFilter(row) === 'pending').length;
    const pendingCertificates = certificates.length - issuedCertificates;

    const qualityRows = [
        ...filteredInspections.map((row) => ({ type: t('Fabric Inspection'), ...row })),
        ...holds.map((row) => ({ type: t('Hold/Release'), ...row })),
        ...certificates.map((row) => ({ type: t('Quality Certificate'), ...row })),
    ];

    const inspectionForm = useForm({
        source_reference_type: defaultQcStage,
        source_reference_id: '',
        source_action: defaultQcStage,
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
        qc_stage: defaultQcStage,
        inspection_result: resolvedInspectionResultOptions[0]?.value ?? 'pass',
        defects: [] as string[],
        shade_reference: '',
        notes: '',
    });

    const holdForm = useForm({ lot_reference: '', reason: '' });
    const releaseForm = useForm({ lot_reference: '', reason: '' });
    const certificateForm = useForm({
        source_reference_type: 'quality_certificate',
        source_action: 'quality_certificate',
        inspection_id: '',
        lot_reference: '',
        certificate_number: '',
        notes: '',
    });

    const finalizeInspection = (id: number, decision: 'pass' | 'fail' | 'rework') => {
        router.post(route('textile.quality.inspections.finalize'), { inspection_id: id, decision }, { preserveScroll: true });
    };

    const issueCertificate = (id: number) => {
        router.post(route('textile.quality.certificates.issue'), { certificate_id: id }, { preserveScroll: true });
    };

    const certificateInspectionOptions = createTextileWorkflowSelectOptions(
        inspections.filter((row) => ['approved', 'released'].includes(row.status))
    );

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Textile') },
                { label: t('Quality') },
                ...(activeMenuSection ? [{ label: t(activeMenuSection.label) }] : []),
            ]}
            pageTitle={t('Textile Quality')}
            pageActions={(
                <Button
                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                    onClick={() => router.get(route('textile.quality.index', { section: 'inspection' }), {}, { preserveState: true, replace: true })}
                >
                    <Plus className="h-4 w-4" />
                    {t('New Inspection')}
                </Button>
            )}
        >
            <Head title={t('Textile Quality')} />

            <TextileWorkspace
                workspace={qualityWorkspace}
                capabilities={textileCapabilities}
                kpis={(section) => {
                    if (section.id === 'overview') {
                        return [
                            { label: t('Inspections'), value: filteredInspections.length, hint: t('Filtered by section parameters'), icon: ShieldCheck },
                            { label: t('Rejected'), value: rejectedInspections, hint: t('Fail decision records'), icon: XCircle },
                            { label: t('Hold Events'), value: holds.length, hint: t('Lot hold/release actions'), icon: TicketCheck },
                            { label: t('Issued Certificates'), value: issuedCertificates, hint: t('Approved quality certificates'), icon: Award },
                        ];
                    }
                    if (section.id === 'inspection') {
                        return [
                            { label: t('Total Inspections'), value: filteredInspections.length, hint: t('Filtered by section parameters'), icon: ShieldCheck },
                            { label: t('Passed'), value: passedInspections, hint: t('Pass decision records'), icon: Check },
                            { label: t('Rework'), value: reworkInspections, hint: t('Rework decision records'), icon: RotateCcw },
                            { label: t('Rejected'), value: rejectedInspections, hint: t('Fail decision records'), icon: XCircle },
                        ];
                    }
                    if (section.id === 'hold-release') {
                        return [
                            { label: t('Hold Events'), value: holds.length, hint: t('Lot hold/release actions'), icon: TicketCheck },
                            { label: t('Inspections'), value: filteredInspections.length, hint: t('Filtered by section parameters'), icon: ShieldCheck },
                            { label: t('Issued Certificates'), value: issuedCertificates, hint: t('Approved quality certificates'), icon: Award },
                            { label: t('Rejected'), value: rejectedInspections, hint: t('Fail decision records'), icon: XCircle },
                        ];
                    }
                    return [
                        { label: t('Issued Certificates'), value: issuedCertificates, hint: t('Approved quality certificates'), icon: Award },
                        { label: t('Pending Certificates'), value: pendingCertificates, hint: t('Certificates not yet issued'), icon: FileQuestion },
                        { label: t('Inspections'), value: filteredInspections.length, hint: t('Filtered by section parameters'), icon: ShieldCheck },
                        { label: t('Rejected'), value: rejectedInspections, hint: t('Fail decision records'), icon: XCircle },
                    ];
                }}
                aside={(section) => (
                    <>
                        <TextileInfoPanel
                            stages={[
                                { id: 'inspection', label: t('Inspections'), count: filteredInspections.length, active: section.id === 'inspection' },
                                { id: 'hold-release', label: t('Hold/Release'), count: holds.length, active: section.id === 'hold-release' },
                                { id: 'certificates', label: t('Certificates'), count: certificates.length, active: section.id === 'certificates' },
                            ]}
                            activities={recentActivity}
                        />
                        <MetricSummaryCard
                            title={t('Quality Summary')}
                            rows={[
                                { label: t('Inspections'), value: filteredInspections.length },
                                { label: t('Rejected'), value: rejectedInspections },
                                { label: t('Hold Events'), value: holds.length },
                                { label: t('Issued Certificates'), value: issuedCertificates },
                            ]}
                        />
                        <MetricSummaryCard
                            title={t('Decision Breakdown')}
                            rows={[
                                { label: t('Passed'), value: passedInspections },
                                { label: t('Rework'), value: reworkInspections },
                                { label: t('Pending'), value: pendingInspections },
                                { label: t('Rejected'), value: rejectedInspections },
                            ]}
                        />
                    </>
                )}
            >
                {(section) => {
                    switch (section.id) {
                        case 'overview':
                            return (
                                <TextileSection
                                    table={
                                        <TextileDataTableCard
                                            data={qualityRows}
                                            columns={[
                                                { key: 'type', header: t('Type'), render: (_value: unknown, row: WorkflowDocument & { type: string }) => formatTextileLabel(row.type) },
                                                { key: 'document_number', header: t('Document') },
                                                { key: 'party_name', header: t('Party'), render: optional },
                                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                                { key: 'quantity', header: t('Qty') },
                                                { key: 'unit', header: t('Unit'), render: optional },
                                                { key: 'status', header: t('Status'), render: (_value: unknown, row: WorkflowDocument & { type: string }) => formatTextileLabel(row.status) },
                                            ]}
                                            emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No quality records yet')} description={t('Create an inspection to start tracking quality.')} />}
                                        />
                                    }
                                />
                            );

                        case 'inspection': {
                            const steps: WorkflowStep[] = [{
                                id: 'inspection',
                                title: t('Fabric Inspection'),
                                icon: ShieldCheck,
                                count: filteredInspections.length,
                                form: (
                                    <TextileFormCard title={t('Fabric Inspection')} icon={ShieldCheck}>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                inspectionForm.post(route('textile.quality.inspections.store'), {
                                    onSuccess: () => inspectionForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity', 'defects', 'shade_reference', 'notes'),
                                });
                            }}
                        >
                            <SelectField
                                label={t('QC Stage')}
                                value={inspectionForm.data.qc_stage}
                                onChange={(value: string) => {
                                    inspectionForm.setData('qc_stage', value);
                                    inspectionForm.setData('source_reference_type', value);
                                    inspectionForm.setData('source_action', value);
                                }}
                                options={resolvedQcStageOptions}
                                includeEmpty
                                emptyLabel={t('Select QC stage')}
                                helperText={t('Stage links directly to Process QC, Final QC, and Shade Matching screens.')}
                                required
                            />
                            <SelectField
                                label={t('Source Type')}
                                value={inspectionForm.data.source_reference_type}
                                onChange={(value: string) => inspectionForm.setData('source_reference_type', value)}
                                options={resolvedSourceTypeOptions}
                                includeEmpty
                                emptyLabel={t('Select source type')}
                                helperText={t('Source types are managed from Master Setup > Quality Setup > Source Types.')}
                            />
                            <Field label={t('Source ID')} type="number" value={inspectionForm.data.source_reference_id} onChange={(value: string) => inspectionForm.setData('source_reference_id', value)} />
                            <SelectField
                                label={t('Source Action')}
                                value={inspectionForm.data.source_action}
                                onChange={(value: string) => inspectionForm.setData('source_action', value)}
                                options={resolvedSourceActionOptions}
                                includeEmpty
                                emptyLabel={t('Select source action')}
                                helperText={t('Source actions are managed from Master Setup > Quality Setup > Source Actions.')}
                            />
                            <SelectField
                                label={t('Inspection Result')}
                                value={inspectionForm.data.inspection_result}
                                onChange={(value: string) => inspectionForm.setData('inspection_result', value)}
                                options={resolvedInspectionResultOptions}
                                includeEmpty
                                emptyLabel={t('Select inspection result')}
                                helperText={t('Inspection results are managed from Master Setup > Quality Setup > Inspection Results.')}
                                required
                            />
                            <SelectField
                                label={t('Primary Defect')}
                                value={inspectionForm.data.defects[0] ?? ''}
                                onChange={(value: string) => inspectionForm.setData('defects', value ? [value] : [])}
                                options={resolvedFabricDefectOptions}
                                includeEmpty
                                emptyLabel={t('Select defect')}
                                helperText={t('Defect options are managed from Master Setup > Quality Setup > Fabric Defects.')}
                                disabled={resolvedFabricDefectOptions.length === 0}
                                disabledReason={t('No defect options available yet. Create defect library first.')}
                            />
                            {inspectionForm.data.qc_stage === 'shade_matching' ? (
                                <Field label={t('Shade Reference')} value={inspectionForm.data.shade_reference} onChange={(value: string) => inspectionForm.setData('shade_reference', value)} required />
                            ) : null}
                            <SelectField
                                label={t('Party')}
                                value={inspectionForm.data.party_name}
                                onChange={(value: string) => inspectionForm.setData('party_name', value)}
                                options={resolvedPartyOptions}
                                includeEmpty
                                emptyLabel={t('Select party')}
                                helperText={t('Party options are derived from customer/vendor profiles and workflow records.')}
                                disabled={resolvedPartyOptions.length === 0}
                                disabledReason={t('No party options available yet. Create customer/vendor profile first.')}
                            />
                            <SelectField
                                label={t('Lot Reference')}
                                value={inspectionForm.data.lot_reference}
                                onChange={(value: string) => inspectionForm.setData('lot_reference', value)}
                                options={resolvedLotReferenceOptions}
                                includeEmpty
                                emptyLabel={t('Select lot reference')}
                                helperText={t('Lot references are derived from active inventory lots and workflow records.')}
                                disabled={resolvedLotReferenceOptions.length === 0}
                                disabledReason={t('No lot options available yet. Create active inventory lots first.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={inspectionForm.data.quantity} onChange={(value: string) => inspectionForm.setData('quantity', value)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={inspectionForm.data.unit}
                                    onChange={(value: string) => inspectionForm.setData('unit', value)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                            </div>
                            <Field label={t('Notes')} value={inspectionForm.data.notes} onChange={(value: string) => inspectionForm.setData('notes', value)} />
                            <Button type="submit" disabled={inspectionForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Inspection')}
                            </Button>
                        </form>
                                    </TextileFormCard>
                                ),
                            }];
                            return (
                                <div className="space-y-6">
                                    <TextileWorkflowSteps steps={steps} openId={openStep} onOpenChange={setOpenStep} records={
                                        <TextileDataTableSection title={t('Inspection Records')}>
                                            <TextileDataTableCard
                                                data={filteredInspections}
                                                columns={createTextileWorkflowColumns(t, {
                                                    actions: createTextileWorkflowActions([
                                                        {
                                                            statuses: textileActionableStatuses.draft,
                                                            actions: [
                                                                { label: t('Pass'), icon: Check, onClick: (row: WorkflowDocument) => finalizeInspection(row.id, 'pass') },
                                                                { label: t('Reject'), icon: XCircle, onClick: (row: WorkflowDocument) => finalizeInspection(row.id, 'fail') },
                                                                { label: t('Rework'), icon: ShieldCheck, onClick: (row: WorkflowDocument) => finalizeInspection(row.id, 'rework') },
                                                            ],
                                                        },
                                                    ]),
                                                }).concat([
                                                    { key: 'qc_stage', header: t('QC Stage'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.qc_stage ?? '')) },
                                                    { key: 'inspection_result', header: t('Result'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.inspection_result ?? '')) },
                                                    { key: 'defects', header: t('Defects'), render: (_value: unknown, row: WorkflowDocument) => (row.metadata?.defects ?? []).join(', ') || '-' },
                                                    { key: 'decision', header: t('Decision'), render: (_value: unknown, row: WorkflowDocument) => normalizeDecisionFilter(row) },
                                                ])}
                                                emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No inspection records found')} description={t('Create inspection records for incoming, process, final, and shade matching checks.')} />}
                                            />
                                        </TextileDataTableSection>
                                    } />
                                </div>
                            );
                        }

                        case 'hold-release': {
                            const steps: WorkflowStep[] = [{
                                id: 'hold-release',
                                title: t('Hold and Release'),
                                icon: TicketCheck,
                                count: holds.length,
                                form: (
                                    <TextileFormCard title={t('Hold and Release')} icon={ShieldCheck}>
                        <form
                            className="grid grid-cols-[1fr_1fr_auto] gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                holdForm.post(route('textile.quality.lots.hold'), {
                                    onSuccess: () => holdForm.reset('reason'),
                                });
                            }}
                        >
                            <SelectField label={t('Lot Reference')} value={holdForm.data.lot_reference} onChange={(value: string) => holdForm.setData('lot_reference', value)} options={resolvedLotReferenceOptions} includeEmpty emptyLabel={t('Select lot')} required />
                            <Field label={t('Hold Reason')} value={holdForm.data.reason} onChange={(value: string) => holdForm.setData('reason', value)} />
                            <Button type="submit" variant="outline" disabled={holdForm.processing} className="self-end">
                                <Plus className="mr-2 h-4 w-4" />{t('Apply Hold')}
                            </Button>
                        </form>

                        <form
                            className="grid grid-cols-[1fr_1fr_auto] gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                releaseForm.post(route('textile.quality.lots.release'), {
                                    onSuccess: () => releaseForm.reset('reason'),
                                });
                            }}
                        >
                            <SelectField label={t('Lot Reference')} value={releaseForm.data.lot_reference} onChange={(value: string) => releaseForm.setData('lot_reference', value)} options={resolvedLotReferenceOptions} includeEmpty emptyLabel={t('Select lot')} required />
                            <Field label={t('Release Reason')} value={releaseForm.data.reason} onChange={(value: string) => releaseForm.setData('reason', value)} />
                            <Button type="submit" disabled={releaseForm.processing} className="self-end">
                                <Check className="mr-2 h-4 w-4" />{t('Release Lot')}
                            </Button>
                        </form>
                                    </TextileFormCard>
                                ),
                            }];
                            return (
                                <div className="space-y-6">
                                    <TextileWorkflowSteps steps={steps} openId={openStep} onOpenChange={setOpenStep} records={
                                        <TextileDataTableSection title={t('Hold and Release Records')}>
                                            <TextileDataTableCard
                                                data={holds}
                                                columns={createTextileWorkflowColumns(t)}
                                                emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No hold/release records found')} description={t('Hold and release events will appear here.')} />}
                                            />
                                        </TextileDataTableSection>
                                    } />
                                </div>
                            );
                        }

                        case 'certificates': {
                            const steps: WorkflowStep[] = [{
                                id: 'certificates',
                                title: t('Quality Certificates'),
                                icon: Award,
                                count: certificates.length,
                                form: (
                                    <TextileFormCard title={t('Quality Certificates')} icon={Award}>
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                certificateForm.post(route('textile.quality.certificates.store'), {
                                    onSuccess: () => certificateForm.reset('inspection_id', 'lot_reference', 'certificate_number', 'notes'),
                                });
                            }}
                        >
                            <SelectField label={t('Source Type')} value={certificateForm.data.source_reference_type} onChange={(value: string) => certificateForm.setData('source_reference_type', value)} options={resolvedSourceTypeOptions} includeEmpty emptyLabel={t('Select source type')} helperText={t('Source types are managed from Master Setup > Quality Setup > Source Types.')} />
                            <SelectField label={t('Source Action')} value={certificateForm.data.source_action} onChange={(value: string) => certificateForm.setData('source_action', value)} options={resolvedSourceActionOptions} includeEmpty emptyLabel={t('Select source action')} helperText={t('Source actions are managed from Master Setup > Quality Setup > Source Actions.')} />
                            <SelectField
                                label={t('Inspection')}
                                value={certificateForm.data.inspection_id}
                                onChange={(value: string) => certificateForm.setData('inspection_id', value)}
                                options={certificateInspectionOptions}
                                includeEmpty
                                emptyLabel={t('Select approved inspection')}
                                disabled={certificateInspectionOptions.length === 0}
                                disabledReason={t('No approved inspections available for certificate issuance.')}
                            />
                            <SelectField
                                label={t('Lot Reference')}
                                value={certificateForm.data.lot_reference}
                                onChange={(value: string) => certificateForm.setData('lot_reference', value)}
                                options={resolvedLotReferenceOptions}
                                includeEmpty
                                emptyLabel={t('Select lot reference')}
                                required
                            />
                            <Field label={t('Certificate Number')} value={certificateForm.data.certificate_number} onChange={(value: string) => certificateForm.setData('certificate_number', value)} required />
                            <Field label={t('Notes')} value={certificateForm.data.notes} onChange={(value: string) => certificateForm.setData('notes', value)} />
                            <Button type="submit" disabled={certificateForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Certificate')}
                            </Button>
                        </form>
                                    </TextileFormCard>
                                ),
                            }];
                            return (
                                <div className="space-y-6">
                                    <TextileWorkflowSteps steps={steps} openId={openStep} onOpenChange={setOpenStep} records={
                                        <TextileDataTableSection title={t('Quality Certificate Records')}>
                                            <TextileDataTableCard
                                                data={certificates}
                                                columns={createTextileWorkflowColumns(t, {
                                                    actions: createTextileWorkflowActions([
                                                        {
                                                            statuses: textileActionableStatuses.draft,
                                                            actions: [
                                                                { label: t('Issue Certificate'), icon: Award, onClick: (row: WorkflowDocument) => issueCertificate(row.id) },
                                                            ],
                                                        },
                                                    ]),
                                                }).concat([
                                                    { key: 'certificate_number', header: t('Certificate Number'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.certificate_number ?? '-') },
                                                    { key: 'inspection_id', header: t('Inspection ID'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.inspection_id ? String(row.metadata.inspection_id) : '-' },
                                                ])}
                                                emptyState={<NoRecordsFound icon={Award} title={t('No quality certificates found')} description={t('Create certificates from approved inspections.')} />}
                                            />
                                        </TextileDataTableSection>
                                    } />
                                </div>
                            );
                        }

                        default:
                            return null;
                    }
                }}
            </TextileWorkspace>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null | undefined) {
    return value || '-';
}
