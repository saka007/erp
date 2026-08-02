import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Factory, Plus, Check } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { buildUnitOptions, textileMachineTypeOptions, textileSourceTypeOptions } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';

interface WorkflowDocument {
    id: number;
    document_number: string;
    source_reference_id?: number | null;
    source_reference_type?: string | null;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    created_at?: string | null;
}

export default function Index({
    warpPlans,
    yarnAllocations,
    warpSheets,
    warpProductions,
    sizingRecipes,
    loomMasters,
    beams,
    beamIssues,
    beamReturns,
    productionBatches,
    weavingOutputs,
    wastes,
    reworks,
    sourceTypeOptions,
    machineTypeOptions,
    unitOptions,
}: {
    warpPlans: WorkflowDocument[];
    yarnAllocations: WorkflowDocument[];
    warpSheets: WorkflowDocument[];
    warpProductions: WorkflowDocument[];
    sizingRecipes: WorkflowDocument[];
    loomMasters: WorkflowDocument[];
    beams: WorkflowDocument[];
    beamIssues: WorkflowDocument[];
    beamReturns: WorkflowDocument[];
    productionBatches: WorkflowDocument[];
    weavingOutputs: WorkflowDocument[];
    wastes: WorkflowDocument[];
    reworks: WorkflowDocument[];
    sourceTypeOptions: string[];
    machineTypeOptions: string[];
    unitOptions: string[];
}) {
    const { t } = useTranslation();
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const validSections = new Set(['warp-planning', 'beam-batch', 'loom-management', 'weaving-output', 'waste', 'rework']);
    const activeSection = sectionParam && validSections.has(sectionParam) ? sectionParam : 'warp-planning';

    const warpPlanForm = useForm({
        source_reference_type: 'inventory_lot',
        source_reference_id: '',
        source_action: 'warp_plan',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'kg',
    });

    const yarnAllocationForm = useForm({ warp_plan_id: '' });
    const warpSheetForm = useForm({ yarn_allocation_id: '' });
    const warpProductionForm = useForm({ warp_sheet_id: '' });
    const sizingRecipeForm = useForm({ warp_production_id: '' });
    const sizingRecipeBeamForm = useForm({ sizing_recipe_id: '' });
    const beamIssueForm = useForm({ beam_id: '' });
    const beamReturnForm = useForm({ beam_issue_id: '' });
    const loomMasterForm = useForm({
        source_reference_type: 'factory',
        source_reference_id: '1',
        source_action: 'loom_register',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'rpm',
    });

    const beamForm = useForm({
        source_reference_type: 'sales_order',
        source_reference_id: '',
        source_action: 'beam_prepare',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
    });

    const batchForm = useForm({ beam_id: '' });
    const weavingOutputForm = useForm({ batch_id: '', quantity: '', unit: 'mtr' });
    const wasteForm = useForm({ batch_id: '', quantity: '', unit: 'mtr' });
    const reworkForm = useForm({ weaving_output_id: '', quantity: '', unit: 'mtr' });
    const approvedWarpPlans = warpPlans.filter((row) => row.status === 'approved');
    const actionableYarnAllocations = yarnAllocations.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableWarpSheets = warpSheets.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableWarpProductions = warpProductions.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableSizingRecipes = sizingRecipes.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const approvedBeams = beams.filter((row) => row.status === 'approved');
    const actionableBeamIssues = beamIssues.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const releasedBatches = productionBatches.filter((row) => row.status === 'released');
    const resolvedSourceTypeOptions = sourceTypeOptions.length > 0
        ? sourceTypeOptions.map((value) => ({ value, label: value }))
        : textileSourceTypeOptions;
    const resolvedMachineTypeOptions = machineTypeOptions.length > 0
        ? machineTypeOptions.map((value) => ({ value, label: value }))
        : textileMachineTypeOptions;
    const resolvedUnitOptions = buildUnitOptions(unitOptions);

    const allDocuments = [...warpPlans, ...yarnAllocations, ...warpSheets, ...warpProductions, ...sizingRecipes, ...loomMasters, ...beams, ...beamIssues, ...beamReturns, ...productionBatches, ...weavingOutputs, ...wastes, ...reworks];
    const draftCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;

    const toNumber = (value: string | number | null | undefined) => {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        const parsed = Number.parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const beamIssueById = new Map<number, WorkflowDocument>(beamIssues.map((row) => [row.id, row]));
    const beamById = new Map<number, WorkflowDocument>(beams.map((row) => [row.id, row]));
    const issuedByBeamId = new Map<number, number>();
    const returnedByBeamId = new Map<number, number>();

    beamIssues.forEach((issue) => {
        const beamId = issue.source_reference_id;
        if (!beamId) {
            return;
        }

        issuedByBeamId.set(beamId, (issuedByBeamId.get(beamId) ?? 0) + toNumber(issue.quantity));
    });

    beamReturns.forEach((beamReturn) => {
        const beamIssueId = beamReturn.source_reference_id;
        if (!beamIssueId) {
            return;
        }

        const issue = beamIssueById.get(beamIssueId);
        const beamId = issue?.source_reference_id;
        if (!beamId) {
            return;
        }

        returnedByBeamId.set(beamId, (returnedByBeamId.get(beamId) ?? 0) + toNumber(beamReturn.quantity));
    });

    const remainingBeamRows = beams.map((beam) => {
        const beamQty = toNumber(beam.quantity);
        const issuedQty = issuedByBeamId.get(beam.id) ?? 0;
        const returnedQty = returnedByBeamId.get(beam.id) ?? 0;

        return {
            id: beam.id,
            document_number: beam.document_number,
            issued_quantity: issuedQty.toFixed(2),
            returned_quantity: returnedQty.toFixed(2),
            remaining_quantity: (beamQty - issuedQty + returnedQty).toFixed(2),
            unit: beam.unit ?? '-',
        };
    });

    const beamHistoryRows = [
        ...beamIssues.map((issue) => {
            const beam = issue.source_reference_id ? beamById.get(issue.source_reference_id) : null;

            return {
                id: issue.id,
                event_type: t('Issue'),
                event_number: issue.document_number,
                beam_number: beam?.document_number ?? '-',
                source_number: beam?.document_number ?? '-',
                quantity: issue.quantity,
                unit: issue.unit ?? '-',
                status: issue.status,
                event_time: issue.created_at ?? '-',
            };
        }),
        ...beamReturns.map((beamReturn) => {
            const beamIssue = beamReturn.source_reference_id ? beamIssueById.get(beamReturn.source_reference_id) : null;
            const beam = beamIssue?.source_reference_id ? beamById.get(beamIssue.source_reference_id) : null;

            return {
                id: beamReturn.id,
                event_type: t('Return'),
                event_number: beamReturn.document_number,
                beam_number: beam?.document_number ?? '-',
                source_number: beamIssue?.document_number ?? '-',
                quantity: beamReturn.quantity,
                unit: beamReturn.unit ?? '-',
                status: beamReturn.status,
                event_time: beamReturn.created_at ?? '-',
            };
        }),
    ].sort((left, right) => right.id - left.id);

    const approveBeam = (id: number) => {
        router.post(route('textile.manufacturing.beams.approve'), { beam_id: id }, { preserveScroll: true });
    };

    const approveWarpPlan = (id: number) => {
        router.post(route('textile.manufacturing.warp-plans.approve'), { warp_plan_id: id }, { preserveScroll: true });
    };

    const releaseBatch = (id: number) => {
        router.post(route('textile.manufacturing.batches.release'), { batch_id: id }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Manufacturing') }]} pageTitle={t('Textile Manufacturing')}>
            <Head title={t('Textile Manufacturing')} />

            <TextileKpiOverview
                title={t('Manufacturing Overview')}
                className="mb-6"
                items={[
                    { label: t('Total Documents'), value: allDocuments.length, hint: t('Warp + Yarn Allocation + Warp Sheet + Warp Production + Sizing Recipe + Loom + Beam + Batch + Output + Waste + Rework') },
                    { label: t('Draft'), value: draftCount, hint: t('Waiting for review') },
                    { label: t('Approved'), value: approvedCount, hint: t('Ready for release actions') },
                    { label: t('Released'), value: releasedCount, hint: t('Ready for production execution') },
                ]}
            />

            <Tabs
                value={activeSection}
                onValueChange={(value) => router.get(route('textile.manufacturing.index', { section: value }), {}, { preserveState: true, replace: true })}
                className="space-y-6"
            >
                <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-6">
                    <TabsTrigger value="warp-planning">{t('Warp Planning')}</TabsTrigger>
                    <TabsTrigger value="beam-batch">{t('Beam and Batch')}</TabsTrigger>
                    <TabsTrigger value="loom-management">{t('Loom Management')}</TabsTrigger>
                    <TabsTrigger value="weaving-output">{t('Weaving Output')}</TabsTrigger>
                    <TabsTrigger value="waste">{t('Waste')}</TabsTrigger>
                    <TabsTrigger value="rework">{t('Rework')}</TabsTrigger>
                </TabsList>
                <TabsContent value="warp-planning">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Create Warp Plan')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            warpPlanForm.post(route('textile.manufacturing.warp-plans.store'), {
                                onSuccess: () => warpPlanForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity'),
                            });
                        }}>
                            <SelectField
                                label={t('Source Type')}
                                value={warpPlanForm.data.source_reference_type}
                                onChange={(v) => warpPlanForm.setData('source_reference_type', v)}
                                options={resolvedSourceTypeOptions}
                                includeEmpty
                                emptyLabel={t('Select source type')}
                                helperText={t('Source types are managed from Textile Master Setup.')}
                                required
                            />
                            <Field label={t('Source ID')} type="number" value={warpPlanForm.data.source_reference_id} onChange={(v) => warpPlanForm.setData('source_reference_id', v)} required />
                            <Field label={t('Source Action')} value={warpPlanForm.data.source_action} onChange={(v) => warpPlanForm.setData('source_action', v)} required />
                            <Field label={t('Party')} value={warpPlanForm.data.party_name} onChange={(v) => warpPlanForm.setData('party_name', v)} />
                            <Field label={t('Lot Reference')} value={warpPlanForm.data.lot_reference} onChange={(v) => warpPlanForm.setData('lot_reference', v)} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={warpPlanForm.data.quantity} onChange={(v) => warpPlanForm.setData('quantity', v)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={warpPlanForm.data.unit}
                                    onChange={(v) => warpPlanForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                            </div>
                            <Button type="submit" disabled={warpPlanForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Warp Plan')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Allocate Yarn from Approved Warp Plan')} icon={Check}>
                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            yarnAllocationForm.post(route('textile.manufacturing.yarn-allocations.store'), {
                                onSuccess: () => yarnAllocationForm.reset('warp_plan_id'),
                            });
                        }}>
                            <SelectField
                                label={t('Approved Warp Plan')}
                                value={yarnAllocationForm.data.warp_plan_id}
                                onChange={(v) => yarnAllocationForm.setData('warp_plan_id', v)}
                                options={createTextileWorkflowSelectOptions(approvedWarpPlans)}
                                includeEmpty
                                emptyLabel={t('Select approved warp plan')}
                                helperText={t('Only approved warp plans are listed.')}
                                disabled={approvedWarpPlans.length === 0}
                                disabledReason={t('No approved warp plan found. Approve a warp plan first.')}
                                required
                            />
                            <Button type="submit" disabled={yarnAllocationForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Allocate Yarn')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Warp Sheet from Yarn Allocation')} icon={Check}>
                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            warpSheetForm.post(route('textile.manufacturing.warp-sheets.store'), {
                                onSuccess: () => warpSheetForm.reset('yarn_allocation_id'),
                            });
                        }}>
                            <SelectField
                                label={t('Yarn Allocation')}
                                value={warpSheetForm.data.yarn_allocation_id}
                                onChange={(v) => warpSheetForm.setData('yarn_allocation_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableYarnAllocations)}
                                includeEmpty
                                emptyLabel={t('Select yarn allocation')}
                                helperText={t('Only completed yarn allocations are listed.')}
                                disabled={actionableYarnAllocations.length === 0}
                                disabledReason={t('No completed yarn allocation found. Create yarn allocation first.')}
                                required
                            />
                            <Button type="submit" disabled={warpSheetForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Warp Sheet')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Warp Production from Warp Sheet')} icon={Check}>
                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            warpProductionForm.post(route('textile.manufacturing.warp-productions.store'), {
                                onSuccess: () => warpProductionForm.reset('warp_sheet_id'),
                            });
                        }}>
                            <SelectField
                                label={t('Warp Sheet')}
                                value={warpProductionForm.data.warp_sheet_id}
                                onChange={(v) => warpProductionForm.setData('warp_sheet_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableWarpSheets)}
                                includeEmpty
                                emptyLabel={t('Select warp sheet')}
                                helperText={t('Only completed warp sheets are listed.')}
                                disabled={actionableWarpSheets.length === 0}
                                disabledReason={t('No completed warp sheet found. Create warp sheet first.')}
                                required
                            />
                            <Button type="submit" disabled={warpProductionForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Warp Production')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Sizing Recipe from Warp Production')} icon={Check}>
                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            sizingRecipeForm.post(route('textile.manufacturing.sizing-recipes.store'), {
                                onSuccess: () => sizingRecipeForm.reset('warp_production_id'),
                            });
                        }}>
                            <SelectField
                                label={t('Warp Production')}
                                value={sizingRecipeForm.data.warp_production_id}
                                onChange={(v) => sizingRecipeForm.setData('warp_production_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableWarpProductions)}
                                includeEmpty
                                emptyLabel={t('Select warp production')}
                                helperText={t('Only completed warp production entries are listed.')}
                                disabled={actionableWarpProductions.length === 0}
                                disabledReason={t('No completed warp production found. Create warp production first.')}
                                required
                            />
                            <Button type="submit" disabled={sizingRecipeForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Sizing Recipe')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileDataTableSection
                        title={t('Warp Plan Records')}
                        data={warpPlans}
                        columns={createTextileWorkflowColumns(t, {
                            actions: createTextileWorkflowActions([
                                {
                                    statuses: textileActionableStatuses.draft,
                                    actions: [{ label: t('Approve'), icon: Check, onClick: (row) => approveWarpPlan(row.id) }],
                                },
                            ]),
                        })}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No warp plans found')} description={t('Create warp plans before yarn allocation.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Yarn Allocation Records')}
                        data={yarnAllocations}
                        columns={createTextileWorkflowColumns(t)}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No yarn allocations found')} description={t('Allocate yarn from approved warp plans.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Warp Sheet Records')}
                        data={warpSheets}
                        columns={createTextileWorkflowColumns(t)}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No warp sheets found')} description={t('Create warp sheets from completed yarn allocations.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Warp Production Records')}
                        data={warpProductions}
                        columns={createTextileWorkflowColumns(t)}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No warp production found')} description={t('Create warp production entries from completed warp sheets.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Sizing Recipe Records')}
                        data={sizingRecipes}
                        columns={createTextileWorkflowColumns(t)}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No sizing recipe found')} description={t('Create sizing recipe entries from completed warp production.')} />}
                    />
                </div>
                </TabsContent>

                <TabsContent value="beam-batch">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Create Beam')} icon={Factory}>
                            <form className="space-y-3" onSubmit={(e) => {
                                e.preventDefault();
                                beamForm.post(route('textile.manufacturing.beams.store'), {
                                    onSuccess: () => beamForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}>
                                <SelectField
                                    label={t('Source Type')}
                                    value={beamForm.data.source_reference_type}
                                    onChange={(v) => beamForm.setData('source_reference_type', v)}
                                    options={resolvedSourceTypeOptions}
                                    includeEmpty
                                    emptyLabel={t('Select source type')}
                                    helperText={t('Source types are managed from Textile Master Setup.')}
                                    required
                                />
                                <Field label={t('Source ID')} type="number" value={beamForm.data.source_reference_id} onChange={(v) => beamForm.setData('source_reference_id', v)} required />
                                <Field label={t('Source Action')} value={beamForm.data.source_action} onChange={(v) => beamForm.setData('source_action', v)} required />
                                <Field label={t('Party')} value={beamForm.data.party_name} onChange={(v) => beamForm.setData('party_name', v)} />
                                <Field label={t('Lot Reference')} value={beamForm.data.lot_reference} onChange={(v) => beamForm.setData('lot_reference', v)} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Quantity')} type="number" value={beamForm.data.quantity} onChange={(v) => beamForm.setData('quantity', v)} required />
                                    <SelectField
                                        label={t('Unit')}
                                        value={beamForm.data.unit}
                                        onChange={(v) => beamForm.setData('unit', v)}
                                        options={resolvedUnitOptions}
                                        includeEmpty
                                        emptyLabel={t('Select unit')}
                                        helperText={t('Units are derived from Unit Conversion master.')}
                                    />
                                </div>
                                <Button type="submit" disabled={beamForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Beam')}</Button>
                            </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Batch from Approved Beam')} icon={Check}>
                            <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                                e.preventDefault();
                                batchForm.post(route('textile.manufacturing.batches.store'), {
                                    onSuccess: () => batchForm.reset('beam_id'),
                                });
                            }}>
                                <SelectField
                                    label={t('Create Batch from Approved Beam')}
                                    value={batchForm.data.beam_id}
                                    onChange={(v) => batchForm.setData('beam_id', v)}
                                    options={createTextileWorkflowSelectOptions(approvedBeams)}
                                    includeEmpty
                                    emptyLabel={t('Select approved beam')}
                                    helperText={t('Only approved beams are listed.')}
                                    disabled={approvedBeams.length === 0}
                                    disabledReason={t('No approved beam found. Approve a beam first.')}
                                    required
                                />
                                <Button type="submit" disabled={batchForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Batch')}</Button>
                            </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Beam from Sizing Recipe')} icon={Check}>
                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            sizingRecipeBeamForm.post(route('textile.manufacturing.beams.from-sizing-recipe'), {
                                onSuccess: () => sizingRecipeBeamForm.reset('sizing_recipe_id'),
                            });
                        }}>
                            <SelectField
                                label={t('From Sizing Recipe')}
                                value={sizingRecipeBeamForm.data.sizing_recipe_id}
                                onChange={(v) => sizingRecipeBeamForm.setData('sizing_recipe_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableSizingRecipes)}
                                includeEmpty
                                emptyLabel={t('Select sizing recipe')}
                                helperText={t('Only completed sizing recipes are listed.')}
                                disabled={actionableSizingRecipes.length === 0}
                                disabledReason={t('No completed sizing recipe found. Create sizing recipe first.')}
                                required
                            />
                            <Button type="submit" disabled={sizingRecipeBeamForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Beam')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Beam Issue')} icon={Check}>
                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            beamIssueForm.post(route('textile.manufacturing.beam-issues.store'), {
                                onSuccess: () => beamIssueForm.reset('beam_id'),
                            });
                        }}>
                            <SelectField
                                label={t('From Approved Beam')}
                                value={beamIssueForm.data.beam_id}
                                onChange={(v) => beamIssueForm.setData('beam_id', v)}
                                options={createTextileWorkflowSelectOptions(approvedBeams)}
                                includeEmpty
                                emptyLabel={t('Select approved beam')}
                                helperText={t('Only approved beams are listed.')}
                                disabled={approvedBeams.length === 0}
                                disabledReason={t('No approved beam found. Approve a beam first.')}
                                required
                            />
                            <Button type="submit" disabled={beamIssueForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Beam Issue')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Beam Return')} icon={Check}>
                        <form className="grid grid-cols-[1fr_auto] gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            beamReturnForm.post(route('textile.manufacturing.beam-returns.store'), {
                                onSuccess: () => beamReturnForm.reset('beam_issue_id'),
                            });
                        }}>
                            <SelectField
                                label={t('From Beam Issue')}
                                value={beamReturnForm.data.beam_issue_id}
                                onChange={(v) => beamReturnForm.setData('beam_issue_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableBeamIssues)}
                                includeEmpty
                                emptyLabel={t('Select beam issue')}
                                helperText={t('Only completed beam issues are listed.')}
                                disabled={actionableBeamIssues.length === 0}
                                disabledReason={t('No completed beam issue found. Create beam issue first.')}
                                required
                            />
                            <Button type="submit" disabled={beamReturnForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Create Beam Return')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileDataTableSection
                        title={t('Beam Records')}
                        data={beams}
                        columns={createTextileWorkflowColumns(t, {
                            actions: createTextileWorkflowActions([
                                {
                                    statuses: textileActionableStatuses.draft,
                                    actions: [{ label: t('Approve'), icon: Check, onClick: (row) => approveBeam(row.id) }],
                                },
                            ]),
                        })}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No beams found')} description={t('Create beams from approved operational sources.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Production Batch Records')}
                        data={productionBatches}
                        columns={createTextileWorkflowColumns(t, {
                            actions: createTextileWorkflowActions([
                                {
                                    statuses: textileActionableStatuses.draftOrApproved,
                                    actions: [{ label: t('Release'), icon: Check, onClick: (row) => releaseBatch(row.id) }],
                                },
                            ]),
                        })}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No production batches found')} description={t('Create batches from approved beams.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Beam Issue Records')}
                        data={beamIssues}
                        columns={createTextileWorkflowColumns(t)}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No beam issues found')} description={t('Create beam issue entries from approved beams.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Beam Return Records')}
                        data={beamReturns}
                        columns={createTextileWorkflowColumns(t)}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No beam returns found')} description={t('Create beam return entries from completed beam issues.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Remaining Beam Summary')}
                        data={remainingBeamRows}
                        columns={[
                            { key: 'document_number', header: t('Beam') },
                            { key: 'issued_quantity', header: t('Issued Qty') },
                            { key: 'returned_quantity', header: t('Returned Qty') },
                            { key: 'remaining_quantity', header: t('Remaining Qty') },
                            { key: 'unit', header: t('Unit') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No beam summary found')} description={t('Create beams and beam issues to track remaining quantity.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Beam History')}
                        data={beamHistoryRows}
                        columns={[
                            { key: 'event_type', header: t('Event') },
                            { key: 'event_number', header: t('Document') },
                            { key: 'beam_number', header: t('Beam') },
                            { key: 'source_number', header: t('Source') },
                            { key: 'quantity', header: t('Qty') },
                            { key: 'unit', header: t('Unit') },
                            { key: 'status', header: t('Status') },
                            { key: 'event_time', header: t('Date/Time') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No beam history found')} description={t('Create beam issues and returns to build beam history.')} />}
                    />
                </div>
                </TabsContent>

                <TabsContent value="loom-management">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Register Loom Master')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            loomMasterForm.post(route('textile.manufacturing.loom-masters.store'), {
                                onSuccess: () => loomMasterForm.reset('party_name', 'lot_reference', 'quantity'),
                            });
                        }}>
                            <SelectField
                                label={t('Source Type')}
                                value={loomMasterForm.data.source_reference_type}
                                onChange={(v) => loomMasterForm.setData('source_reference_type', v)}
                                options={resolvedSourceTypeOptions}
                                includeEmpty
                                emptyLabel={t('Select source type')}
                                helperText={t('Source types are managed from Textile Master Setup.')}
                                required
                            />
                            <Field label={t('Source ID')} type="number" value={loomMasterForm.data.source_reference_id} onChange={(v) => loomMasterForm.setData('source_reference_id', v)} required />
                            <Field label={t('Loom Number')} value={loomMasterForm.data.party_name} onChange={(v) => loomMasterForm.setData('party_name', v)} required />
                            <SelectField
                                label={t('Machine Type')}
                                value={loomMasterForm.data.lot_reference}
                                onChange={(v) => loomMasterForm.setData('lot_reference', v)}
                                options={resolvedMachineTypeOptions}
                                includeEmpty
                                emptyLabel={t('Select machine type')}
                                helperText={t('Machine types are managed from Textile Master Setup.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('RPM')} type="number" value={loomMasterForm.data.quantity} onChange={(v) => loomMasterForm.setData('quantity', v)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={loomMasterForm.data.unit}
                                    onChange={(v) => loomMasterForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                    required
                                />
                            </div>
                            <Button type="submit" disabled={loomMasterForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Register Loom')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileDataTableCard
                        data={loomMasters}
                        columns={createTextileWorkflowColumns(t)}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No loom masters found')} description={t('Register loom masters to start machine-wise planning.')} />}
                    />
                </div>
                </TabsContent>

                <TabsContent value="weaving-output">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Record Weaving Output')} icon={Factory}>
                            <form className="grid grid-cols-4 gap-3" onSubmit={(e) => {
                                e.preventDefault();
                                weavingOutputForm.post(route('textile.manufacturing.weaving-output.store'), {
                                    onSuccess: () => weavingOutputForm.reset('batch_id', 'quantity'),
                                });
                            }}>
                                <SelectField
                                    label={t('Batch')}
                                    value={weavingOutputForm.data.batch_id}
                                    onChange={(v) => weavingOutputForm.setData('batch_id', v)}
                                    options={createTextileWorkflowSelectOptions(releasedBatches)}
                                    includeEmpty
                                    emptyLabel={t('Select released batch')}
                                    helperText={t('Only released production batches are listed.')}
                                    disabled={releasedBatches.length === 0}
                                    disabledReason={t('No released batch found. Release a production batch first.')}
                                    required
                                />
                                <Field label={t('Output Qty')} type="number" value={weavingOutputForm.data.quantity} onChange={(v) => weavingOutputForm.setData('quantity', v)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={weavingOutputForm.data.unit}
                                    onChange={(v) => weavingOutputForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                                <Button type="submit" disabled={weavingOutputForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Record Output')}</Button>
                            </form>
                    </TextileFormCard>
                    <TextileDataTableCard data={weavingOutputs} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No weaving output found')} description={t('Record weaving output from released batches.')} />} />
                </div>
                </TabsContent>

                <TabsContent value="waste">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Record Waste')} icon={Factory}>
                            <form className="grid grid-cols-4 gap-3" onSubmit={(e) => {
                                e.preventDefault();
                                wasteForm.post(route('textile.manufacturing.waste.store'), {
                                    onSuccess: () => wasteForm.reset('batch_id', 'quantity'),
                                });
                            }}>
                                <SelectField
                                    label={t('Batch')}
                                    value={wasteForm.data.batch_id}
                                    onChange={(v) => wasteForm.setData('batch_id', v)}
                                    options={createTextileWorkflowSelectOptions(releasedBatches)}
                                    includeEmpty
                                    emptyLabel={t('Select released batch')}
                                    helperText={t('Only released production batches are listed.')}
                                    disabled={releasedBatches.length === 0}
                                    disabledReason={t('No released batch found. Release a production batch first.')}
                                    required
                                />
                                <Field label={t('Waste Qty')} type="number" value={wasteForm.data.quantity} onChange={(v) => wasteForm.setData('quantity', v)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={wasteForm.data.unit}
                                    onChange={(v) => wasteForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                                <Button type="submit" variant="outline" disabled={wasteForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Record Waste')}</Button>
                            </form>
                    </TextileFormCard>
                    <TextileDataTableCard data={wastes} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No waste records found')} description={t('Record waste from released batches.')} />} />
                </div>
                </TabsContent>

                <TabsContent value="rework">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Record Rework')} icon={Factory}>
                            <form className="grid grid-cols-4 gap-3" onSubmit={(e) => {
                                e.preventDefault();
                                reworkForm.post(route('textile.manufacturing.rework.store'), {
                                    onSuccess: () => reworkForm.reset('weaving_output_id', 'quantity'),
                                });
                            }}>
                                <SelectField
                                    label={t('Weaving Output')}
                                    value={reworkForm.data.weaving_output_id}
                                    onChange={(v) => reworkForm.setData('weaving_output_id', v)}
                                    options={createTextileWorkflowSelectOptions(weavingOutputs)}
                                    includeEmpty
                                    emptyLabel={t('Select weaving output')}
                                    helperText={t('Select a weaving output to create a rework entry.')}
                                    disabled={weavingOutputs.length === 0}
                                    disabledReason={t('No weaving output found. Record weaving output first.')}
                                    required
                                />
                                <Field label={t('Rework Qty')} type="number" value={reworkForm.data.quantity} onChange={(v) => reworkForm.setData('quantity', v)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={reworkForm.data.unit}
                                    onChange={(v) => reworkForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                                <Button type="submit" variant="outline" disabled={reworkForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Record Rework')}</Button>
                            </form>
                    </TextileFormCard>
                    <TextileDataTableCard data={reworks} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No rework records found')} description={t('Record rework linked to weaving output.')} />} />
                </div>
                </TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}
