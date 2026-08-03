import { Head, router, useForm, usePage } from '@inertiajs/react';
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
import { buildUnitOptions, formatTextileLabel, formatTextileOptionLabel, textileMachineTypeOptions, textileSourceTypeOptions } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';
import { PageProps } from '@/types';

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
    metadata?: Record<string, unknown>;
    created_at?: string | null;
}

export default function Index({
    warpPlans,
    yarnAllocations,
    warpSheets,
    warpProductions,
    sizingRecipes,
    chemicalConsumptions,
    loomMasters,
    loomBreakdowns,
    loomMaintenances,
    productionCalendars,
    capacityPlans,
    shiftPlans,
    machinePlans,
    materialPlans,
    productionSchedules,
    beams,
    beamIssues,
    beamReturns,
    beamInspections,
    beamCosts,
    productionBatches,
    weavingOutputs,
    shiftProductions,
    takhaEntries,
    loomEfficiencies,
    operatorEfficiencies,
    machineDowntimes,
    productionCosts,
    greyFabricRolls,
    greyRollHistories,
    wastes,
    reworks,
    sourceTypeOptions,
    sourceActionOptions,
    machineTypeOptions,
    shedTypeOptions,
    loomStatusOptions,
    breakdownReasonOptions,
    maintenanceTypeOptions,
    dayTypeOptions,
    shiftOptions,
    unitOptions,
    costCenterOptions,
    costTypeOptions,
    inspectionResultOptions,
    fabricDefectOptions,
    fabricGradeOptions,
    warehouseOptions,
    chemicalOptions,
    operatorOptions,
    partyOptions,
    lotReferenceOptions,
}: {
    warpPlans: WorkflowDocument[];
    yarnAllocations: WorkflowDocument[];
    warpSheets: WorkflowDocument[];
    warpProductions: WorkflowDocument[];
    sizingRecipes: WorkflowDocument[];
    chemicalConsumptions: WorkflowDocument[];
    loomMasters: WorkflowDocument[];
    loomBreakdowns: WorkflowDocument[];
    loomMaintenances: WorkflowDocument[];
    productionCalendars: WorkflowDocument[];
    capacityPlans: WorkflowDocument[];
    shiftPlans: WorkflowDocument[];
    machinePlans: WorkflowDocument[];
    materialPlans: WorkflowDocument[];
    productionSchedules: WorkflowDocument[];
    beams: WorkflowDocument[];
    beamIssues: WorkflowDocument[];
    beamReturns: WorkflowDocument[];
    beamInspections: WorkflowDocument[];
    beamCosts: WorkflowDocument[];
    productionBatches: WorkflowDocument[];
    weavingOutputs: WorkflowDocument[];
    shiftProductions: WorkflowDocument[];
    takhaEntries: WorkflowDocument[];
    loomEfficiencies: WorkflowDocument[];
    operatorEfficiencies: WorkflowDocument[];
    machineDowntimes: WorkflowDocument[];
    productionCosts: WorkflowDocument[];
    greyFabricRolls: WorkflowDocument[];
    greyRollHistories: WorkflowDocument[];
    wastes: WorkflowDocument[];
    reworks: WorkflowDocument[];
    sourceTypeOptions: string[];
    sourceActionOptions: string[];
    machineTypeOptions: string[];
    shedTypeOptions: string[];
    loomStatusOptions: string[];
    breakdownReasonOptions: string[];
    maintenanceTypeOptions: string[];
    dayTypeOptions: string[];
    shiftOptions: string[];
    unitOptions: string[];
    costCenterOptions: Array<{ value: string; label: string }>;
    costTypeOptions: string[];
    inspectionResultOptions: string[];
    fabricDefectOptions: string[];
    fabricGradeOptions: string[];
    warehouseOptions: string[];
    chemicalOptions: string[];
    operatorOptions: string[];
    partyOptions: string[];
    lotReferenceOptions: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('manufacturing_'));
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const visibleSections = hasFineGrainedCapabilities
        ? [
            textileCapabilities.manufacturing_warping || textileCapabilities.manufacturing_sizing ? 'warp-planning' : null,
            textileCapabilities.manufacturing_beam ? 'beam-batch' : null,
            textileCapabilities.manufacturing_loom ? 'loom-management' : null,
            textileCapabilities.manufacturing_planning ? 'machine-planning' : null,
            textileCapabilities.manufacturing_weaving ? 'weaving-output' : null,
            textileCapabilities.manufacturing_waste ? 'waste' : null,
            textileCapabilities.manufacturing_rework ? 'rework' : null,
        ].filter((value): value is string => value !== null)
        : ['warp-planning', 'beam-batch', 'loom-management', 'machine-planning', 'weaving-output', 'waste', 'rework'];
    const validSections = new Set(visibleSections);
    const activeSection = sectionParam && validSections.has(sectionParam)
        ? sectionParam
        : (visibleSections[0] ?? 'warp-planning');

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
    const chemicalConsumptionForm = useForm({
        sizing_recipe_id: '',
        chemical_type: '',
        composition_percent: '',
        consumption_quantity: '',
        unit: 'kg',
        notes: '',
    });
    const sizingRecipeBeamForm = useForm({ sizing_recipe_id: '' });
    const beamIssueForm = useForm({ beam_id: '' });
    const beamReturnForm = useForm({ beam_issue_id: '' });
    const beamInspectionForm = useForm({ beam_id: '', inspection_result: '', remarks: '' });
    const beamCostForm = useForm({ beam_id: '', cost_type: '', cost_amount: '', quantity: '', unit: 'mtr', notes: '' });
    const loomMasterForm = useForm({
        source_reference_type: 'factory',
        source_reference_id: '1',
        source_action: 'loom_register',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'rpm',
        shed_type: '',
        width: '',
        loom_status: 'running',
        running_hours: '',
        idle_hours: '',
        operator_name: '',
    });
    const loomBreakdownForm = useForm({ loom_master_id: '', breakdown_reason: '', downtime_hours: '', unit: 'hour', operator_name: '', notes: '' });
    const loomMaintenanceForm = useForm({ loom_master_id: '', maintenance_type: '', maintenance_hours: '', unit: 'hour', operator_name: '', notes: '' });
    const productionCalendarForm = useForm({ plan_date: '', day_type: 'working', planned_shift: 'day', notes: '' });
    const capacityPlanForm = useForm({ loom_master_id: '', plan_date: '', available_hours: '', capacity_quantity: '', unit: 'mtr', efficiency_target: '', operator_name: '', notes: '' });
    const shiftPlanForm = useForm({ loom_master_id: '', plan_date: '', planned_shift: 'day', expected_hours: '', unit: 'hour', operator_name: '', notes: '' });
    const machinePlanForm = useForm({ loom_master_id: '', beam_id: '', planned_date: '', planned_shift: 'day', planned_quantity: '', unit: 'mtr', operator_name: '', notes: '' });
    const materialPlanForm = useForm({ beam_id: '', plan_date: '', required_quantity: '', unit: 'mtr', notes: '' });
    const productionScheduleForm = useForm({ loom_master_id: '', beam_id: '', scheduled_date: '', scheduled_shift: 'day', scheduled_quantity: '', unit: 'mtr', operator_name: '', notes: '' });

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
    const shiftProductionForm = useForm({ batch_id: '', loom_master_id: '', planned_shift: 'day', quantity: '', unit: 'mtr', operator_name: '', notes: '' });
    const takhaEntryForm = useForm({ weaving_output_id: '', takha_number: '', quantity: '', unit: 'mtr', operator_name: '', notes: '' });
    const loomEfficiencyForm = useForm({ loom_master_id: '', planned_shift: 'day', planned_quantity: '', actual_quantity: '', runtime_hours: '', downtime_hours: '', unit: 'mtr', operator_name: '', notes: '' });
    const operatorEfficiencyForm = useForm({ planned_shift: 'day', planned_quantity: '', actual_quantity: '', unit: 'mtr', operator_name: '', notes: '' });
    const machineDowntimeForm = useForm({ loom_master_id: '', planned_shift: 'day', downtime_reason: '', downtime_hours: '', unit: 'hour', operator_name: '', notes: '' });
    const productionCostForm = useForm({ weaving_output_id: '', cost_center_id: '', cost_amount: '', quantity: '', unit: 'mtr', operator_name: '', notes: '' });
    const greyFabricRollForm = useForm({ weaving_output_id: '', roll_number: '', roll_barcode: '', roll_qr_code: '', roll_weight: '', roll_length: '', gsm: '', width: '', defects: [] as string[], grade: '', warehouse: '', unit: 'mtr', operator_name: '', notes: '' });
    const greyFabricRollUpdateForm = useForm({ grey_roll_id: '', roll_weight: '', roll_length: '', gsm: '', width: '', defects: [] as string[], grade: '', warehouse: '', operator_name: '', notes: '' });
    const wasteForm = useForm({ batch_id: '', quantity: '', unit: 'mtr' });
    const reworkForm = useForm({ weaving_output_id: '', quantity: '', unit: 'mtr' });
    const approvedWarpPlans = warpPlans.filter((row) => row.status === 'approved');
    const actionableYarnAllocations = yarnAllocations.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableWarpSheets = warpSheets.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableWarpProductions = warpProductions.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableSizingRecipes = sizingRecipes.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableBeams = beams.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const actionableLoomMasters = loomMasters.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const approvedBeams = beams.filter((row) => row.status === 'approved');
    const actionableBeamIssues = beamIssues.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const releasedBatches = productionBatches.filter((row) => row.status === 'released');
    const completedWeavingOutputs = weavingOutputs.filter((row) => ['approved', 'released', 'closed'].includes(row.status));
    const resolvedSourceTypeOptions = sourceTypeOptions.length > 0
        ? sourceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : textileSourceTypeOptions;
    const resolvedSourceActionOptions = sourceActionOptions.length > 0
        ? sourceActionOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : [
            { value: 'warp_plan', label: formatTextileOptionLabel('warp_plan') },
            { value: 'beam_prepare', label: formatTextileOptionLabel('beam_prepare') },
            { value: 'loom_register', label: formatTextileOptionLabel('loom_register') },
        ];
    const resolvedPartyOptions = partyOptions
        .map((value) => ({ value, label: value }));
    const resolvedLotReferenceOptions = lotReferenceOptions
        .map((value) => ({ value, label: value }));
    const resolvedMachineTypeOptions = machineTypeOptions.length > 0
        ? machineTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }))
        : textileMachineTypeOptions;
    const resolvedShedTypeOptions = shedTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedLoomStatusOptions = loomStatusOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedBreakdownReasonOptions = breakdownReasonOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedMaintenanceTypeOptions = maintenanceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedDayTypeOptions = dayTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedShiftOptions = shiftOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedUnitOptions = buildUnitOptions(unitOptions);
    const resolvedCostCenterOptions = costCenterOptions;
    const resolvedFabricDefectOptions = fabricDefectOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedFabricGradeOptions = fabricGradeOptions.map((value) => ({ value, label: value }));
    const resolvedWarehouseOptions = warehouseOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedChemicalOptions = chemicalOptions.map((value) => ({ value, label: value }));
    const resolvedCostTypeOptions = costTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedInspectionResultOptions = inspectionResultOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedOperatorOptions = operatorOptions.map((value) => ({ value, label: value }));

    const allDocuments = [...warpPlans, ...yarnAllocations, ...warpSheets, ...warpProductions, ...sizingRecipes, ...chemicalConsumptions, ...loomMasters, ...loomBreakdowns, ...loomMaintenances, ...productionCalendars, ...capacityPlans, ...shiftPlans, ...machinePlans, ...materialPlans, ...productionSchedules, ...beams, ...beamIssues, ...beamReturns, ...beamInspections, ...beamCosts, ...productionBatches, ...weavingOutputs, ...shiftProductions, ...takhaEntries, ...loomEfficiencies, ...operatorEfficiencies, ...machineDowntimes, ...productionCosts, ...greyFabricRolls, ...greyRollHistories, ...wastes, ...reworks];
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
                    { label: t('Chemical Records'), value: chemicalConsumptions.length, hint: t('Sizing chemical usage entries') },
                    { label: t('Loom Breakdowns'), value: loomBreakdowns.length, hint: t('Loom stoppage and downtime entries') },
                    { label: t('Loom Maintenance'), value: loomMaintenances.length, hint: t('Preventive/corrective maintenance entries') },
                    { label: t('Planning Records'), value: productionCalendars.length + capacityPlans.length + shiftPlans.length + machinePlans.length + materialPlans.length + productionSchedules.length, hint: t('Calendar, capacity, shift, machine, material, and schedule plans') },
                    { label: t('Machine Plans'), value: machinePlans.length, hint: t('Loom to beam planning assignments') },
                    { label: t('Shift Production'), value: shiftProductions.length, hint: t('Shift-wise weaving execution entries') },
                    { label: t('Production Cost Records'), value: productionCosts.length, hint: t('Weaving cost capture entries') },
                    { label: t('Beam Inspections'), value: beamInspections.length, hint: t('Sizing and beam quality checkpoints') },
                    { label: t('Beam Cost Records'), value: beamCosts.length, hint: t('Sizing and beam cost capture entries') },
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
                <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-7">
                    {validSections.has('warp-planning') ? <TabsTrigger value="warp-planning">{t('Warp Planning')}</TabsTrigger> : null}
                    {validSections.has('beam-batch') ? <TabsTrigger value="beam-batch">{t('Beam and Batch')}</TabsTrigger> : null}
                    {validSections.has('loom-management') ? <TabsTrigger value="loom-management">{t('Loom Management')}</TabsTrigger> : null}
                    {validSections.has('machine-planning') ? <TabsTrigger value="machine-planning">{t('Production Planning')}</TabsTrigger> : null}
                    {validSections.has('weaving-output') ? <TabsTrigger value="weaving-output">{t('Weaving Production')}</TabsTrigger> : null}
                    {validSections.has('waste') ? <TabsTrigger value="waste">{t('Waste')}</TabsTrigger> : null}
                    {validSections.has('rework') ? <TabsTrigger value="rework">{t('Rework')}</TabsTrigger> : null}
                </TabsList>
                {validSections.has('warp-planning') ? <TabsContent value="warp-planning">
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
                                    helperText={t('Source types are managed from Master Setup > Manufacturing Setup > Source Types.')}
                                required
                            />
                            <Field label={t('Source ID')} type="number" value={warpPlanForm.data.source_reference_id} onChange={(v) => warpPlanForm.setData('source_reference_id', v)} required />
                            <SelectField
                                label={t('Source Action')}
                                value={warpPlanForm.data.source_action}
                                onChange={(v) => warpPlanForm.setData('source_action', v)}
                                options={resolvedSourceActionOptions}
                                includeEmpty
                                emptyLabel={t('Select source action')}
                                helperText={t('Source actions are managed from Master Setup > Manufacturing Setup > Source Actions.')}
                                required
                            />
                            <SelectField
                                label={t('Party')}
                                value={warpPlanForm.data.party_name}
                                onChange={(v) => warpPlanForm.setData('party_name', v)}
                                options={resolvedPartyOptions}
                                includeEmpty
                                emptyLabel={t('Select party')}
                                helperText={t('Parties are derived from customer profiles and existing workflow records.')}
                                disabled={resolvedPartyOptions.length === 0}
                                disabledReason={t('No party options available yet. Create customer or prior workflow records.')}
                            />
                            <SelectField
                                label={t('Lot Reference')}
                                value={warpPlanForm.data.lot_reference}
                                onChange={(v) => warpPlanForm.setData('lot_reference', v)}
                                options={resolvedLotReferenceOptions}
                                includeEmpty
                                emptyLabel={t('Select lot reference')}
                                helperText={t('Lot references are derived from active inventory lots and workflow records.')}
                                disabled={resolvedLotReferenceOptions.length === 0}
                                disabledReason={t('No lot options available yet. Create active inventory lots first.')}
                                required
                            />
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

                    <TextileFormCard title={t('Record Chemical Consumption for Sizing')} icon={Check}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            chemicalConsumptionForm.post(route('textile.manufacturing.chemical-consumptions.store'), {
                                onSuccess: () => chemicalConsumptionForm.reset('sizing_recipe_id', 'chemical_type', 'composition_percent', 'consumption_quantity', 'notes'),
                            });
                        }}>
                            <SelectField
                                label={t('Sizing Recipe')}
                                value={chemicalConsumptionForm.data.sizing_recipe_id}
                                onChange={(v) => chemicalConsumptionForm.setData('sizing_recipe_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableSizingRecipes)}
                                includeEmpty
                                emptyLabel={t('Select sizing recipe')}
                                helperText={t('Only completed sizing recipes are listed.')}
                                disabled={actionableSizingRecipes.length === 0}
                                disabledReason={t('No completed sizing recipe found. Create sizing recipe first.')}
                                required
                            />
                            <SelectField
                                label={t('Chemical')}
                                value={chemicalConsumptionForm.data.chemical_type}
                                onChange={(v) => chemicalConsumptionForm.setData('chemical_type', v)}
                                options={resolvedChemicalOptions}
                                includeEmpty
                                emptyLabel={t('Select chemical item')}
                                helperText={t('Chemical options are derived from Product Master items with type Chemical.')}
                                disabled={resolvedChemicalOptions.length === 0}
                                disabledReason={t('No chemical items available. Create Product Master item with type Chemical first.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Composition %')} type="number" value={chemicalConsumptionForm.data.composition_percent} onChange={(v) => chemicalConsumptionForm.setData('composition_percent', v)} required />
                                <Field label={t('Consumption Qty')} type="number" value={chemicalConsumptionForm.data.consumption_quantity} onChange={(v) => chemicalConsumptionForm.setData('consumption_quantity', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Unit')}
                                    value={chemicalConsumptionForm.data.unit}
                                    onChange={(v) => chemicalConsumptionForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                    required
                                />
                                <Field label={t('Notes')} value={chemicalConsumptionForm.data.notes} onChange={(v) => chemicalConsumptionForm.setData('notes', v)} />
                            </div>
                            <Button type="submit" disabled={chemicalConsumptionForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Consumption')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Beam Inspection')} icon={Check}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            beamInspectionForm.post(route('textile.manufacturing.beam-inspections.store'), {
                                onSuccess: () => beamInspectionForm.reset('beam_id', 'remarks'),
                            });
                        }}>
                            <SelectField
                                label={t('Beam')}
                                value={beamInspectionForm.data.beam_id}
                                onChange={(v) => beamInspectionForm.setData('beam_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableBeams)}
                                includeEmpty
                                emptyLabel={t('Select beam')}
                                helperText={t('Only completed beams are listed for inspection.')}
                                disabled={actionableBeams.length === 0}
                                disabledReason={t('No completed beam found. Create and approve beam first.')}
                                required
                            />
                            <SelectField
                                label={t('Inspection Result')}
                                value={beamInspectionForm.data.inspection_result}
                                onChange={(v) => beamInspectionForm.setData('inspection_result', v)}
                                options={resolvedInspectionResultOptions}
                                includeEmpty
                                emptyLabel={t('Select inspection result')}
                                helperText={t('Inspection results are controlled from Master Setup > Manufacturing Setup > Inspection Results.')}
                                required
                            />
                            <Field label={t('Remarks')} value={beamInspectionForm.data.remarks} onChange={(v) => beamInspectionForm.setData('remarks', v)} />
                            <Button type="submit" disabled={beamInspectionForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Beam Inspection')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Beam Cost')} icon={Check}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            beamCostForm.post(route('textile.manufacturing.beam-costs.store'), {
                                onSuccess: () => beamCostForm.reset('beam_id', 'cost_type', 'cost_amount', 'quantity', 'notes'),
                            });
                        }}>
                            <SelectField
                                label={t('Beam')}
                                value={beamCostForm.data.beam_id}
                                onChange={(v) => beamCostForm.setData('beam_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableBeams)}
                                includeEmpty
                                emptyLabel={t('Select beam')}
                                helperText={t('Only completed beams are listed for cost capture.')}
                                disabled={actionableBeams.length === 0}
                                disabledReason={t('No completed beam found. Create and approve beam first.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Cost Type')}
                                    value={beamCostForm.data.cost_type}
                                    onChange={(v) => beamCostForm.setData('cost_type', v)}
                                    options={resolvedCostTypeOptions}
                                    includeEmpty
                                    emptyLabel={t('Select cost type')}
                                    helperText={t('Cost types are controlled from Master Setup > Manufacturing Setup > Cost Types.')}
                                    required
                                />
                                <Field label={t('Cost Amount')} type="number" value={beamCostForm.data.cost_amount} onChange={(v) => beamCostForm.setData('cost_amount', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={beamCostForm.data.quantity} onChange={(v) => beamCostForm.setData('quantity', v)} />
                                <SelectField
                                    label={t('Unit')}
                                    value={beamCostForm.data.unit}
                                    onChange={(v) => beamCostForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                            </div>
                            <Field label={t('Notes')} value={beamCostForm.data.notes} onChange={(v) => beamCostForm.setData('notes', v)} />
                            <Button type="submit" disabled={beamCostForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Beam Cost')}</Button>
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
                    <TextileDataTableSection
                        title={t('Chemical Consumption Records')}
                        data={chemicalConsumptions}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'source_reference_id', header: t('Sizing Recipe ID') },
                            { key: 'chemical_type', header: t('Chemical'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.chemical_type ?? '')) },
                            { key: 'composition_percent', header: t('Composition %'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.composition_percent ?? '-') },
                            { key: 'quantity', header: t('Consumption Qty') },
                            { key: 'unit', header: t('Unit') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No chemical consumption found')} description={t('Record chemical composition and consumption from completed sizing recipes.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Beam Inspection Records')}
                        data={beamInspections}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            {
                                key: 'source_reference_id',
                                header: t('Beam'),
                                render: (value: unknown) => {
                                    const beamId = Number(value ?? 0);
                                    const beamRecord = beamById.get(beamId);

                                    return beamRecord?.document_number ?? String(value ?? '-');
                                },
                            },
                            { key: 'inspection_result', header: t('Result'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.inspection_result ?? '')) },
                            { key: 'remarks', header: t('Remarks'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.remarks ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No beam inspection found')} description={t('Record beam inspection from completed beams.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Beam Cost Records')}
                        data={beamCosts}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            {
                                key: 'source_reference_id',
                                header: t('Beam'),
                                render: (value: unknown) => {
                                    const beamId = Number(value ?? 0);
                                    const beamRecord = beamById.get(beamId);

                                    return beamRecord?.document_number ?? String(value ?? '-');
                                },
                            },
                            { key: 'cost_type', header: t('Cost Type'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.cost_type ?? '')) },
                            { key: 'cost_amount', header: t('Cost Amount'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.cost_amount ?? '-') },
                            { key: 'cost_per_unit', header: t('Cost/Unit'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.cost_per_unit ?? '-') },
                            { key: 'quantity', header: t('Quantity') },
                            { key: 'unit', header: t('Unit') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No beam cost found')} description={t('Record beam cost entries from completed beams.')} />}
                    />
                </div>
                </TabsContent> : null}

                {validSections.has('beam-batch') ? <TabsContent value="beam-batch">
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
                                        helperText={t('Source types are managed from Master Setup > Manufacturing Setup > Source Types.')}
                                    required
                                />
                                <Field label={t('Source ID')} type="number" value={beamForm.data.source_reference_id} onChange={(v) => beamForm.setData('source_reference_id', v)} required />
                                <SelectField
                                    label={t('Source Action')}
                                    value={beamForm.data.source_action}
                                    onChange={(v) => beamForm.setData('source_action', v)}
                                    options={resolvedSourceActionOptions}
                                    includeEmpty
                                    emptyLabel={t('Select source action')}
                                    helperText={t('Source actions are managed from Master Setup > Manufacturing Setup > Source Actions.')}
                                    required
                                />
                                <SelectField
                                    label={t('Party')}
                                    value={beamForm.data.party_name}
                                    onChange={(v) => beamForm.setData('party_name', v)}
                                    options={resolvedPartyOptions}
                                    includeEmpty
                                    emptyLabel={t('Select party')}
                                    helperText={t('Parties are derived from customer profiles and existing workflow records.')}
                                    disabled={resolvedPartyOptions.length === 0}
                                    disabledReason={t('No party options available yet. Create customer or prior workflow records.')}
                                />
                                <SelectField
                                    label={t('Lot Reference')}
                                    value={beamForm.data.lot_reference}
                                    onChange={(v) => beamForm.setData('lot_reference', v)}
                                    options={resolvedLotReferenceOptions}
                                    includeEmpty
                                    emptyLabel={t('Select lot reference')}
                                    helperText={t('Lot references are derived from active inventory lots and workflow records.')}
                                    disabled={resolvedLotReferenceOptions.length === 0}
                                    disabledReason={t('No lot options available yet. Create active inventory lots first.')}
                                    required
                                />
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
                </TabsContent> : null}

                {validSections.has('loom-management') ? <TabsContent value="loom-management">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Register Loom Master')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            loomMasterForm.post(route('textile.manufacturing.loom-masters.store'), {
                                onSuccess: () => loomMasterForm.reset('party_name', 'lot_reference', 'quantity', 'shed_type', 'width', 'running_hours', 'idle_hours', 'operator_name'),
                            });
                        }}>
                            <SelectField
                                label={t('Source Type')}
                                value={loomMasterForm.data.source_reference_type}
                                onChange={(v) => loomMasterForm.setData('source_reference_type', v)}
                                options={resolvedSourceTypeOptions}
                                includeEmpty
                                emptyLabel={t('Select source type')}
                                helperText={t('Source types are managed from Master Setup > Manufacturing Setup > Source Types.')}
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
                                    helperText={t('Machine types are managed from Master Setup > Manufacturing Setup > Machine Types.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Shed Type')}
                                    value={loomMasterForm.data.shed_type}
                                    onChange={(v) => loomMasterForm.setData('shed_type', v)}
                                    options={resolvedShedTypeOptions}
                                    includeEmpty
                                    emptyLabel={t('Select shed type')}
                                    helperText={t('Shed types are managed from Master Setup > Loom Setup > Shed Types.')}
                                    required
                                />
                                <Field label={t('Width')} type="number" value={loomMasterForm.data.width} onChange={(v) => loomMasterForm.setData('width', v)} required />
                            </div>
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
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Loom Status')}
                                    value={loomMasterForm.data.loom_status}
                                    onChange={(v) => loomMasterForm.setData('loom_status', v)}
                                    options={resolvedLoomStatusOptions}
                                    includeEmpty
                                    emptyLabel={t('Select loom status')}
                                    helperText={t('Loom statuses are managed from Master Setup > Loom Setup > Loom Statuses.')}
                                    required
                                />
                                <SelectField
                                    label={t('Operator')}
                                    value={loomMasterForm.data.operator_name}
                                    onChange={(v) => loomMasterForm.setData('operator_name', v)}
                                    options={resolvedOperatorOptions}
                                    includeEmpty
                                    emptyLabel={t('Select operator')}
                                    helperText={t('Operators are listed from your company users.')}
                                    disabled={resolvedOperatorOptions.length === 0}
                                    disabledReason={t('No operators found. Add company users first.')}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Running Hours')} type="number" value={loomMasterForm.data.running_hours} onChange={(v) => loomMasterForm.setData('running_hours', v)} />
                                <Field label={t('Idle Hours')} type="number" value={loomMasterForm.data.idle_hours} onChange={(v) => loomMasterForm.setData('idle_hours', v)} />
                            </div>
                            <Button type="submit" disabled={loomMasterForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Register Loom')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Loom Breakdown')} icon={Check}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            loomBreakdownForm.post(route('textile.manufacturing.loom-breakdowns.store'), {
                                onSuccess: () => loomBreakdownForm.reset('loom_master_id', 'breakdown_reason', 'downtime_hours', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField
                                label={t('Loom')}
                                value={loomBreakdownForm.data.loom_master_id}
                                onChange={(v) => loomBreakdownForm.setData('loom_master_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableLoomMasters)}
                                includeEmpty
                                emptyLabel={t('Select loom')}
                                helperText={t('Only active loom masters are listed.')}
                                disabled={actionableLoomMasters.length === 0}
                                disabledReason={t('No active loom master found. Register loom first.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Breakdown Reason')}
                                    value={loomBreakdownForm.data.breakdown_reason}
                                    onChange={(v) => loomBreakdownForm.setData('breakdown_reason', v)}
                                    options={resolvedBreakdownReasonOptions}
                                    includeEmpty
                                    emptyLabel={t('Select reason')}
                                    helperText={t('Breakdown reasons are managed from Master Setup > Loom Setup > Breakdown Reasons.')}
                                    required
                                />
                                <Field label={t('Downtime Hours')} type="number" value={loomBreakdownForm.data.downtime_hours} onChange={(v) => loomBreakdownForm.setData('downtime_hours', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Unit')}
                                    value={loomBreakdownForm.data.unit}
                                    onChange={(v) => loomBreakdownForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                    required
                                />
                                <SelectField
                                    label={t('Operator')}
                                    value={loomBreakdownForm.data.operator_name}
                                    onChange={(v) => loomBreakdownForm.setData('operator_name', v)}
                                    options={resolvedOperatorOptions}
                                    includeEmpty
                                    emptyLabel={t('Select operator')}
                                    helperText={t('Operators are listed from your company users.')}
                                    disabled={resolvedOperatorOptions.length === 0}
                                    disabledReason={t('No operators found. Add company users first.')}
                                />
                            </div>
                            <Field label={t('Notes')} value={loomBreakdownForm.data.notes} onChange={(v) => loomBreakdownForm.setData('notes', v)} />
                            <Button type="submit" disabled={loomBreakdownForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Breakdown')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Loom Maintenance')} icon={Check}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            loomMaintenanceForm.post(route('textile.manufacturing.loom-maintenances.store'), {
                                onSuccess: () => loomMaintenanceForm.reset('loom_master_id', 'maintenance_type', 'maintenance_hours', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField
                                label={t('Loom')}
                                value={loomMaintenanceForm.data.loom_master_id}
                                onChange={(v) => loomMaintenanceForm.setData('loom_master_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableLoomMasters)}
                                includeEmpty
                                emptyLabel={t('Select loom')}
                                helperText={t('Only active loom masters are listed.')}
                                disabled={actionableLoomMasters.length === 0}
                                disabledReason={t('No active loom master found. Register loom first.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Maintenance Type')}
                                    value={loomMaintenanceForm.data.maintenance_type}
                                    onChange={(v) => loomMaintenanceForm.setData('maintenance_type', v)}
                                    options={resolvedMaintenanceTypeOptions}
                                    includeEmpty
                                    emptyLabel={t('Select maintenance type')}
                                    helperText={t('Maintenance types are managed from Master Setup > Loom Setup > Maintenance Types.')}
                                    required
                                />
                                <Field label={t('Maintenance Hours')} type="number" value={loomMaintenanceForm.data.maintenance_hours} onChange={(v) => loomMaintenanceForm.setData('maintenance_hours', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('Unit')}
                                    value={loomMaintenanceForm.data.unit}
                                    onChange={(v) => loomMaintenanceForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                    required
                                />
                                <SelectField
                                    label={t('Operator')}
                                    value={loomMaintenanceForm.data.operator_name}
                                    onChange={(v) => loomMaintenanceForm.setData('operator_name', v)}
                                    options={resolvedOperatorOptions}
                                    includeEmpty
                                    emptyLabel={t('Select operator')}
                                    helperText={t('Operators are listed from your company users.')}
                                    disabled={resolvedOperatorOptions.length === 0}
                                    disabledReason={t('No operators found. Add company users first.')}
                                />
                            </div>
                            <Field label={t('Notes')} value={loomMaintenanceForm.data.notes} onChange={(v) => loomMaintenanceForm.setData('notes', v)} />
                            <Button type="submit" disabled={loomMaintenanceForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Maintenance')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileDataTableSection
                        title={t('Loom Master Records')}
                        data={loomMasters}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'party_name', header: t('Loom') },
                            { key: 'lot_reference', header: t('Machine Type') },
                            { key: 'quantity', header: t('RPM') },
                            { key: 'width', header: t('Width'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.width ?? '-') },
                            { key: 'shed_type', header: t('Shed'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.shed_type ?? '')) },
                            { key: 'loom_status', header: t('Status'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.loom_status ?? '')) },
                            { key: 'running_hours', header: t('Running Hrs'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.running_hours ?? '-') },
                            { key: 'idle_hours', header: t('Idle Hrs'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.idle_hours ?? '-') },
                            { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No loom masters found')} description={t('Register loom masters to start machine-wise planning.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Loom Breakdown Records')}
                        data={loomBreakdowns}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'source_reference_id', header: t('Loom ID') },
                            { key: 'breakdown_reason', header: t('Reason'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.breakdown_reason ?? '')) },
                            { key: 'downtime_hours', header: t('Downtime Hrs'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.downtime_hours ?? '-') },
                            { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No loom breakdown records found')} description={t('Record loom breakdown to track downtime and root causes.')} />}
                    />
                    <TextileDataTableSection
                        title={t('Loom Maintenance Records')}
                        data={loomMaintenances}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'source_reference_id', header: t('Loom ID') },
                            { key: 'maintenance_type', header: t('Type'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.maintenance_type ?? '')) },
                            { key: 'maintenance_hours', header: t('Hours'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.maintenance_hours ?? '-') },
                            { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No loom maintenance records found')} description={t('Record loom maintenance to track machine care and uptime stability.')} />}
                    />
                </div>
                </TabsContent> : null}

                {validSections.has('machine-planning') ? <TabsContent value="machine-planning">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Create Production Calendar')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            productionCalendarForm.post(route('textile.manufacturing.production-calendars.store'), {
                                onSuccess: () => productionCalendarForm.reset('plan_date', 'notes'),
                            });
                        }}>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Plan Date')} type="date" value={productionCalendarForm.data.plan_date} onChange={(v) => productionCalendarForm.setData('plan_date', v)} required />
                                <SelectField label={t('Day Type')} value={productionCalendarForm.data.day_type} onChange={(v) => productionCalendarForm.setData('day_type', v)} options={resolvedDayTypeOptions} required />
                            </div>
                            <SelectField label={t('Planned Shift')} value={productionCalendarForm.data.planned_shift} onChange={(v) => productionCalendarForm.setData('planned_shift', v)} options={resolvedShiftOptions} includeEmpty emptyLabel={t('Select shift')} />
                            <Field label={t('Notes')} value={productionCalendarForm.data.notes} onChange={(v) => productionCalendarForm.setData('notes', v)} />
                            <Button type="submit" disabled={productionCalendarForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Calendar Entry')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Capacity Plan')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            capacityPlanForm.post(route('textile.manufacturing.capacity-plans.store'), {
                                onSuccess: () => capacityPlanForm.reset('loom_master_id', 'plan_date', 'available_hours', 'capacity_quantity', 'efficiency_target', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Loom')} value={capacityPlanForm.data.loom_master_id} onChange={(v) => capacityPlanForm.setData('loom_master_id', v)} options={createTextileWorkflowSelectOptions(actionableLoomMasters)} includeEmpty emptyLabel={t('Select loom')} helperText={t('Only active loom masters are listed.')} disabled={actionableLoomMasters.length === 0} disabledReason={t('No active loom master found. Register loom first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Plan Date')} type="date" value={capacityPlanForm.data.plan_date} onChange={(v) => capacityPlanForm.setData('plan_date', v)} required />
                                <Field label={t('Available Hours')} type="number" value={capacityPlanForm.data.available_hours} onChange={(v) => capacityPlanForm.setData('available_hours', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Capacity Quantity')} type="number" value={capacityPlanForm.data.capacity_quantity} onChange={(v) => capacityPlanForm.setData('capacity_quantity', v)} required />
                                <SelectField label={t('Unit')} value={capacityPlanForm.data.unit} onChange={(v) => capacityPlanForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Efficiency Target %')} type="number" value={capacityPlanForm.data.efficiency_target} onChange={(v) => capacityPlanForm.setData('efficiency_target', v)} />
                                <SelectField label={t('Operator')} value={capacityPlanForm.data.operator_name} onChange={(v) => capacityPlanForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            </div>
                            <Field label={t('Notes')} value={capacityPlanForm.data.notes} onChange={(v) => capacityPlanForm.setData('notes', v)} />
                            <Button type="submit" disabled={capacityPlanForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Capacity Plan')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Shift Plan')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            shiftPlanForm.post(route('textile.manufacturing.shift-plans.store'), {
                                onSuccess: () => shiftPlanForm.reset('loom_master_id', 'plan_date', 'expected_hours', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Loom')} value={shiftPlanForm.data.loom_master_id} onChange={(v) => shiftPlanForm.setData('loom_master_id', v)} options={createTextileWorkflowSelectOptions(actionableLoomMasters)} includeEmpty emptyLabel={t('Select loom')} helperText={t('Only active loom masters are listed.')} disabled={actionableLoomMasters.length === 0} disabledReason={t('No active loom master found. Register loom first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Plan Date')} type="date" value={shiftPlanForm.data.plan_date} onChange={(v) => shiftPlanForm.setData('plan_date', v)} required />
                                <SelectField label={t('Planned Shift')} value={shiftPlanForm.data.planned_shift} onChange={(v) => shiftPlanForm.setData('planned_shift', v)} options={resolvedShiftOptions} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Expected Hours')} type="number" value={shiftPlanForm.data.expected_hours} onChange={(v) => shiftPlanForm.setData('expected_hours', v)} required />
                                <SelectField label={t('Unit')} value={shiftPlanForm.data.unit} onChange={(v) => shiftPlanForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} required />
                            </div>
                            <SelectField label={t('Operator')} value={shiftPlanForm.data.operator_name} onChange={(v) => shiftPlanForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            <Field label={t('Notes')} value={shiftPlanForm.data.notes} onChange={(v) => shiftPlanForm.setData('notes', v)} />
                            <Button type="submit" disabled={shiftPlanForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Shift Plan')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Machine Plan')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            machinePlanForm.post(route('textile.manufacturing.machine-plans.store'), {
                                onSuccess: () => machinePlanForm.reset('loom_master_id', 'beam_id', 'planned_date', 'planned_quantity', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField
                                label={t('Loom')}
                                value={machinePlanForm.data.loom_master_id}
                                onChange={(v) => machinePlanForm.setData('loom_master_id', v)}
                                options={createTextileWorkflowSelectOptions(actionableLoomMasters)}
                                includeEmpty
                                emptyLabel={t('Select loom')}
                                helperText={t('Only active loom masters are listed.')}
                                disabled={actionableLoomMasters.length === 0}
                                disabledReason={t('No active loom master found. Register loom first.')}
                                required
                            />
                            <SelectField
                                label={t('Approved Beam')}
                                value={machinePlanForm.data.beam_id}
                                onChange={(v) => machinePlanForm.setData('beam_id', v)}
                                options={createTextileWorkflowSelectOptions(approvedBeams)}
                                includeEmpty
                                emptyLabel={t('Select approved beam')}
                                helperText={t('Only approved beams are available for machine planning.')}
                                disabled={approvedBeams.length === 0}
                                disabledReason={t('No approved beam found. Approve a beam first.')}
                                required
                            />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Planned Date')} type="date" value={machinePlanForm.data.planned_date} onChange={(v) => machinePlanForm.setData('planned_date', v)} required />
                                <SelectField
                                    label={t('Planned Shift')}
                                    value={machinePlanForm.data.planned_shift}
                                    onChange={(v) => machinePlanForm.setData('planned_shift', v)}
                                    options={resolvedShiftOptions}
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Planned Quantity')} type="number" value={machinePlanForm.data.planned_quantity} onChange={(v) => machinePlanForm.setData('planned_quantity', v)} required />
                                <SelectField
                                    label={t('Unit')}
                                    value={machinePlanForm.data.unit}
                                    onChange={(v) => machinePlanForm.setData('unit', v)}
                                    options={resolvedUnitOptions}
                                    includeEmpty
                                    emptyLabel={t('Select unit')}
                                    helperText={t('Units are derived from Unit Conversion master.')}
                                />
                            </div>
                            <SelectField
                                label={t('Operator')}
                                value={machinePlanForm.data.operator_name}
                                onChange={(v) => machinePlanForm.setData('operator_name', v)}
                                options={resolvedOperatorOptions}
                                includeEmpty
                                emptyLabel={t('Select operator')}
                                helperText={t('Operators are listed from your company users.')}
                                disabled={resolvedOperatorOptions.length === 0}
                                disabledReason={t('No operators found. Add company users first.')}
                            />
                            <Field label={t('Notes')} value={machinePlanForm.data.notes} onChange={(v) => machinePlanForm.setData('notes', v)} />
                            <Button type="submit" disabled={machinePlanForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Machine Plan')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Material Plan')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            materialPlanForm.post(route('textile.manufacturing.material-plans.store'), {
                                onSuccess: () => materialPlanForm.reset('beam_id', 'plan_date', 'required_quantity', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Approved Beam')} value={materialPlanForm.data.beam_id} onChange={(v) => materialPlanForm.setData('beam_id', v)} options={createTextileWorkflowSelectOptions(approvedBeams)} includeEmpty emptyLabel={t('Select approved beam')} helperText={t('Only approved beams are available for material planning.')} disabled={approvedBeams.length === 0} disabledReason={t('No approved beam found. Approve a beam first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Plan Date')} type="date" value={materialPlanForm.data.plan_date} onChange={(v) => materialPlanForm.setData('plan_date', v)} required />
                                <Field label={t('Required Quantity')} type="number" value={materialPlanForm.data.required_quantity} onChange={(v) => materialPlanForm.setData('required_quantity', v)} required />
                            </div>
                            <SelectField label={t('Unit')} value={materialPlanForm.data.unit} onChange={(v) => materialPlanForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            <Field label={t('Notes')} value={materialPlanForm.data.notes} onChange={(v) => materialPlanForm.setData('notes', v)} />
                            <Button type="submit" disabled={materialPlanForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Material Plan')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Create Production Schedule')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            productionScheduleForm.post(route('textile.manufacturing.production-schedules.store'), {
                                onSuccess: () => productionScheduleForm.reset('loom_master_id', 'beam_id', 'scheduled_date', 'scheduled_quantity', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Loom')} value={productionScheduleForm.data.loom_master_id} onChange={(v) => productionScheduleForm.setData('loom_master_id', v)} options={createTextileWorkflowSelectOptions(actionableLoomMasters)} includeEmpty emptyLabel={t('Select loom')} helperText={t('Only active loom masters are listed.')} disabled={actionableLoomMasters.length === 0} disabledReason={t('No active loom master found. Register loom first.')} required />
                            <SelectField label={t('Approved Beam')} value={productionScheduleForm.data.beam_id} onChange={(v) => productionScheduleForm.setData('beam_id', v)} options={createTextileWorkflowSelectOptions(approvedBeams)} includeEmpty emptyLabel={t('Select approved beam')} helperText={t('Only approved beams are available for production scheduling.')} disabled={approvedBeams.length === 0} disabledReason={t('No approved beam found. Approve a beam first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Scheduled Date')} type="date" value={productionScheduleForm.data.scheduled_date} onChange={(v) => productionScheduleForm.setData('scheduled_date', v)} required />
                                <SelectField label={t('Scheduled Shift')} value={productionScheduleForm.data.scheduled_shift} onChange={(v) => productionScheduleForm.setData('scheduled_shift', v)} options={resolvedShiftOptions} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Scheduled Quantity')} type="number" value={productionScheduleForm.data.scheduled_quantity} onChange={(v) => productionScheduleForm.setData('scheduled_quantity', v)} required />
                                <SelectField label={t('Unit')} value={productionScheduleForm.data.unit} onChange={(v) => productionScheduleForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            </div>
                            <SelectField label={t('Operator')} value={productionScheduleForm.data.operator_name} onChange={(v) => productionScheduleForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            <Field label={t('Notes')} value={productionScheduleForm.data.notes} onChange={(v) => productionScheduleForm.setData('notes', v)} />
                            <Button type="submit" disabled={productionScheduleForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Production Schedule')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileDataTableSection
                        title={t('Production Calendar Records')}
                        data={productionCalendars}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'plan_date', header: t('Plan Date'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.plan_date ?? '-') },
                            { key: 'day_type', header: t('Day Type'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.day_type ?? '')) },
                            { key: 'planned_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_shift ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No production calendar found')} description={t('Create calendar entries to define working, holiday, shutdown, or maintenance days.')} />}
                    />

                    <TextileDataTableSection
                        title={t('Capacity Plan Records')}
                        data={capacityPlans}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'source_reference_id', header: t('Loom ID') },
                            { key: 'plan_date', header: t('Plan Date'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.plan_date ?? '-') },
                            { key: 'available_hours', header: t('Hours'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.available_hours ?? '-') },
                            { key: 'quantity', header: t('Capacity Qty') },
                            { key: 'efficiency_target', header: t('Eff. %'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.efficiency_target ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No capacity plans found')} description={t('Create capacity plans to define loom availability and output expectation.')} />}
                    />

                    <TextileDataTableSection
                        title={t('Shift Plan Records')}
                        data={shiftPlans}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'source_reference_id', header: t('Loom ID') },
                            { key: 'plan_date', header: t('Plan Date'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.plan_date ?? '-') },
                            { key: 'planned_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_shift ?? '-') },
                            { key: 'expected_hours', header: t('Hours'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.expected_hours ?? '-') },
                            { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No shift plans found')} description={t('Create shift plans to assign loom time and operator coverage.')} />}
                    />

                    <TextileDataTableSection
                        title={t('Machine Plan Records')}
                        data={machinePlans}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'source_reference_id', header: t('Loom ID') },
                            { key: 'beam_number', header: t('Beam'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.beam_number ?? row.lot_reference ?? '-') },
                            { key: 'planned_date', header: t('Planned Date'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_date ?? '-') },
                            { key: 'planned_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_shift ?? '-') },
                            { key: 'quantity', header: t('Planned Qty') },
                            { key: 'unit', header: t('Unit') },
                            { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No machine plans found')} description={t('Create machine plans to assign approved beams to looms by date and shift.')} />}
                    />

                    <TextileDataTableSection
                        title={t('Material Plan Records')}
                        data={materialPlans}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'beam_number', header: t('Beam'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.beam_number ?? row.lot_reference ?? '-') },
                            { key: 'plan_date', header: t('Plan Date'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.plan_date ?? '-') },
                            { key: 'quantity', header: t('Required Qty') },
                            { key: 'unit', header: t('Unit') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No material plans found')} description={t('Create material plans to reserve beam-linked quantity requirements for planned runs.')} />}
                    />

                    <TextileDataTableSection
                        title={t('Production Schedule Records')}
                        data={productionSchedules}
                        columns={[
                            { key: 'document_number', header: t('Number') },
                            { key: 'source_reference_id', header: t('Loom ID') },
                            { key: 'beam_number', header: t('Beam'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.beam_number ?? row.lot_reference ?? '-') },
                            { key: 'scheduled_date', header: t('Scheduled Date'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.scheduled_date ?? '-') },
                            { key: 'scheduled_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.scheduled_shift ?? '')) },
                            { key: 'quantity', header: t('Scheduled Qty') },
                            { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') },
                            { key: 'status', header: t('Status') },
                        ]}
                        emptyState={<NoRecordsFound icon={Factory} title={t('No production schedules found')} description={t('Create production schedules to lock loom, beam, date, shift, and quantity commitments.')} />}
                    />
                </div>
                </TabsContent> : null}

                {validSections.has('weaving-output') ? <TabsContent value="weaving-output">
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Record Weaving Output')} icon={Factory}>
                        <form className="grid grid-cols-4 gap-3" onSubmit={(e) => {
                            e.preventDefault();
                            weavingOutputForm.post(route('textile.manufacturing.weaving-output.store'), {
                                onSuccess: () => weavingOutputForm.reset('batch_id', 'quantity'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={weavingOutputForm.data.batch_id} onChange={(v) => weavingOutputForm.setData('batch_id', v)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} helperText={t('Only released production batches are listed.')} disabled={releasedBatches.length === 0} disabledReason={t('No released batch found. Release a production batch first.')} required />
                            <Field label={t('Output Qty')} type="number" value={weavingOutputForm.data.quantity} onChange={(v) => weavingOutputForm.setData('quantity', v)} required />
                            <SelectField label={t('Unit')} value={weavingOutputForm.data.unit} onChange={(v) => weavingOutputForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            <Button type="submit" disabled={weavingOutputForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Record Output')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Shift Production')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            shiftProductionForm.post(route('textile.manufacturing.shift-productions.store'), {
                                onSuccess: () => shiftProductionForm.reset('batch_id', 'loom_master_id', 'quantity', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Batch')} value={shiftProductionForm.data.batch_id} onChange={(v) => shiftProductionForm.setData('batch_id', v)} options={createTextileWorkflowSelectOptions(releasedBatches)} includeEmpty emptyLabel={t('Select released batch')} helperText={t('Only released production batches are listed.')} disabled={releasedBatches.length === 0} disabledReason={t('No released batch found. Release a production batch first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Loom')} value={shiftProductionForm.data.loom_master_id} onChange={(v) => shiftProductionForm.setData('loom_master_id', v)} options={createTextileWorkflowSelectOptions(actionableLoomMasters)} includeEmpty emptyLabel={t('Select loom')} helperText={t('Optional loom link for shift execution entry.')} />
                                <SelectField label={t('Shift')} value={shiftProductionForm.data.planned_shift} onChange={(v) => shiftProductionForm.setData('planned_shift', v)} options={resolvedShiftOptions} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={shiftProductionForm.data.quantity} onChange={(v) => shiftProductionForm.setData('quantity', v)} required />
                                <SelectField label={t('Unit')} value={shiftProductionForm.data.unit} onChange={(v) => shiftProductionForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            </div>
                            <SelectField label={t('Operator')} value={shiftProductionForm.data.operator_name} onChange={(v) => shiftProductionForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            <Field label={t('Notes')} value={shiftProductionForm.data.notes} onChange={(v) => shiftProductionForm.setData('notes', v)} />
                            <Button type="submit" disabled={shiftProductionForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Shift Production')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Takha Entry')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            takhaEntryForm.post(route('textile.manufacturing.takha-entries.store'), {
                                onSuccess: () => takhaEntryForm.reset('weaving_output_id', 'takha_number', 'quantity', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Weaving Output')} value={takhaEntryForm.data.weaving_output_id} onChange={(v) => takhaEntryForm.setData('weaving_output_id', v)} options={createTextileWorkflowSelectOptions(completedWeavingOutputs)} includeEmpty emptyLabel={t('Select weaving output')} helperText={t('Only completed weaving outputs are listed.')} disabled={completedWeavingOutputs.length === 0} disabledReason={t('No weaving output found. Record weaving output first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Takha Number')} value={takhaEntryForm.data.takha_number} onChange={(v) => takhaEntryForm.setData('takha_number', v)} required />
                                <Field label={t('Quantity')} type="number" value={takhaEntryForm.data.quantity} onChange={(v) => takhaEntryForm.setData('quantity', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Unit')} value={takhaEntryForm.data.unit} onChange={(v) => takhaEntryForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                <SelectField label={t('Operator')} value={takhaEntryForm.data.operator_name} onChange={(v) => takhaEntryForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            </div>
                            <Field label={t('Notes')} value={takhaEntryForm.data.notes} onChange={(v) => takhaEntryForm.setData('notes', v)} />
                            <Button type="submit" disabled={takhaEntryForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Takha Entry')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Loom Efficiency')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            loomEfficiencyForm.post(route('textile.manufacturing.loom-efficiencies.store'), {
                                onSuccess: () => loomEfficiencyForm.reset('loom_master_id', 'planned_quantity', 'actual_quantity', 'runtime_hours', 'downtime_hours', 'operator_name', 'notes'),
                            });
                        }}>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Loom')} value={loomEfficiencyForm.data.loom_master_id} onChange={(v) => loomEfficiencyForm.setData('loom_master_id', v)} options={createTextileWorkflowSelectOptions(actionableLoomMasters)} includeEmpty emptyLabel={t('Select loom')} helperText={t('Only active loom masters are listed.')} disabled={actionableLoomMasters.length === 0} disabledReason={t('No active loom master found. Register loom first.')} required />
                                <SelectField label={t('Shift')} value={loomEfficiencyForm.data.planned_shift} onChange={(v) => loomEfficiencyForm.setData('planned_shift', v)} options={resolvedShiftOptions} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Planned Quantity')} type="number" value={loomEfficiencyForm.data.planned_quantity} onChange={(v) => loomEfficiencyForm.setData('planned_quantity', v)} required />
                                <Field label={t('Actual Quantity')} type="number" value={loomEfficiencyForm.data.actual_quantity} onChange={(v) => loomEfficiencyForm.setData('actual_quantity', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Runtime Hours')} type="number" value={loomEfficiencyForm.data.runtime_hours} onChange={(v) => loomEfficiencyForm.setData('runtime_hours', v)} />
                                <Field label={t('Downtime Hours')} type="number" value={loomEfficiencyForm.data.downtime_hours} onChange={(v) => loomEfficiencyForm.setData('downtime_hours', v)} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Unit')} value={loomEfficiencyForm.data.unit} onChange={(v) => loomEfficiencyForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                <SelectField label={t('Operator')} value={loomEfficiencyForm.data.operator_name} onChange={(v) => loomEfficiencyForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            </div>
                            <Field label={t('Notes')} value={loomEfficiencyForm.data.notes} onChange={(v) => loomEfficiencyForm.setData('notes', v)} />
                            <Button type="submit" disabled={loomEfficiencyForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Loom Efficiency')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Operator Efficiency')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            operatorEfficiencyForm.post(route('textile.manufacturing.operator-efficiencies.store'), {
                                onSuccess: () => operatorEfficiencyForm.reset('planned_quantity', 'actual_quantity', 'operator_name', 'notes'),
                            });
                        }}>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Shift')} value={operatorEfficiencyForm.data.planned_shift} onChange={(v) => operatorEfficiencyForm.setData('planned_shift', v)} options={resolvedShiftOptions} required />
                                <SelectField label={t('Operator')} value={operatorEfficiencyForm.data.operator_name} onChange={(v) => operatorEfficiencyForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Planned Quantity')} type="number" value={operatorEfficiencyForm.data.planned_quantity} onChange={(v) => operatorEfficiencyForm.setData('planned_quantity', v)} required />
                                <Field label={t('Actual Quantity')} type="number" value={operatorEfficiencyForm.data.actual_quantity} onChange={(v) => operatorEfficiencyForm.setData('actual_quantity', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Unit')} value={operatorEfficiencyForm.data.unit} onChange={(v) => operatorEfficiencyForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                <Field label={t('Notes')} value={operatorEfficiencyForm.data.notes} onChange={(v) => operatorEfficiencyForm.setData('notes', v)} />
                            </div>
                            <Button type="submit" disabled={operatorEfficiencyForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Operator Efficiency')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Machine Downtime')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            machineDowntimeForm.post(route('textile.manufacturing.machine-downtimes.store'), {
                                onSuccess: () => machineDowntimeForm.reset('loom_master_id', 'downtime_reason', 'downtime_hours', 'operator_name', 'notes'),
                            });
                        }}>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Loom')} value={machineDowntimeForm.data.loom_master_id} onChange={(v) => machineDowntimeForm.setData('loom_master_id', v)} options={createTextileWorkflowSelectOptions(actionableLoomMasters)} includeEmpty emptyLabel={t('Select loom')} helperText={t('Only active loom masters are listed.')} disabled={actionableLoomMasters.length === 0} disabledReason={t('No active loom master found. Register loom first.')} required />
                                <SelectField label={t('Shift')} value={machineDowntimeForm.data.planned_shift} onChange={(v) => machineDowntimeForm.setData('planned_shift', v)} options={resolvedShiftOptions} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Downtime Reason')} value={machineDowntimeForm.data.downtime_reason} onChange={(v) => machineDowntimeForm.setData('downtime_reason', v)} options={resolvedBreakdownReasonOptions} includeEmpty emptyLabel={t('Select reason')} helperText={t('Downtime reasons reuse Loom Setup > Breakdown Reasons.')} required />
                                <Field label={t('Downtime Hours')} type="number" value={machineDowntimeForm.data.downtime_hours} onChange={(v) => machineDowntimeForm.setData('downtime_hours', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Unit')} value={machineDowntimeForm.data.unit} onChange={(v) => machineDowntimeForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} required />
                                <SelectField label={t('Operator')} value={machineDowntimeForm.data.operator_name} onChange={(v) => machineDowntimeForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            </div>
                            <Field label={t('Notes')} value={machineDowntimeForm.data.notes} onChange={(v) => machineDowntimeForm.setData('notes', v)} />
                            <Button type="submit" disabled={machineDowntimeForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Downtime')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Record Production Cost')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            productionCostForm.post(route('textile.manufacturing.production-costs.store'), {
                                onSuccess: () => productionCostForm.reset('weaving_output_id', 'cost_center_id', 'cost_amount', 'quantity', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Weaving Output')} value={productionCostForm.data.weaving_output_id} onChange={(v) => productionCostForm.setData('weaving_output_id', v)} options={createTextileWorkflowSelectOptions(completedWeavingOutputs)} includeEmpty emptyLabel={t('Select weaving output')} helperText={t('Only completed weaving outputs are listed.')} disabled={completedWeavingOutputs.length === 0} disabledReason={t('No weaving output found. Record weaving output first.')} required />
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Cost Center')} value={productionCostForm.data.cost_center_id} onChange={(v) => productionCostForm.setData('cost_center_id', v)} options={resolvedCostCenterOptions} includeEmpty emptyLabel={t('Select cost center')} helperText={t('Cost centers are managed from Master Setup > Core Setup > Cost Centers.')} />
                                <Field label={t('Cost Amount')} type="number" value={productionCostForm.data.cost_amount} onChange={(v) => productionCostForm.setData('cost_amount', v)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={productionCostForm.data.quantity} onChange={(v) => productionCostForm.setData('quantity', v)} />
                                <SelectField label={t('Unit')} value={productionCostForm.data.unit} onChange={(v) => productionCostForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Operator')} value={productionCostForm.data.operator_name} onChange={(v) => productionCostForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                                <Field label={t('Notes')} value={productionCostForm.data.notes} onChange={(v) => productionCostForm.setData('notes', v)} />
                            </div>
                            <Button type="submit" disabled={productionCostForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Record Production Cost')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Generate Grey Fabric Roll')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            greyFabricRollForm.post(route('textile.manufacturing.grey-fabric-rolls.store'), {
                                onSuccess: () => greyFabricRollForm.reset('weaving_output_id', 'roll_number', 'roll_barcode', 'roll_qr_code', 'roll_weight', 'roll_length', 'gsm', 'width', 'grade', 'warehouse', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Weaving Output')} value={greyFabricRollForm.data.weaving_output_id} onChange={(v) => greyFabricRollForm.setData('weaving_output_id', v)} options={createTextileWorkflowSelectOptions(completedWeavingOutputs)} includeEmpty emptyLabel={t('Select weaving output')} helperText={t('Only completed weaving outputs are listed.')} disabled={completedWeavingOutputs.length === 0} disabledReason={t('No weaving output found. Record weaving output first.')} required />
                            <div className="grid grid-cols-3 gap-3">
                                <Field label={t('Roll Number')} value={greyFabricRollForm.data.roll_number} onChange={(v) => greyFabricRollForm.setData('roll_number', v)} />
                                <Field label={t('Roll Barcode')} value={greyFabricRollForm.data.roll_barcode} onChange={(v) => greyFabricRollForm.setData('roll_barcode', v)} />
                                <Field label={t('Roll QR Code')} value={greyFabricRollForm.data.roll_qr_code} onChange={(v) => greyFabricRollForm.setData('roll_qr_code', v)} />
                            </div>
                            <div className="grid grid-cols-4 gap-3">
                                <Field label={t('Roll Weight')} type="number" value={greyFabricRollForm.data.roll_weight} onChange={(v) => greyFabricRollForm.setData('roll_weight', v)} required />
                                <Field label={t('Roll Length')} type="number" value={greyFabricRollForm.data.roll_length} onChange={(v) => greyFabricRollForm.setData('roll_length', v)} required />
                                <Field label={t('GSM')} type="number" value={greyFabricRollForm.data.gsm} onChange={(v) => greyFabricRollForm.setData('gsm', v)} required />
                                <Field label={t('Width')} type="number" value={greyFabricRollForm.data.width} onChange={(v) => greyFabricRollForm.setData('width', v)} required />
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                <SelectField label={t('Defects')} value={greyFabricRollForm.data.defects.join(',')} onChange={(v) => greyFabricRollForm.setData('defects', v ? v.split(',').map((item) => item.trim()).filter(Boolean) : [])} options={resolvedFabricDefectOptions} includeEmpty emptyLabel={t('Select defects')} helperText={t('Defects are managed from Beam and Cost Setup > Fabric Defects.')} />
                                <SelectField label={t('Grade')} value={greyFabricRollForm.data.grade} onChange={(v) => greyFabricRollForm.setData('grade', v)} options={resolvedFabricGradeOptions} includeEmpty emptyLabel={t('Select grade')} helperText={t('Grades are managed from Beam and Cost Setup > Fabric Grades.')} required />
                                <SelectField label={t('Warehouse')} value={greyFabricRollForm.data.warehouse} onChange={(v) => greyFabricRollForm.setData('warehouse', v)} options={resolvedWarehouseOptions} includeEmpty emptyLabel={t('Select warehouse')} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Unit')} value={greyFabricRollForm.data.unit} onChange={(v) => greyFabricRollForm.setData('unit', v)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                <SelectField label={t('Operator')} value={greyFabricRollForm.data.operator_name} onChange={(v) => greyFabricRollForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                            </div>
                            <Field label={t('Notes')} value={greyFabricRollForm.data.notes} onChange={(v) => greyFabricRollForm.setData('notes', v)} />
                            <Button type="submit" disabled={greyFabricRollForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Generate Roll')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileFormCard title={t('Update Grey Fabric Roll')} icon={Factory}>
                        <form className="space-y-3" onSubmit={(e) => {
                            e.preventDefault();
                            greyFabricRollUpdateForm.post(route('textile.manufacturing.grey-fabric-rolls.update'), {
                                onSuccess: () => greyFabricRollUpdateForm.reset('grey_roll_id', 'roll_weight', 'roll_length', 'gsm', 'width', 'grade', 'warehouse', 'operator_name', 'notes'),
                            });
                        }}>
                            <SelectField label={t('Grey Roll')} value={greyFabricRollUpdateForm.data.grey_roll_id} onChange={(v) => greyFabricRollUpdateForm.setData('grey_roll_id', v)} options={createTextileWorkflowSelectOptions(greyFabricRolls)} includeEmpty emptyLabel={t('Select grey roll')} helperText={t('Select generated roll to update quality and trace fields.')} disabled={greyFabricRolls.length === 0} disabledReason={t('No grey roll found. Generate grey roll first.')} required />
                            <div className="grid grid-cols-4 gap-3">
                                <Field label={t('Roll Weight')} type="number" value={greyFabricRollUpdateForm.data.roll_weight} onChange={(v) => greyFabricRollUpdateForm.setData('roll_weight', v)} />
                                <Field label={t('Roll Length')} type="number" value={greyFabricRollUpdateForm.data.roll_length} onChange={(v) => greyFabricRollUpdateForm.setData('roll_length', v)} />
                                <Field label={t('GSM')} type="number" value={greyFabricRollUpdateForm.data.gsm} onChange={(v) => greyFabricRollUpdateForm.setData('gsm', v)} />
                                <Field label={t('Width')} type="number" value={greyFabricRollUpdateForm.data.width} onChange={(v) => greyFabricRollUpdateForm.setData('width', v)} />
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                <SelectField label={t('Defects')} value={greyFabricRollUpdateForm.data.defects.join(',')} onChange={(v) => greyFabricRollUpdateForm.setData('defects', v ? v.split(',').map((item) => item.trim()).filter(Boolean) : [])} options={resolvedFabricDefectOptions} includeEmpty emptyLabel={t('Select defects')} helperText={t('Defects are managed from Beam and Cost Setup > Fabric Defects.')} />
                                <SelectField label={t('Grade')} value={greyFabricRollUpdateForm.data.grade} onChange={(v) => greyFabricRollUpdateForm.setData('grade', v)} options={resolvedFabricGradeOptions} includeEmpty emptyLabel={t('Select grade')} helperText={t('Grades are managed from Beam and Cost Setup > Fabric Grades.')} />
                                <SelectField label={t('Warehouse')} value={greyFabricRollUpdateForm.data.warehouse} onChange={(v) => greyFabricRollUpdateForm.setData('warehouse', v)} options={resolvedWarehouseOptions} includeEmpty emptyLabel={t('Select warehouse')} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField label={t('Operator')} value={greyFabricRollUpdateForm.data.operator_name} onChange={(v) => greyFabricRollUpdateForm.setData('operator_name', v)} options={resolvedOperatorOptions} includeEmpty emptyLabel={t('Select operator')} helperText={t('Operators are listed from your company users.')} disabled={resolvedOperatorOptions.length === 0} disabledReason={t('No operators found. Add company users first.')} />
                                <Field label={t('Notes')} value={greyFabricRollUpdateForm.data.notes} onChange={(v) => greyFabricRollUpdateForm.setData('notes', v)} />
                            </div>
                            <Button type="submit" disabled={greyFabricRollUpdateForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Update Roll')}</Button>
                        </form>
                    </TextileFormCard>

                    <TextileDataTableSection title={t('Weaving Output Records')} data={weavingOutputs} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No weaving output found')} description={t('Record weaving output from released batches.')} />} />
                    <TextileDataTableSection title={t('Shift Production Records')} data={shiftProductions} columns={[{ key: 'document_number', header: t('Number') }, { key: 'source_reference_id', header: t('Batch ID') }, { key: 'planned_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_shift ?? '-') }, { key: 'quantity', header: t('Qty') }, { key: 'unit', header: t('Unit') }, { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No shift production found')} description={t('Record shift-wise weaving production from released batches.')} />} />
                    <TextileDataTableSection title={t('Takha Entry Records')} data={takhaEntries} columns={[{ key: 'document_number', header: t('Number') }, { key: 'takha_number', header: t('Takha'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.takha_number ?? row.lot_reference ?? '-') }, { key: 'quantity', header: t('Qty') }, { key: 'unit', header: t('Unit') }, { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No takha entries found')} description={t('Record takha-level production entries from completed weaving output.')} />} />
                    <TextileDataTableSection title={t('Loom Efficiency Records')} data={loomEfficiencies} columns={[{ key: 'document_number', header: t('Number') }, { key: 'source_reference_id', header: t('Loom ID') }, { key: 'planned_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_shift ?? '-') }, { key: 'planned_quantity', header: t('Planned Qty'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_quantity ?? '-') }, { key: 'actual_quantity', header: t('Actual Qty'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.actual_quantity ?? '-') }, { key: 'efficiency_percent', header: t('Efficiency %'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.efficiency_percent ?? '-') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No loom efficiency found')} description={t('Record planned vs actual loom output to track efficiency.')} />} />
                    <TextileDataTableSection title={t('Operator Efficiency Records')} data={operatorEfficiencies} columns={[{ key: 'document_number', header: t('Number') }, { key: 'planned_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_shift ?? '-') }, { key: 'planned_quantity', header: t('Planned Qty'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_quantity ?? '-') }, { key: 'actual_quantity', header: t('Actual Qty'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.actual_quantity ?? '-') }, { key: 'efficiency_percent', header: t('Efficiency %'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.efficiency_percent ?? '-') }, { key: 'party_name', header: t('Operator') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No operator efficiency found')} description={t('Record operator planned vs actual production to track efficiency.')} />} />
                    <TextileDataTableSection title={t('Machine Downtime Records')} data={machineDowntimes} columns={[{ key: 'document_number', header: t('Number') }, { key: 'source_reference_id', header: t('Loom ID') }, { key: 'planned_shift', header: t('Shift'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.planned_shift ?? '-') }, { key: 'downtime_reason', header: t('Reason'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.downtime_reason ?? '')) }, { key: 'downtime_hours', header: t('Downtime Hrs'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.downtime_hours ?? '-') }, { key: 'operator_name', header: t('Operator'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.operator_name ?? '-') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No machine downtime found')} description={t('Record weaving-stage machine downtime by shift and loom.')} />} />
                    <TextileDataTableSection title={t('Production Cost Records')} data={productionCosts} columns={[{ key: 'document_number', header: t('Number') }, { key: 'source_reference_id', header: t('Weaving Output ID') }, { key: 'cost_amount', header: t('Cost Amount'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.cost_amount ?? '-') }, { key: 'cost_per_unit', header: t('Cost/Unit'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.cost_per_unit ?? '-') }, { key: 'quantity', header: t('Qty') }, { key: 'unit', header: t('Unit') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No production cost found')} description={t('Record weaving production cost against completed output.')} />} />
                    <TextileDataTableSection title={t('Grey Fabric Roll Records')} data={greyFabricRolls} columns={[{ key: 'document_number', header: t('Number') }, { key: 'roll_number', header: t('Roll No.'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.roll_number ?? row.lot_reference ?? '-') }, { key: 'roll_barcode', header: t('Barcode'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.roll_barcode ?? '-') }, { key: 'roll_qr_code', header: t('QR'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.roll_qr_code ?? '-') }, { key: 'roll_weight', header: t('Weight'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.roll_weight ?? '-') }, { key: 'roll_length', header: t('Length'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.roll_length ?? row.quantity ?? '-') }, { key: 'gsm', header: t('GSM'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.gsm ?? '-') }, { key: 'width', header: t('Width'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.width ?? '-') }, { key: 'grade', header: t('Grade'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.grade ?? '-') }, { key: 'warehouse', header: t('Warehouse'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.warehouse ?? '-') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No grey fabric roll found')} description={t('Generate grey fabric roll with roll-number, barcode, QR, and quality profile fields.')} />} />
                    <TextileDataTableSection title={t('Grey Roll History Records')} data={greyRollHistories} columns={[{ key: 'document_number', header: t('Number') }, { key: 'source_reference_id', header: t('Roll ID') }, { key: 'roll_number', header: t('Roll No.'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.roll_number ?? row.lot_reference ?? '-') }, { key: 'history_event', header: t('Event'), render: (_value: unknown, row: WorkflowDocument) => formatTextileLabel(String(row.metadata?.history_event ?? '')) }, { key: 'history_notes', header: t('Notes'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.history_notes ?? '-') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Factory} title={t('No grey roll history found')} description={t('Grey roll updates and lifecycle events will appear here.')} />} />
                </div>
                </TabsContent> : null}

                {validSections.has('waste') ? <TabsContent value="waste">
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
                </TabsContent> : null}

                {validSections.has('rework') ? <TabsContent value="rework">
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
                </TabsContent> : null}
            </Tabs>
        </AuthenticatedLayout>
    );
}
