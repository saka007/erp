import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Archive,
    Boxes,
    Building2,
    CheckCheck,
    ClipboardCheck,
    LayoutDashboard,
    Layers,
    ListChecks,
    LockKeyhole,
    MoveRight,
    Package,
    PackageCheck,
    PackageOpen,
    Plus,
    SearchCheck,
    Shirt,
    Settings,
    ToggleLeft,
    Unlock,
    Warehouse,
} from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import NoRecordsFound from '@/components/no-records-found';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileDataTableSection } from '@/components/textile/textile-data-table-section';
import { TextileField } from '@/components/textile/textile-field';
import { TextileSelectField } from '@/components/textile/textile-select-field';
import { TextileSection } from '@/components/textile/textile-section';
import { TextileWorkspace } from '@/components/textile/textile-workspace';
import { getTextileWorkspace } from '@/components/textile/textile-workspaces';
import { TextileInfoPanel, MetricSummaryCard, type ActivityItem } from '@/components/textile/textile-info-panel';
import { TextileWorkflowSteps, type WorkflowStep } from '@/components/textile/textile-workflow-steps';
import { PageProps } from '@/types';

interface TextileLot {
    id: number;
    lot_reference: string;
    batch_number?: string | null;
    barcode?: string | null;
    qr_code?: string | null;
    material_type?: string | null;
    production_stage?: string | null;
    source_document_type?: string | null;
    source_document_id?: number | null;
    received_quantity: string;
    available_quantity: string;
    reserved_quantity: string;
    status: string;
    is_frozen?: boolean;
    freeze_note?: string | null;
    is_active?: boolean;
}

interface TextileMovement {
    id: number;
    movement_type: string;
    adjustment_direction?: string | null;
    lot_reference?: string | null;
    location_from?: string | null;
    location_to?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
}

interface TextileReservation {
    id: number;
    lot_reference: string;
    reference_type?: string | null;
    reference_id?: number | null;
    reserved_quantity: string;
    status: string;
}

interface TextileLocation {
    id: number;
    name: string;
    code?: string | null;
    rack?: string | null;
    bin?: string | null;
    location_type: string;
    is_active?: boolean;
}

interface TextileCycleCount {
    id: number;
    lot_reference: string;
    expected_quantity: string;
    counted_quantity: string;
    variance_quantity: string;
    adjustment_direction?: string | null;
    status: string;
}

interface InventoryFilters {
    movement_type: string;
    status: string;
    lot_reference: string;
    location: string;
}

interface InventoryKpi {
    label: string;
    value: string | number;
    hint?: string;
    icon?: React.ComponentType<{ className?: string }>;
}

interface InventoryProps {
    lots?: TextileLot[];
    movements?: TextileMovement[];
    reservations?: TextileReservation[];
    cycleCounts?: TextileCycleCount[];
    locations?: TextileLocation[];
    movementTypes?: string[];
    movementStatuses?: string[];
    filters?: InventoryFilters;
    recentActivity?: ActivityItem[];
    kpis?: Record<string, string | number>;
    materialTypeKpis?: Record<string, { label: string; icon: string; count: number; total_qty: number; available_qty: number }>;
    section?: string;
    materialType?: string | null;
}

const MATERIAL_TYPE_LABELS: Record<string, string> = {
    yarn: 'Yarn',
    beam: 'Beam',
    grey_fabric: 'Grey Fabric',
    finished_fabric: 'Finished Fabric',
    chemical: 'Chemical',
    packing_material: 'Packing Material',
};

const SECTION_MATERIAL_LABELS: Record<string, string> = {
    'yarn-stock': 'Yarn Stock',
    'beam-stock': 'Beam Stock',
    'grey-fabric': 'Grey Fabric',
    'finished-fabric': 'Finished Fabric',
    chemicals: 'Chemicals',
    'packing-materials': 'Packing Materials',
};

