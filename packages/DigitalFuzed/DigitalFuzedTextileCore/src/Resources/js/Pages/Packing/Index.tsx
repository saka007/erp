import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Box, Plus, QrCode, Check } from 'lucide-react';
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
import { buildUnitOptions, formatTextileOptionLabel } from '@/components/textile/textile-form-options';
import { createTextileWorkflowActions, createTextileWorkflowColumns, createTextileWorkflowSelectOptions, textileActionableStatuses } from '@/components/textile/textile-workflow-columns';
import { PageProps } from '@/types';

interface WorkflowDocument {
    id: number;
    document_number: string;
    source_reference_type?: string | null;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    metadata?: {
        packing_material?: string | null;
        label_type?: string | null;
        label_code?: string | null;
        weight?: number | null;
    } | null;
}

const PACKING_SECTIONS = ['roll-packing', 'bundle-packing', 'bale-packing', 'labels'] as const;
type PackingSection = typeof PACKING_SECTIONS[number];

function metadataLabel(value?: string | null) {
    return value ? formatTextileOptionLabel(value) : '-';
}

export default function Index({
    rollPackings,
    bundlePackings,
    balePackings,
    labels,
    challans,
    sourceTypeOptions,
    packingMaterialOptions,
    labelTypeOptions,
    unitOptions,
    lotReferenceOptions,
}: {
    rollPackings: WorkflowDocument[];
    bundlePackings: WorkflowDocument[];
    balePackings: WorkflowDocument[];
    labels: WorkflowDocument[];
    challans: WorkflowDocument[];
    sourceTypeOptions: string[];
    packingMaterialOptions: string[];
    labelTypeOptions: string[];
    unitOptions: string[];
    lotReferenceOptions: string[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const hasFineGrainedCapabilities = Object.keys(textileCapabilities).some((key) => key.startsWith('sales_'));
    const canPacking = !hasFineGrainedCapabilities || textileCapabilities.sales_challan_pod;

    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const visibleSections: PackingSection[] = canPacking ? [...PACKING_SECTIONS] : [];
    const activeSection = sectionParam && visibleSections.includes(sectionParam as PackingSection)
        ? sectionParam as PackingSection
        : (visibleSections[0] ?? 'roll-packing');

    const resolvedSourceTypeOptions = sourceTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedPackingMaterialOptions = packingMaterialOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedLabelTypeOptions = labelTypeOptions.map((value) => ({ value, label: formatTextileOptionLabel(value) }));
    const resolvedLotReferenceOptions = lotReferenceOptions.map((value) => ({ value, label: value }));
    const resolvedUnitOptions = buildUnitOptions(unitOptions);
    const challanOptions = createTextileWorkflowSelectOptions(challans);

    const rollForm = useForm({
        source_reference_type: resolvedSourceTypeOptions[0]?.value ?? 'challan',
        challan_id: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
        packing_material: resolvedPackingMaterialOptions[0]?.value ?? 'poly_wrap',
        weight: '',
        notes: '',
    });

    const bundleForm = useForm({
        source_reference_type: resolvedSourceTypeOptions[0]?.value ?? 'challan',
        challan_id: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
        packing_material: resolvedPackingMaterialOptions[0]?.value ?? 'carton_box',
        weight: '',
        notes: '',
    });

    const baleForm = useForm({
        source_reference_type: resolvedSourceTypeOptions[0]?.value ?? 'challan',
        challan_id: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
        packing_material: resolvedPackingMaterialOptions[0]?.value ?? 'jute_bale',
        weight: '',
        notes: '',
    });

    const labelForm = useForm({
        source_reference_type: resolvedSourceTypeOptions[0]?.value ?? 'challan',
        challan_id: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
        packing_material: resolvedPackingMaterialOptions[0]?.value ?? 'poly_wrap',
        label_type: resolvedLabelTypeOptions[0]?.value ?? 'barcode',
        label_code: '',
        weight: '',
        notes: '',
    });

    const issueLabel = (id: number) => {
        router.post(route('textile.packing.labels.issue'), { label_id: id }, { preserveScroll: true });
    };

    const allDocuments = [...rollPackings, ...bundlePackings, ...balePackings, ...labels];
    const issuedLabels = labels.filter((row) => row.status === 'approved').length;

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Packing') }]} pageTitle={t('Textile Packing')}>
            <Head title={t('Textile Packing')} />

            <TextileKpiOverview
                title={t('Packing Overview')}
                className="mb-6"
                items={[
                    { label: t('Total Packing Docs'), value: allDocuments.length, hint: t('Roll + Bundle + Bale + Label') },
                    { label: t('Roll Packings'), value: rollPackings.length, hint: t('Roll-wise packed quantity records') },
                    { label: t('Bundle Packings'), value: bundlePackings.length, hint: t('Bundle-level dispatch preparation') },
                    { label: t('Issued Labels'), value: issuedLabels, hint: t('Barcode/QR labels approved') },
                ]}
            />

            {canPacking ? (
                <Tabs
                    value={activeSection}
                    onValueChange={(value: string) => router.get(route('textile.packing.index', { section: value }), {}, { preserveState: true, replace: true })}
                    className="space-y-6"
                >
                    <TabsList className="grid w-full grid-cols-2 gap-2 h-auto p-1 md:grid-cols-4">
                        <TabsTrigger value="roll-packing">{t('Roll Packing')}</TabsTrigger>
                        <TabsTrigger value="bundle-packing">{t('Bundle Packing')}</TabsTrigger>
                        <TabsTrigger value="bale-packing">{t('Bale Packing')}</TabsTrigger>
                        <TabsTrigger value="labels">{t('Labels')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="roll-packing">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Create Roll Packing')} icon={Plus}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        rollForm.post(route('textile.packing.rolls.store'), {
                                            onSuccess: () => rollForm.reset('challan_id', 'lot_reference', 'quantity', 'weight', 'notes'),
                                        });
                                    }}
                                >
                                    <SelectField label={t('Source Type')} value={rollForm.data.source_reference_type} onChange={(value: string) => rollForm.setData('source_reference_type', value)} options={resolvedSourceTypeOptions} includeEmpty emptyLabel={t('Select source type')} helperText={t('Source types are managed from Master Setup > Packing Setup > Source Types.')} required />
                                    <SelectField label={t('Released Challan')} value={rollForm.data.challan_id} onChange={(value: string) => rollForm.setData('challan_id', value)} options={challanOptions} includeEmpty emptyLabel={t('Select released challan')} helperText={t('Packing links with released challan records.')} disabled={challanOptions.length === 0} disabledReason={t('No released challan found. Release challan from Sales first.')} />
                                    <SelectField label={t('Lot Reference')} value={rollForm.data.lot_reference} onChange={(value: string) => rollForm.setData('lot_reference', value)} options={resolvedLotReferenceOptions} includeEmpty emptyLabel={t('Select lot reference')} helperText={t('Lot references are derived from inventory lots and workflow records.')} disabled={resolvedLotReferenceOptions.length === 0} disabledReason={t('No lot options available yet. Create active lots first.')} required />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity')} type="number" value={rollForm.data.quantity} onChange={(value: string) => rollForm.setData('quantity', value)} required />
                                        <SelectField label={t('Unit')} value={rollForm.data.unit} onChange={(value: string) => rollForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Packing Material')} value={rollForm.data.packing_material} onChange={(value: string) => rollForm.setData('packing_material', value)} options={resolvedPackingMaterialOptions} includeEmpty emptyLabel={t('Select packing material')} helperText={t('Packing materials are managed from Master Setup > Packing Setup > Source Actions.')} required />
                                        <Field label={t('Weight')} type="number" value={rollForm.data.weight} onChange={(value: string) => rollForm.setData('weight', value)} />
                                    </div>
                                    <Field label={t('Notes')} value={rollForm.data.notes} onChange={(value: string) => rollForm.setData('notes', value)} />
                                    <Button type="submit" disabled={rollForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Roll Packing')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableCard
                                data={rollPackings}
                                columns={[
                                    ...createTextileWorkflowColumns(t),
                                    { key: 'packing_material', header: t('Material'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.packing_material) },
                                    { key: 'weight', header: t('Weight'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.weight ?? '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={Box} title={t('No roll packing found')} description={t('Create roll packing after challan release.')} />}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="bundle-packing">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Create Bundle Packing')} icon={Plus}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        bundleForm.post(route('textile.packing.bundles.store'), {
                                            onSuccess: () => bundleForm.reset('challan_id', 'lot_reference', 'quantity', 'weight', 'notes'),
                                        });
                                    }}
                                >
                                    <SelectField label={t('Source Type')} value={bundleForm.data.source_reference_type} onChange={(value: string) => bundleForm.setData('source_reference_type', value)} options={resolvedSourceTypeOptions} includeEmpty emptyLabel={t('Select source type')} helperText={t('Source types are managed from Master Setup > Packing Setup > Source Types.')} required />
                                    <SelectField label={t('Released Challan')} value={bundleForm.data.challan_id} onChange={(value: string) => bundleForm.setData('challan_id', value)} options={challanOptions} includeEmpty emptyLabel={t('Select released challan')} helperText={t('Packing links with released challan records.')} disabled={challanOptions.length === 0} disabledReason={t('No released challan found. Release challan from Sales first.')} />
                                    <SelectField label={t('Lot Reference')} value={bundleForm.data.lot_reference} onChange={(value: string) => bundleForm.setData('lot_reference', value)} options={resolvedLotReferenceOptions} includeEmpty emptyLabel={t('Select lot reference')} helperText={t('Lot references are derived from inventory lots and workflow records.')} disabled={resolvedLotReferenceOptions.length === 0} disabledReason={t('No lot options available yet. Create active lots first.')} required />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity')} type="number" value={bundleForm.data.quantity} onChange={(value: string) => bundleForm.setData('quantity', value)} required />
                                        <SelectField label={t('Unit')} value={bundleForm.data.unit} onChange={(value: string) => bundleForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Packing Material')} value={bundleForm.data.packing_material} onChange={(value: string) => bundleForm.setData('packing_material', value)} options={resolvedPackingMaterialOptions} includeEmpty emptyLabel={t('Select packing material')} helperText={t('Packing materials are managed from Master Setup > Packing Setup > Source Actions.')} required />
                                        <Field label={t('Weight')} type="number" value={bundleForm.data.weight} onChange={(value: string) => bundleForm.setData('weight', value)} />
                                    </div>
                                    <Field label={t('Notes')} value={bundleForm.data.notes} onChange={(value: string) => bundleForm.setData('notes', value)} />
                                    <Button type="submit" disabled={bundleForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Bundle Packing')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableCard
                                data={bundlePackings}
                                columns={[
                                    ...createTextileWorkflowColumns(t),
                                    { key: 'packing_material', header: t('Material'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.packing_material) },
                                    { key: 'weight', header: t('Weight'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.weight ?? '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={Box} title={t('No bundle packing found')} description={t('Create bundle packing from released challan lots.')} />}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="bale-packing">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Create Bale Packing')} icon={Plus}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        baleForm.post(route('textile.packing.bales.store'), {
                                            onSuccess: () => baleForm.reset('challan_id', 'lot_reference', 'quantity', 'weight', 'notes'),
                                        });
                                    }}
                                >
                                    <SelectField label={t('Source Type')} value={baleForm.data.source_reference_type} onChange={(value: string) => baleForm.setData('source_reference_type', value)} options={resolvedSourceTypeOptions} includeEmpty emptyLabel={t('Select source type')} helperText={t('Source types are managed from Master Setup > Packing Setup > Source Types.')} required />
                                    <SelectField label={t('Released Challan')} value={baleForm.data.challan_id} onChange={(value: string) => baleForm.setData('challan_id', value)} options={challanOptions} includeEmpty emptyLabel={t('Select released challan')} helperText={t('Packing links with released challan records.')} disabled={challanOptions.length === 0} disabledReason={t('No released challan found. Release challan from Sales first.')} />
                                    <SelectField label={t('Lot Reference')} value={baleForm.data.lot_reference} onChange={(value: string) => baleForm.setData('lot_reference', value)} options={resolvedLotReferenceOptions} includeEmpty emptyLabel={t('Select lot reference')} helperText={t('Lot references are derived from inventory lots and workflow records.')} disabled={resolvedLotReferenceOptions.length === 0} disabledReason={t('No lot options available yet. Create active lots first.')} required />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity')} type="number" value={baleForm.data.quantity} onChange={(value: string) => baleForm.setData('quantity', value)} required />
                                        <SelectField label={t('Unit')} value={baleForm.data.unit} onChange={(value: string) => baleForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Packing Material')} value={baleForm.data.packing_material} onChange={(value: string) => baleForm.setData('packing_material', value)} options={resolvedPackingMaterialOptions} includeEmpty emptyLabel={t('Select packing material')} helperText={t('Packing materials are managed from Master Setup > Packing Setup > Source Actions.')} required />
                                        <Field label={t('Weight')} type="number" value={baleForm.data.weight} onChange={(value: string) => baleForm.setData('weight', value)} />
                                    </div>
                                    <Field label={t('Notes')} value={baleForm.data.notes} onChange={(value: string) => baleForm.setData('notes', value)} />
                                    <Button type="submit" disabled={baleForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Bale Packing')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableCard
                                data={balePackings}
                                columns={[
                                    ...createTextileWorkflowColumns(t),
                                    { key: 'packing_material', header: t('Material'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.packing_material) },
                                    { key: 'weight', header: t('Weight'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.weight ?? '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={Box} title={t('No bale packing found')} description={t('Create bale packing for shipping units.')} />}
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="labels">
                        <div className="grid gap-6 xl:grid-cols-2">
                            <TextileFormCard title={t('Generate Label')} icon={QrCode}>
                                <form
                                    className="space-y-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        labelForm.post(route('textile.packing.labels.store'), {
                                            onSuccess: () => labelForm.reset('challan_id', 'lot_reference', 'quantity', 'label_code', 'weight', 'notes'),
                                        });
                                    }}
                                >
                                    <SelectField label={t('Source Type')} value={labelForm.data.source_reference_type} onChange={(value: string) => labelForm.setData('source_reference_type', value)} options={resolvedSourceTypeOptions} includeEmpty emptyLabel={t('Select source type')} helperText={t('Source types are managed from Master Setup > Packing Setup > Source Types.')} required />
                                    <SelectField label={t('Released Challan')} value={labelForm.data.challan_id} onChange={(value: string) => labelForm.setData('challan_id', value)} options={challanOptions} includeEmpty emptyLabel={t('Select released challan')} helperText={t('Label generation links with released challan records.')} disabled={challanOptions.length === 0} disabledReason={t('No released challan found. Release challan from Sales first.')} />
                                    <SelectField label={t('Label Type')} value={labelForm.data.label_type} onChange={(value: string) => labelForm.setData('label_type', value)} options={resolvedLabelTypeOptions} includeEmpty emptyLabel={t('Select label type')} helperText={t('Barcode and QR labels are both supported.')} required />
                                    <Field label={t('Label Code')} value={labelForm.data.label_code} onChange={(value: string) => labelForm.setData('label_code', value)} />
                                    <SelectField label={t('Lot Reference')} value={labelForm.data.lot_reference} onChange={(value: string) => labelForm.setData('lot_reference', value)} options={resolvedLotReferenceOptions} includeEmpty emptyLabel={t('Select lot reference')} helperText={t('Lot references are derived from inventory lots and workflow records.')} disabled={resolvedLotReferenceOptions.length === 0} disabledReason={t('No lot options available yet. Create active lots first.')} required />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Quantity')} type="number" value={labelForm.data.quantity} onChange={(value: string) => labelForm.setData('quantity', value)} required />
                                        <SelectField label={t('Unit')} value={labelForm.data.unit} onChange={(value: string) => labelForm.setData('unit', value)} options={resolvedUnitOptions} includeEmpty emptyLabel={t('Select unit')} helperText={t('Units are derived from Unit Conversion master.')} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <SelectField label={t('Packing Material')} value={labelForm.data.packing_material} onChange={(value: string) => labelForm.setData('packing_material', value)} options={resolvedPackingMaterialOptions} includeEmpty emptyLabel={t('Select packing material')} helperText={t('Packing materials are managed from Master Setup > Packing Setup > Source Actions.')} required />
                                        <Field label={t('Weight')} type="number" value={labelForm.data.weight} onChange={(value: string) => labelForm.setData('weight', value)} />
                                    </div>
                                    <Field label={t('Notes')} value={labelForm.data.notes} onChange={(value: string) => labelForm.setData('notes', value)} />
                                    <Button type="submit" disabled={labelForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Generate Label')}</Button>
                                </form>
                            </TextileFormCard>

                            <TextileDataTableSection
                                title={t('Label Records')}
                                data={labels}
                                columns={[
                                    ...createTextileWorkflowColumns(t, {
                                        actions: createTextileWorkflowActions([
                                            {
                                                statuses: textileActionableStatuses.draftOrApproved,
                                                actions: [
                                                    {
                                                        label: t('Issue Label'),
                                                        icon: Check,
                                                        onClick: (row) => issueLabel(row.id),
                                                        when: (row) => row.status !== 'approved',
                                                    },
                                                ],
                                                noVisibleActionContent: t('Label already issued'),
                                            },
                                        ]),
                                    }),
                                    { key: 'label_type', header: t('Type'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.label_type) },
                                    { key: 'label_code', header: t('Code'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.label_code || '-' },
                                    { key: 'packing_material', header: t('Material'), render: (_value: unknown, row: WorkflowDocument) => metadataLabel(row.metadata?.packing_material) },
                                    { key: 'weight', header: t('Weight'), render: (_value: unknown, row: WorkflowDocument) => row.metadata?.weight ?? '-' },
                                ]}
                                emptyState={<NoRecordsFound icon={QrCode} title={t('No labels found')} description={t('Generate barcode or QR labels for packed material.')} />}
                            />
                        </div>
                    </TabsContent>
                </Tabs>
            ) : (
                <NoRecordsFound icon={Box} title={t('Packing is not enabled')} description={t('Enable Sales Challan/POD capability in Textile Operating Model to use packing workflows.')} />
            )}
        </AuthenticatedLayout>
    );
}
