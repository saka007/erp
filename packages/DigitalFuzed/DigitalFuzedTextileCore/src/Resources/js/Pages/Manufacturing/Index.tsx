import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Factory, Plus, Check } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';

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
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const validSections = new Set(['beam-batch', 'weaving-output', 'waste', 'rework']);
    const activeSection = sectionParam && validSections.has(sectionParam) ? sectionParam : 'beam-batch';

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
    const approvedBeams = beams.filter((row) => row.status === 'approved');
    const releasedBatches = productionBatches.filter((row) => row.status === 'released');

    const allDocuments = [...beams, ...productionBatches, ...weavingOutputs, ...wastes, ...reworks];
    const draftCount = allDocuments.filter((row) => row.status === 'draft').length;
    const approvedCount = allDocuments.filter((row) => row.status === 'approved').length;
    const releasedCount = allDocuments.filter((row) => row.status === 'released').length;

    const approveBeam = (id: number) => {
        router.post(route('textile.manufacturing.beams.approve'), { beam_id: id }, { preserveScroll: true });
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
                    { label: t('Total Documents'), value: allDocuments.length, hint: t('Beam + Batch + Output + Waste + Rework') },
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
                <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-4">
                    <TabsTrigger value="beam-batch">{t('Beam and Batch')}</TabsTrigger>
                    <TabsTrigger value="weaving-output">{t('Weaving Output')}</TabsTrigger>
                    <TabsTrigger value="waste">{t('Waste')}</TabsTrigger>
                    <TabsTrigger value="rework">{t('Rework')}</TabsTrigger>
                </TabsList>
            </Tabs>

            {activeSection === 'beam-batch' && (
                <div className="grid gap-6 xl:grid-cols-2">
                    <TextileFormCard title={t('Create Beam')} icon={Factory}>
                            <form className="space-y-3" onSubmit={(e) => {
                                e.preventDefault();
                                beamForm.post(route('textile.manufacturing.beams.store'), {
                                    onSuccess: () => beamForm.reset('source_reference_id', 'party_name', 'lot_reference', 'quantity'),
                                });
                            }}>
                                <Field label={t('Source Type')} value={beamForm.data.source_reference_type} onChange={(v) => beamForm.setData('source_reference_type', v)} required />
                                <Field label={t('Source ID')} type="number" value={beamForm.data.source_reference_id} onChange={(v) => beamForm.setData('source_reference_id', v)} required />
                                <Field label={t('Source Action')} value={beamForm.data.source_action} onChange={(v) => beamForm.setData('source_action', v)} required />
                                <Field label={t('Party')} value={beamForm.data.party_name} onChange={(v) => beamForm.setData('party_name', v)} />
                                <Field label={t('Lot Reference')} value={beamForm.data.lot_reference} onChange={(v) => beamForm.setData('lot_reference', v)} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Quantity')} type="number" value={beamForm.data.quantity} onChange={(v) => beamForm.setData('quantity', v)} required />
                                    <Field label={t('Unit')} value={beamForm.data.unit} onChange={(v) => beamForm.setData('unit', v)} />
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
                </div>
            )}

            {activeSection === 'weaving-output' && (
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
                                <Field label={t('Unit')} value={weavingOutputForm.data.unit} onChange={(v) => weavingOutputForm.setData('unit', v)} />
                                <Button type="submit" disabled={weavingOutputForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Record Output')}</Button>
                            </form>
                    </TextileFormCard>
                    <TextileDataTableCard data={weavingOutputs} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No weaving output found')} description={t('Record weaving output from released batches.')} />} />
                </div>
            )}

            {activeSection === 'waste' && (
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
                                <Field label={t('Unit')} value={wasteForm.data.unit} onChange={(v) => wasteForm.setData('unit', v)} />
                                <Button type="submit" variant="outline" disabled={wasteForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Record Waste')}</Button>
                            </form>
                    </TextileFormCard>
                    <TextileDataTableCard data={wastes} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No waste records found')} description={t('Record waste from released batches.')} />} />
                </div>
            )}

            {activeSection === 'rework' && (
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
                                <Field label={t('Unit')} value={reworkForm.data.unit} onChange={(v) => reworkForm.setData('unit', v)} />
                                <Button type="submit" variant="outline" disabled={reworkForm.processing} className="self-end"><Plus className="mr-2 h-4 w-4" />{t('Record Rework')}</Button>
                            </form>
                    </TextileFormCard>
                    <TextileDataTableCard data={reworks} columns={createTextileWorkflowColumns(t)} emptyState={<NoRecordsFound icon={Factory} title={t('No rework records found')} description={t('Record rework linked to weaving output.')} />} />
                </div>
            )}
        </AuthenticatedLayout>
    );
}