export default function Index({
    lots = [],
    movements = [],
    reservations = [],
    cycleCounts = [],
    locations = [],
    movementTypes = [],
    movementStatuses = [],
    filters = {
        movement_type: '',
        status: '',
        lot_reference: '',
        location: '',
    },
    recentActivity = [],
    materialTypeKpis = {},
    materialType,
}: InventoryProps) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const textileCapabilities = auth.user?.textile_capabilities || {};
    const inventoryWorkspace = getTextileWorkspace('inventory')!;
    const [openStep, setOpenStep] = useState<string | null>(new URLSearchParams(window.location.search).get('sub'));
    const sectionParam = new URLSearchParams(window.location.search).get('section') || 'overview';
    const activeMenuSection = inventoryWorkspace.sections.find((item) => item.id === sectionParam) ?? inventoryWorkspace.sections[0];
    const sectionMaterialType = materialType || (SECTION_MATERIAL_LABELS[sectionParam] ? sectionParam : null);

    const toNumber = (value: string | number | null | undefined) => {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        const parsed = Number.parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const visibleLots = useMemo(() => {
        if (!materialType) {
            return lots;
        }

        return lots.filter((lot) => lot.material_type === materialType);
    }, [lots, materialType]);

    const activeLots = visibleLots.filter((lot) => lot.is_active !== false);
    const frozenLots = activeLots.filter((lot) => lot.is_frozen === true);
    const totalAvailableQuantity = activeLots.reduce((sum, lot) => sum + toNumber(lot.available_quantity), 0);
    const openReservations = reservations.filter((reservation) => reservation.status !== 'released' && reservation.status !== 'cancelled');

    const materialSummaryRows = useMemo(() => {
        return Object.entries(materialTypeKpis).map(([key, entry]) => ({
            label: entry.label,
            value: entry.count,
            hint: `${entry.total_qty.toFixed(2)} total / ${entry.available_qty.toFixed(2)} available`,
        }));
    }, [materialTypeKpis]);

    const inventoryRows = useMemo(() => {
        return activeLots.map((lot) => ({
            type: lot.material_type || sectionMaterialType || 'other',
            lot_reference: lot.lot_reference,
            batch_number: lot.batch_number ?? '-',
            barcode: lot.barcode ?? '-',
            qr_code: lot.qr_code ?? '-',
            received_quantity: lot.received_quantity,
            available_quantity: lot.available_quantity,
            reserved_quantity: lot.reserved_quantity,
            production_stage: lot.production_stage ?? '-',
            status: lot.status,
            id: lot.id,
        }));
    }, [activeLots, sectionMaterialType]);

    const activeLotReferenceOptions = activeLots.map((lot) => lot.lot_reference);
    const allLotReferenceOptions = activeLots.map((lot) => lot.lot_reference);
    const activeLocationOptions = locations.map((location) => ({
        value: String(location.id),
        label: location.code ? `${location.name} (${location.code})` : location.name,
    }));
    const locationNameOptions = locations.map((location) => location.name);
    const activeLotIdOptions = activeLots.map((lot) => ({
        value: String(lot.id),
        label: `${lot.lot_reference}${lot.batch_number ? ` | ${lot.batch_number}` : ''}`,
    }));
    const freezableLotIdOptions = activeLots
        .filter((lot) => lot.is_frozen !== true)
        .map((lot) => ({
            value: String(lot.id),
            label: `${lot.lot_reference}${lot.batch_number ? ` | ${lot.batch_number}` : ''}`,
        }));
    const frozenLotIdOptions = activeLots
        .filter((lot) => lot.is_frozen === true)
        .map((lot) => ({
            value: String(lot.id),
            label: `${lot.lot_reference}${lot.batch_number ? ` | ${lot.batch_number}` : ''}`,
        }));
    const activeReservationOptions = reservations.map((reservation) => ({
        value: String(reservation.id),
        label: `${reservation.lot_reference} | Qty ${reservation.reserved_quantity} | ${reservation.status}`,
    }));
    const lotStatusOptions = ['active', 'hold', 'inactive'];
    const unitOptions = ['kg', 'mtr', 'pcs', 'cone', 'roll', 'set', 'rpm'];
    const reservationReferenceTypeOptions = ['sales_order', 'allocation', 'job_work_outward', 'production_batch', 'manual'];
    const allocationReferenceTypeOptions = ['allocation', 'dispatch', 'sales_order', 'manual'];

    const lotUpdateForm = useForm({ lot_id: '', status: 'hold' });
    const lotArchiveForm = useForm({ lot_id: '' });
    const lotFreezeForm = useForm({ lot_id: '', freeze_note: '' });
    const lotUnfreezeForm = useForm({ lot_id: '' });
    const movementFilterForm = useForm<InventoryFilters>({
        movement_type: filters.movement_type || '',
        status: filters.status || '',
        lot_reference: filters.lot_reference || '',
        location: filters.location || '',
    });
    const reservationReleaseForm = useForm({ reservation_id: '' });
    const reservationAllocateForm = useForm({
        reservation_id: '',
        allocation_reference_id: '',
        allocation_reference_type: 'allocation',
    });
    const physicalVerificationForm = useForm({ lot_reference: '', counted_quantity: '', location: '', unit: 'mtr' });
    const cycleCountForm = useForm({ lot_reference: '', counted_quantity: '', location: '', unit: 'mtr', notes: '' });
    const locationForm = useForm({ name: '', code: '', rack: '', bin: '', location_type: 'warehouse' });
    const locationArchiveForm = useForm({ location_id: '' });

    const submitLocation = (event: React.FormEvent) => {
        event.preventDefault();
        locationForm.post(route('textile.inventory.locations.store'), {
            onSuccess: () => locationForm.reset('name', 'code', 'rack', 'bin'),
        });
    };

    const submitLocationArchive = (event: React.FormEvent) => {
        event.preventDefault();
        locationArchiveForm.post(route('textile.inventory.locations.archive'), {
            onSuccess: () => locationArchiveForm.reset('location_id'),
        });
    };

    const submitMovementFilter = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(route('textile.inventory.index'), movementFilterForm.data as unknown as Record<string, string>, {
            preserveState: true,
            replace: true,
        });
    };

    const clearMovementFilter = () => {
        const cleared = { movement_type: '', status: '', lot_reference: '', location: '' };
        movementFilterForm.setData(cleared);
        router.get(route('textile.inventory.index'), cleared, {
            preserveState: true,
            replace: true,
        });
    };

    const submitReservationRelease = (event: React.FormEvent) => {
        event.preventDefault();
        reservationReleaseForm.post(route('textile.inventory.reservations.release'), {
            onSuccess: () => reservationReleaseForm.reset('reservation_id'),
        });
    };

    const submitReservationAllocate = (event: React.FormEvent) => {
        event.preventDefault();
        reservationAllocateForm.post(route('textile.inventory.reservations.allocate'), {
            onSuccess: () => reservationAllocateForm.reset('reservation_id', 'allocation_reference_id'),
        });
    };

    const submitLotUpdate = (event: React.FormEvent) => {
        event.preventDefault();
        lotUpdateForm.post(route('textile.inventory.lots.update'), {
            onSuccess: () => lotUpdateForm.reset('lot_id'),
        });
    };

    const submitLotArchive = (event: React.FormEvent) => {
        event.preventDefault();
        lotArchiveForm.post(route('textile.inventory.lots.archive'), {
            onSuccess: () => lotArchiveForm.reset('lot_id'),
        });
    };

    const submitLotFreeze = (event: React.FormEvent) => {
        event.preventDefault();
        lotFreezeForm.post(route('textile.inventory.lots.freeze'), {
            onSuccess: () => lotFreezeForm.reset('lot_id', 'freeze_note'),
        });
    };

    const submitLotUnfreeze = (event: React.FormEvent) => {
        event.preventDefault();
        lotUnfreezeForm.post(route('textile.inventory.lots.unfreeze'), {
            onSuccess: () => lotUnfreezeForm.reset('lot_id'),
        });
    };

    const submitPhysicalVerification = (event: React.FormEvent) => {
        event.preventDefault();
        physicalVerificationForm.post(route('textile.inventory.physical-verifications.store'), {
            onSuccess: () => physicalVerificationForm.reset('lot_reference', 'counted_quantity'),
        });
    };

    const submitCycleCount = (event: React.FormEvent) => {
        event.preventDefault();
        cycleCountForm.post(route('textile.inventory.cycle-counts.store'), {
            onSuccess: () => cycleCountForm.reset('lot_reference', 'counted_quantity', 'notes'),
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Textile') },
                { label: t('Inventory') },
                ...(activeMenuSection ? [{ label: t(activeMenuSection.label) }] : []),
            ]}
            pageTitle={t('Textile Inventory')}
            pageActions={(
                <Button
                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                    onClick={() => router.get(route('textile.inventory.index', { section: 'locations-controls' }), {}, { preserveState: true, replace: true })}
                >
                    <Settings className="h-4 w-4" />
                    {t('Locations & Controls')}
                </Button>
            )}
        >
            <Head title={t('Textile Inventory')} />

            <TextileWorkspace
                workspace={inventoryWorkspace}
                capabilities={textileCapabilities}
                kpis={(section) => {
                    if (section.id === 'overview') {
                        return [
                            { label: t('Active Lots'), value: activeLots.length, hint: t('Lots currently available for operations'), icon: Boxes },
                            { label: t('Frozen Lots'), value: frozenLots.length, hint: t('Temporarily blocked from transactions'), icon: LockKeyhole },
                            { label: t('Available Quantity'), value: totalAvailableQuantity.toFixed(2), hint: t('Total available across active lots'), icon: Warehouse },
                            { label: t('Open Reservations'), value: openReservations.length, hint: t('Reservations not yet released or cancelled'), icon: ClipboardCheck },
                        ];
                    }

                    if (section.id === 'locations-controls') {
                        return [
                            { label: t('Locations'), value: locations.length, hint: t('Stock locations'), icon: Building2 },
                            { label: t('Frozen Lots'), value: frozenLots.length, hint: t('Blocked from transactions'), icon: LockKeyhole },
                            { label: t('Cycle Counts'), value: cycleCounts.length, hint: t('Posted cycle counts'), icon: ListChecks },
                            { label: t('Active Lots'), value: activeLots.length, hint: t('Lots available for operations'), icon: Boxes },
                        ];
                    }

                    return [
                        { label: t('Lots'), value: activeLots.length, hint: t(`${SECTION_MATERIAL_LABELS[section.id] || 'Current'} lots in view`), icon: Boxes },
                        { label: t('Available Qty'), value: totalAvailableQuantity.toFixed(2), hint: t('Filtered available quantity'), icon: Warehouse },
                        { label: t('Frozen Lots'), value: frozenLots.length, hint: t('Lots on hold'), icon: LockKeyhole },
                        { label: t('Reserved Qty'), value: activeLots.reduce((sum, lot) => sum + toNumber(lot.reserved_quantity), 0).toFixed(2), hint: t('Reserved quantity in view'), icon: PackageCheck },
                    ];
                }}
                aside={(section) => (
                    <>
                        <TextileInfoPanel
                            stages={[
                                { id: 'lots', label: t('Active Lots'), count: activeLots.length, active: section.id !== 'overview' },
                                { id: 'reservations', label: t('Open Reservations'), count: openReservations.length, active: section.id === 'locations-controls' },
                                { id: 'cycle-counts', label: t('Cycle Counts'), count: cycleCounts.length, active: section.id === 'locations-controls' },
                            ]}
                            activities={recentActivity}
                        />
                        <MetricSummaryCard
                            title={t('Inventory Summary')}
                            rows={[
                                { label: t('Active Lots'), value: activeLots.length },
                                { label: t('Frozen Lots'), value: frozenLots.length },
                                { label: t('Open Reservations'), value: openReservations.length },
                                { label: t('Available Qty'), value: totalAvailableQuantity.toFixed(2) },
                            ]}
                        />
                        <MetricSummaryCard
                            title={t('Material Mix')}
                            rows={section.id === 'overview' ? materialSummaryRows : [
                                { label: t('Current Section'), value: t(SECTION_MATERIAL_LABELS[section.id] || 'Controls') },
                                { label: t('Filtered Lots'), value: activeLots.length },
                                { label: t('Filtered Qty'), value: totalAvailableQuantity.toFixed(2) },
                            ]}
                        />
                    </>
                )}
                >
                {(section) => {
                    if (section.id === 'overview') {
                        return (
                            <TextileSection
                                table={
                                    <TextileDataTableCard
                                        data={inventoryRows}
                                        columns={[
                                            { key: 'type', header: t('Type'), render: (_value: unknown, row: { type: string }) => MATERIAL_TYPE_LABELS[row.type] || row.type },
                                            { key: 'lot_reference', header: t('Lot Reference') },
                                            { key: 'batch_number', header: t('Batch') },
                                            { key: 'received_quantity', header: t('Received') },
                                            { key: 'available_quantity', header: t('Available') },
                                            { key: 'reserved_quantity', header: t('Reserved') },
                                            { key: 'production_stage', header: t('Stage') },
                                            { key: 'status', header: t('Status') },
                                        ]}
                                        emptyState={<NoRecordsFound icon={Boxes} title={t('No inventory records yet')} description={t('Auto-created lots from upstream documents will appear here.')} />}
                                    />
                                }
                            />
                        );
                    }

                    if (section.id === 'locations-controls') {
                        const steps: WorkflowStep[] = [];
                        steps.push({
                            id: 'location-create',
                            title: t('Create Location'),
                            icon: Building2,
                            count: locations.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <div className="mb-5 flex items-center gap-2">
                                            <Boxes className="h-5 w-5 text-violet-600" />
                                            <h2 className="font-semibold">{t('Create Location')}</h2>
                                        </div>
                                        <form onSubmit={submitLocation} className="grid gap-4 xl:grid-cols-4">
                                            <TextileField label={t('Location Name')} value={locationForm.data.name} onChange={(value) => locationForm.setData('name', value)} required />
                                            <TextileField label={t('Code')} value={locationForm.data.code} onChange={(value) => locationForm.setData('code', value)} />
                                            <TextileField label={t('Rack')} value={locationForm.data.rack} onChange={(value) => locationForm.setData('rack', value)} />
                                            <TextileField label={t('Bin')} value={locationForm.data.bin} onChange={(value) => locationForm.setData('bin', value)} />
                                            <TextileSelectField label={t('Location Type')} value={locationForm.data.location_type} onChange={(value) => locationForm.setData('location_type', value)} options={['warehouse', 'loom-floor', 'process-house', 'dispatch', 'quarantine']} required />
                                            <div className="flex items-end"><Button type="submit" disabled={locationForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Location')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'location-archive',
                            title: t('Archive Location'),
                            icon: Archive,
                            count: locations.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitLocationArchive} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Archive Location')} value={locationArchiveForm.data.location_id} onChange={(value) => locationArchiveForm.setData('location_id', value)} options={activeLocationOptions} includeEmpty emptyLabel={t('Select active location')} required />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={locationArchiveForm.processing} className="w-full">{t('Archive Location')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'lot-status-update',
                            title: t('Update Lot'),
                            icon: ToggleLeft,
                            count: activeLots.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitLotUpdate} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Update Lot Status')} value={lotUpdateForm.data.lot_id} onChange={(value) => lotUpdateForm.setData('lot_id', value)} options={activeLotIdOptions} includeEmpty emptyLabel={t('Select active lot')} required />
                                            <TextileSelectField label={t('New Status')} value={lotUpdateForm.data.status} onChange={(value) => lotUpdateForm.setData('status', value)} options={lotStatusOptions} required />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotUpdateForm.processing} className="w-full">{t('Update Lot')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'lot-status-archive',
                            title: t('Archive Lot'),
                            icon: Archive,
                            count: activeLots.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitLotArchive} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Archive Lot')} value={lotArchiveForm.data.lot_id} onChange={(value) => lotArchiveForm.setData('lot_id', value)} options={activeLotIdOptions} includeEmpty emptyLabel={t('Select active lot')} required />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotArchiveForm.processing} className="w-full">{t('Archive Lot')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'lot-freeze',
                            title: t('Freeze Lot'),
                            icon: LockKeyhole,
                            count: frozenLots.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitLotFreeze} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Freeze Lot')} value={lotFreezeForm.data.lot_id} onChange={(value) => lotFreezeForm.setData('lot_id', value)} options={freezableLotIdOptions} includeEmpty emptyLabel={t('Select lot to freeze')} required />
                                            <TextileField label={t('Freeze Note')} value={lotFreezeForm.data.freeze_note} onChange={(value) => lotFreezeForm.setData('freeze_note', value)} />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotFreezeForm.processing} className="w-full">{t('Freeze Lot')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'lot-unfreeze',
                            title: t('Unfreeze Lot'),
                            icon: Unlock,
                            count: frozenLots.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitLotUnfreeze} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Unfreeze Lot')} value={lotUnfreezeForm.data.lot_id} onChange={(value) => lotUnfreezeForm.setData('lot_id', value)} options={frozenLotIdOptions} includeEmpty emptyLabel={t('Select lot to unfreeze')} required />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotUnfreezeForm.processing} className="w-full">{t('Unfreeze Lot')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'physical-verification',
                            title: t('Physical Verification'),
                            icon: SearchCheck,
                            count: cycleCounts.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitPhysicalVerification} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Verify Lot')} value={physicalVerificationForm.data.lot_reference} onChange={(value) => physicalVerificationForm.setData('lot_reference', value)} options={allLotReferenceOptions} includeEmpty emptyLabel={t('Select active lot')} required />
                                            <TextileField label={t('Counted Quantity')} type="number" value={physicalVerificationForm.data.counted_quantity} onChange={(value) => physicalVerificationForm.setData('counted_quantity', value)} required />
                                            <TextileSelectField label={t('Location')} value={physicalVerificationForm.data.location} onChange={(value) => physicalVerificationForm.setData('location', value)} options={locationNameOptions} includeEmpty emptyLabel={t('Optional location')} />
                                            <div className="grid grid-cols-2 gap-2">
                                                <TextileSelectField label={t('Unit')} value={physicalVerificationForm.data.unit} onChange={(value) => physicalVerificationForm.setData('unit', value)} options={unitOptions} includeEmpty emptyLabel={t('Select unit')} />
                                                <div className="flex items-end"><Button type="submit" variant="outline" disabled={physicalVerificationForm.processing} className="w-full">{t('Post Verification')}</Button></div>
                                            </div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'cycle-count',
                            title: t('Cycle Count'),
                            icon: ListChecks,
                            count: cycleCounts.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitCycleCount} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Cycle Count Lot')} value={cycleCountForm.data.lot_reference} onChange={(value) => cycleCountForm.setData('lot_reference', value)} options={allLotReferenceOptions} includeEmpty emptyLabel={t('Select active lot')} required />
                                            <TextileField label={t('Counted Quantity')} type="number" value={cycleCountForm.data.counted_quantity} onChange={(value) => cycleCountForm.setData('counted_quantity', value)} required />
                                            <TextileSelectField label={t('Location')} value={cycleCountForm.data.location} onChange={(value) => cycleCountForm.setData('location', value)} options={locationNameOptions} includeEmpty emptyLabel={t('Optional location')} />
                                            <TextileSelectField label={t('Unit')} value={cycleCountForm.data.unit} onChange={(value) => cycleCountForm.setData('unit', value)} options={unitOptions} includeEmpty emptyLabel={t('Select unit')} />
                                            <div className="xl:col-span-4">
                                                <TextileField label={t('Notes')} value={cycleCountForm.data.notes} onChange={(value) => cycleCountForm.setData('notes', value)} />
                                                <Button type="submit" variant="outline" disabled={cycleCountForm.processing} className="mt-2 w-full">{t('Post Cycle Count')}</Button>
                                            </div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'reservation-release',
                            title: t('Release Reservation'),
                            icon: CheckCheck,
                            count: openReservations.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitReservationRelease} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Reservation ID')} value={reservationReleaseForm.data.reservation_id} onChange={(value) => reservationReleaseForm.setData('reservation_id', value)} options={activeReservationOptions} includeEmpty emptyLabel={t('Select active reservation')} required />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={reservationReleaseForm.processing} className="w-full">{t('Release Reservation')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });
                        steps.push({
                            id: 'reservation-allocate',
                            title: t('Allocate Reservation'),
                            icon: ClipboardCheck,
                            count: openReservations.length,
                            form: (
                                <Card>
                                    <CardContent className="p-5">
                                        <form onSubmit={submitReservationAllocate} className="grid gap-4 xl:grid-cols-4">
                                            <TextileSelectField label={t('Allocate Reservation ID')} value={reservationAllocateForm.data.reservation_id} onChange={(value) => reservationAllocateForm.setData('reservation_id', value)} options={activeReservationOptions} includeEmpty emptyLabel={t('Select active reservation')} required />
                                            <TextileField label={t('Allocation Reference ID')} type="number" value={reservationAllocateForm.data.allocation_reference_id} onChange={(value) => reservationAllocateForm.setData('allocation_reference_id', value)} required />
                                            <TextileSelectField label={t('Allocation Reference Type')} value={reservationAllocateForm.data.allocation_reference_type} onChange={(value) => reservationAllocateForm.setData('allocation_reference_type', value)} options={allocationReferenceTypeOptions} includeEmpty emptyLabel={t('Select allocation reference type')} />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={reservationAllocateForm.processing} className="w-full">{t('Link Allocation')}</Button></div>
                                        </form>
                                    </CardContent>
                                </Card>
                            ),
                        });

                        return (
                            <div className="space-y-6">
                                <TextileWorkflowSteps
                                    steps={steps}
                                    openId={openStep}
                                    onOpenChange={setOpenStep}
                                    records={
                                        <div className="grid gap-6 xl:grid-cols-2">
                                            <TextileDataTableSection title={t('Locations')} data={locations} columns={[{ key: 'name', header: t('Location') }, { key: 'code', header: t('Code') }, { key: 'rack', header: t('Rack') }, { key: 'bin', header: t('Bin') }, { key: 'location_type', header: t('Type') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile locations found')} description={t('Create dedicated locations to control stock movement flow.')} />} />
                                            <TextileDataTableSection title={t('Lots')} data={visibleLots} columns={[{ key: 'lot_reference', header: t('Lot Reference') }, { key: 'batch_number', header: t('Batch') }, { key: 'material_type', header: t('Material Type') }, { key: 'status', header: t('Status') }, { key: 'is_frozen', header: t('Frozen') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create or auto-generate lots to begin tracking stock.')} />} />
                                            <TextileDataTableSection title={t('Cycle Counts')} data={cycleCounts} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'expected_quantity', header: t('Expected') }, { key: 'counted_quantity', header: t('Counted') }, { key: 'variance_quantity', header: t('Variance') }, { key: 'adjustment_direction', header: t('Direction') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No cycle counts found')} description={t('Post a cycle count to track counted variance by lot.')} />} />
                                            <TextileDataTableSection title={t('Reservations')} data={reservations} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'reserved_quantity', header: t('Reserved Qty') }, { key: 'reference_type', header: t('Reference Type') }, { key: 'reference_id', header: t('Reference ID') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile reservations found')} description={t('Reserve stock to control lot availability by demand.')} />} />
                                        </div>
                                    }
                                />
                            </div>
                        );
                    }

                    return (
                        <TextileSection
                            table={
                                <TextileDataTableCard
                                    data={visibleLots}
                                    columns={[
                                        { key: 'lot_reference', header: t('Lot Reference') },
                                        { key: 'batch_number', header: t('Batch') },
                                        { key: 'barcode', header: t('Barcode') },
                                        { key: 'material_type', header: t('Type'), render: (_value: unknown, row: TextileLot) => MATERIAL_TYPE_LABELS[row.material_type || ''] || row.material_type || '-' },
                                        { key: 'production_stage', header: t('Stage') },
                                        { key: 'received_quantity', header: t('Received') },
                                        { key: 'available_quantity', header: t('Available') },
                                        { key: 'reserved_quantity', header: t('Reserved') },
                                        { key: 'status', header: t('Status') },
                                    ]}
                                    emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Auto-created lots for this material type will appear here.')} />}
                                />
                            }
                        />
                    );
                }}
            </TextileWorkspace>
        </AuthenticatedLayout>
    );
}
