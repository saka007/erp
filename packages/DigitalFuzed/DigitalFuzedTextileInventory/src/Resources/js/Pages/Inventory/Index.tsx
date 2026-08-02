import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Boxes, MoveRight, Plus } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileField } from '@/components/textile/textile-field';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { TextileSelectField } from '@/components/textile/textile-select-field';

interface TextileLot {
    id: number;
    lot_reference: string;
    batch_number?: string | null;
    barcode?: string | null;
    qr_code?: string | null;
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
    status?: string | null;
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

export default function Index({
    lots,
    movements,
    reservations,
    cycleCounts,
    locations,
    movementTypes,
    movementStatuses,
    filters,
}: {
    lots: TextileLot[];
    movements: TextileMovement[];
    reservations: TextileReservation[];
    cycleCounts: TextileCycleCount[];
    locations: TextileLocation[];
    movementTypes: string[];
    movementStatuses: string[];
    filters: InventoryFilters;
}) {
    const { t } = useTranslation();
    const searchParams = new URLSearchParams(window.location.search);
    const sectionParam = searchParams.get('section');
    const subSectionParam = searchParams.get('sub');
    const validSections = new Set(['transactions', 'controls', 'records']);
    const activeSection = sectionParam && validSections.has(sectionParam) ? sectionParam : 'transactions';

    const sectionSubsections: Record<string, string[]> = {
        transactions: ['lot-create', 'movement-create', 'reservation-create'],
        controls: [
            'location-create',
            'location-archive',
            'lot-status-update',
            'lot-status-archive',
            'lot-freeze',
            'lot-unfreeze',
            'physical-verification',
            'cycle-count',
            'reservation-release',
            'reservation-allocate',
        ],
        records: ['record-locations', 'record-lots', 'record-movements', 'record-cycle-counts', 'record-reservations'],
    };

    const defaultSubsectionBySection: Record<string, string> = {
        transactions: 'lot-create',
        controls: 'location-create',
        records: 'record-locations',
    };

    const resolveSubsection = (section: string) => {
        const validSubsections = sectionSubsections[section] || [];
        if (subSectionParam && validSubsections.includes(subSectionParam)) {
            return subSectionParam;
        }

        return defaultSubsectionBySection[section] || '';
    };

    const activeSubsection = resolveSubsection(activeSection);

    const toNumber = (value: string | number | null | undefined) => {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        const parsed = Number.parseFloat(value || '0');
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const activeLots = lots.filter((lot) => lot.is_active !== false);
    const frozenLots = activeLots.filter((lot) => lot.is_frozen === true);
    const totalAvailableQuantity = activeLots.reduce((sum, lot) => sum + toNumber(lot.available_quantity), 0);
    const openReservations = reservations.filter((reservation) => reservation.status !== 'released' && reservation.status !== 'cancelled');

    const lotForm = useForm({
        lot_reference: '',
        batch_number: '',
        barcode: '',
        qr_code: '',
        received_quantity: '',
        available_quantity: '',
        status: 'active',
    });

    const lotUpdateForm = useForm({
        lot_id: '',
        status: 'hold',
    });

    const lotArchiveForm = useForm({
        lot_id: '',
    });

    const lotFreezeForm = useForm({
        lot_id: '',
        freeze_note: '',
    });

    const lotUnfreezeForm = useForm({
        lot_id: '',
    });

    const movementForm = useForm({
        movement_type: 'receipt',
        adjustment_direction: 'decrease',
        lot_reference: '',
        location_from: '',
        location_to: '',
        quantity: '',
        unit: 'mtr',
        status: 'posted',
        notes: '',
    });

    const reservationForm = useForm({
        lot_reference: '',
        quantity: '',
        reference_type: 'sales_order',
        reference_id: '',
    });

    const locationForm = useForm({
        name: '',
        code: '',
        rack: '',
        bin: '',
        location_type: 'warehouse',
    });

    const locationArchiveForm = useForm({
        location_id: '',
    });

    const movementFilterForm = useForm<InventoryFilters>({
        movement_type: filters.movement_type || '',
        status: filters.status || '',
        lot_reference: filters.lot_reference || '',
        location: filters.location || '',
    });

    const reservationReleaseForm = useForm({
        reservation_id: '',
    });

    const reservationAllocateForm = useForm({
        reservation_id: '',
        allocation_reference_id: '',
        allocation_reference_type: 'allocation',
    });

    const physicalVerificationForm = useForm({
        lot_reference: '',
        counted_quantity: '',
        location: '',
        unit: 'mtr',
    });

    const cycleCountForm = useForm({
        lot_reference: '',
        counted_quantity: '',
        location: '',
        unit: 'mtr',
        notes: '',
    });

    const submitLot = (event: React.FormEvent) => {
        event.preventDefault();
        lotForm.post(route('textile.inventory.lots.store'), {
            onSuccess: () => lotForm.reset('lot_reference', 'batch_number', 'barcode', 'qr_code', 'received_quantity', 'available_quantity'),
        });
    };

    const submitMovement = (event: React.FormEvent) => {
        event.preventDefault();
        movementForm.post(route('textile.inventory.movements.store'), {
            onSuccess: () => movementForm.reset('lot_reference', 'location_from', 'location_to', 'quantity', 'notes'),
        });
    };

    const submitReservation = (event: React.FormEvent) => {
        event.preventDefault();
        reservationForm.post(route('textile.inventory.reservations.store'), {
            onSuccess: () => reservationForm.reset('lot_reference', 'quantity', 'reference_id'),
        });
    };

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
        const payload: Record<string, string> = {
            movement_type: movementFilterForm.data.movement_type,
            status: movementFilterForm.data.status,
            lot_reference: movementFilterForm.data.lot_reference,
            location: movementFilterForm.data.location,
        };
        router.get(route('textile.inventory.index'), payload, {
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
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Inventory') }]} pageTitle={t('Textile Inventory')}>
            <Head title={t('Textile Inventory')} />

            <TextileKpiOverview
                title={t('Inventory Overview')}
                className="mb-6"
                items={[
                    { label: t('Active Lots'), value: activeLots.length, hint: t('Lots currently available for operations') },
                    { label: t('Frozen Lots'), value: frozenLots.length, hint: t('Temporarily blocked from transactions') },
                    { label: t('Available Quantity'), value: totalAvailableQuantity.toFixed(2), hint: t('Total available across active lots') },
                    { label: t('Open Reservations'), value: openReservations.length, hint: t('Reservations not yet released or cancelled') },
                ]}
            />

            <Tabs
                value={activeSection}
                onValueChange={(value) => router.get(route('textile.inventory.index'), { section: value, sub: defaultSubsectionBySection[value] || '' }, { preserveState: true, replace: true })}
                className="space-y-6"
            >
                <TabsList className="grid w-full grid-cols-3 h-auto p-1">
                    <TabsTrigger value="transactions">{t('Transactions')}</TabsTrigger>
                    <TabsTrigger value="controls">{t('Controls')}</TabsTrigger>
                    <TabsTrigger value="records">{t('Records')}</TabsTrigger>
                </TabsList>

                <TabsContent value="transactions" className="space-y-6">
                    <Tabs
                        value={activeSection === 'transactions' ? activeSubsection : defaultSubsectionBySection.transactions}
                        onValueChange={(value) => router.get(route('textile.inventory.index'), { section: 'transactions', sub: value }, { preserveState: true, replace: true })}
                        className="space-y-4"
                    >
                        <TabsList className="grid w-full grid-cols-1 gap-2 h-auto p-1 sm:grid-cols-3">
                            <TabsTrigger value="lot-create">{t('New Lot')}</TabsTrigger>
                            <TabsTrigger value="movement-create">{t('Record Movement')}</TabsTrigger>
                            <TabsTrigger value="reservation-create">{t('Reserve Quantity')}</TabsTrigger>
                        </TabsList>

                        <TabsContent value="lot-create" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('New Lot')}</h2>
                                    </div>
                                    <form onSubmit={submitLot} className="space-y-4">
                                        <TextileField label={t('Lot Reference')} value={lotForm.data.lot_reference} onChange={(value) => lotForm.setData('lot_reference', value)} required />
                                        <TextileField label={t('Batch Number')} value={lotForm.data.batch_number} onChange={(value) => lotForm.setData('batch_number', value)} />
                                        <TextileField label={t('Barcode')} value={lotForm.data.barcode} onChange={(value) => lotForm.setData('barcode', value)} />
                                        <TextileField label={t('QR Code')} value={lotForm.data.qr_code} onChange={(value) => lotForm.setData('qr_code', value)} />
                                        <TextileField label={t('Received Quantity')} type="number" value={lotForm.data.received_quantity} onChange={(value) => lotForm.setData('received_quantity', value)} required />
                                        <TextileField label={t('Opening Available Quantity')} type="number" value={lotForm.data.available_quantity} onChange={(value) => lotForm.setData('available_quantity', value)} />
                                        <TextileField label={t('Status')} value={lotForm.data.status} onChange={(value) => lotForm.setData('status', value)} />
                                        <Button type="submit" disabled={lotForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Lot')}</Button>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={lots} columns={[{ key: 'lot_reference', header: t('Lot Reference') }, { key: 'batch_number', header: t('Batch'), render: optional }, { key: 'barcode', header: t('Barcode'), render: optional }, { key: 'qr_code', header: t('QR Code'), render: optional }, { key: 'received_quantity', header: t('Received') }, { key: 'available_quantity', header: t('Available') }, { key: 'reserved_quantity', header: t('Reserved') }, { key: 'status', header: t('Status') }, { key: 'is_frozen', header: t('Frozen'), render: booleanLabel }, { key: 'freeze_note', header: t('Freeze Note'), render: optional }, { key: 'id', header: t('Details'), render: (value: number) => (<Button type="button" variant="ghost" size="sm" onClick={() => router.visit(route('textile.inventory.lots.show', value))}>{t('View')}</Button>) }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create the first lot to begin tracking stock by lot.')} />} />
                        </TabsContent>

                        <TabsContent value="movement-create" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <MoveRight className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Record Movement')}</h2>
                                    </div>
                                    <form onSubmit={submitMovement} className="space-y-4">
                                        <TextileSelectField label={t('Movement Type')} value={movementForm.data.movement_type} onChange={(value) => movementForm.setData('movement_type', value)} options={movementTypes} required />
                                        {movementForm.data.movement_type === 'adjustment' && (
                                            <TextileSelectField label={t('Adjustment Direction')} value={movementForm.data.adjustment_direction} onChange={(value) => movementForm.setData('adjustment_direction', value)} options={['increase', 'decrease']} required />
                                        )}
                                        <TextileField label={t('Lot Reference')} value={movementForm.data.lot_reference} onChange={(value) => movementForm.setData('lot_reference', value)} />
                                        <div className="grid grid-cols-2 gap-3">
                                            <TextileSelectField label={t('From Location')} value={movementForm.data.location_from} onChange={(value) => movementForm.setData('location_from', value)} options={locations.map((location) => location.name)} includeEmpty emptyLabel={t('Select location')} />
                                            <TextileSelectField label={t('To Location')} value={movementForm.data.location_to} onChange={(value) => movementForm.setData('location_to', value)} options={locations.map((location) => location.name)} includeEmpty emptyLabel={t('Select location')} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <TextileField label={t('Quantity')} type="number" value={movementForm.data.quantity} onChange={(value) => movementForm.setData('quantity', value)} required />
                                            <TextileField label={t('Unit')} value={movementForm.data.unit} onChange={(value) => movementForm.setData('unit', value)} />
                                        </div>
                                        <TextileSelectField label={t('Status')} value={movementForm.data.status} onChange={(value) => movementForm.setData('status', value)} options={movementStatuses} required />
                                        <TextileField label={t('Notes')} value={movementForm.data.notes} onChange={(value) => movementForm.setData('notes', value)} />
                                        <Button type="submit" disabled={movementForm.processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Save Movement')}</Button>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={movements} columns={[{ key: 'movement_type', header: t('Type') }, { key: 'adjustment_direction', header: t('Direction'), render: optional }, { key: 'lot_reference', header: t('Lot'), render: optional }, { key: 'location_from', header: t('From'), render: optional }, { key: 'location_to', header: t('To'), render: optional }, { key: 'quantity', header: t('Qty') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={MoveRight} title={t('No textile movements found')} description={t('Record the first inventory movement to build stock history.')} />} />
                        </TabsContent>

                        <TabsContent value="reservation-create" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Reserve Lot Quantity')}</h2>
                                    </div>
                                    <form onSubmit={submitReservation} className="grid gap-4 xl:grid-cols-4">
                                        <TextileField label={t('Lot Reference')} value={reservationForm.data.lot_reference} onChange={(value) => reservationForm.setData('lot_reference', value)} required />
                                        <TextileField label={t('Quantity')} type="number" value={reservationForm.data.quantity} onChange={(value) => reservationForm.setData('quantity', value)} required />
                                        <TextileField label={t('Reference Type')} value={reservationForm.data.reference_type} onChange={(value) => reservationForm.setData('reference_type', value)} />
                                        <TextileField label={t('Reference ID')} type="number" value={reservationForm.data.reference_id} onChange={(value) => reservationForm.setData('reference_id', value)} />
                                        <div className="xl:col-span-4">
                                            <Button type="submit" disabled={reservationForm.processing} className="w-full xl:w-auto"><Plus className="mr-2 h-4 w-4" />{t('Reserve Quantity')}</Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={reservations} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'reserved_quantity', header: t('Reserved Qty') }, { key: 'reference_type', header: t('Reference Type'), render: optional }, { key: 'reference_id', header: t('Reference ID'), render: optionalNumber }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile reservations found')} description={t('Reserve stock to control lot availability by demand.')} />} />
                        </TabsContent>
                    </Tabs>
                </TabsContent>

                <TabsContent value="controls" className="space-y-6">
                    <Tabs
                        value={activeSection === 'controls' ? activeSubsection : defaultSubsectionBySection.controls}
                        onValueChange={(value) => router.get(route('textile.inventory.index'), { section: 'controls', sub: value }, { preserveState: true, replace: true })}
                        className="space-y-4"
                    >
                        <TabsList className="grid w-full grid-cols-1 gap-2 h-auto p-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                            <TabsTrigger value="location-create">{t('Create Location')}</TabsTrigger>
                            <TabsTrigger value="location-archive">{t('Archive Location')}</TabsTrigger>
                            <TabsTrigger value="lot-status-update">{t('Update Lot')}</TabsTrigger>
                            <TabsTrigger value="lot-status-archive">{t('Archive Lot')}</TabsTrigger>
                            <TabsTrigger value="lot-freeze">{t('Freeze Lot')}</TabsTrigger>
                            <TabsTrigger value="lot-unfreeze">{t('Unfreeze Lot')}</TabsTrigger>
                            <TabsTrigger value="physical-verification">{t('Physical Verification')}</TabsTrigger>
                            <TabsTrigger value="cycle-count">{t('Cycle Count')}</TabsTrigger>
                            <TabsTrigger value="reservation-release">{t('Release Reservation')}</TabsTrigger>
                            <TabsTrigger value="reservation-allocate">{t('Allocate Reservation')}</TabsTrigger>
                        </TabsList>

                        <TabsContent value="location-create" className="space-y-6">
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

                            <TextileDataTableCard data={locations} columns={[{ key: 'name', header: t('Location') }, { key: 'code', header: t('Code'), render: optional }, { key: 'rack', header: t('Rack'), render: optional }, { key: 'bin', header: t('Bin'), render: optional }, { key: 'location_type', header: t('Type') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile locations found')} description={t('Create dedicated locations to control stock movement flow.')} />} />
                        </TabsContent>

                        <TabsContent value="location-archive" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Archive Location')}</h2>
                                    </div>
                                    <form onSubmit={submitLocationArchive} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Archive Location')} value={locationArchiveForm.data.location_id} onChange={(value) => locationArchiveForm.setData('location_id', value)} options={locations.map((location) => String(location.id))} includeEmpty emptyLabel={t('Select active location ID')} required />
                                        <div className="flex items-end"><Button type="submit" variant="outline" disabled={locationArchiveForm.processing} className="w-full">{t('Archive Location')}</Button></div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={locations} columns={[{ key: 'name', header: t('Location') }, { key: 'code', header: t('Code'), render: optional }, { key: 'location_type', header: t('Type') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile locations found')} description={t('Create dedicated locations to control stock movement flow.')} />} />
                        </TabsContent>

                        <TabsContent value="lot-status-update" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Update Lot Status')}</h2>
                                    </div>
                                    <form onSubmit={submitLotUpdate} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Update Lot Status')} value={lotUpdateForm.data.lot_id} onChange={(value) => lotUpdateForm.setData('lot_id', value)} options={lots.filter((lot) => lot.is_active !== false).map((lot) => String(lot.id))} includeEmpty emptyLabel={t('Select active lot ID')} required />
                                        <TextileSelectField label={t('New Status')} value={lotUpdateForm.data.status} onChange={(value) => lotUpdateForm.setData('status', value)} options={['active', 'hold', 'inactive']} required />
                                        <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotUpdateForm.processing} className="w-full">{t('Update Lot')}</Button></div>
                                    </form>

                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={lots} columns={[{ key: 'lot_reference', header: t('Lot Reference') }, { key: 'status', header: t('Status') }, { key: 'is_frozen', header: t('Frozen'), render: booleanLabel }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create the first lot to begin tracking stock by lot.')} />} />
                        </TabsContent>

                        <TabsContent value="lot-status-archive" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Archive Lot')}</h2>
                                    </div>
                                    <form onSubmit={submitLotArchive} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Archive Lot')} value={lotArchiveForm.data.lot_id} onChange={(value) => lotArchiveForm.setData('lot_id', value)} options={lots.filter((lot) => lot.is_active !== false).map((lot) => String(lot.id))} includeEmpty emptyLabel={t('Select active lot ID')} required />
                                        <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotArchiveForm.processing} className="w-full">{t('Archive Lot')}</Button></div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={lots} columns={[{ key: 'lot_reference', header: t('Lot Reference') }, { key: 'status', header: t('Status') }, { key: 'is_frozen', header: t('Frozen'), render: booleanLabel }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create the first lot to begin tracking stock by lot.')} />} />
                        </TabsContent>

                        <TabsContent value="lot-freeze" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Freeze Lot')}</h2>
                                    </div>
                                    <form onSubmit={submitLotFreeze} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Freeze Lot')} value={lotFreezeForm.data.lot_id} onChange={(value) => lotFreezeForm.setData('lot_id', value)} options={lots.filter((lot) => lot.is_active !== false && lot.is_frozen !== true).map((lot) => String(lot.id))} includeEmpty emptyLabel={t('Select lot to freeze')} required />
                                        <TextileField label={t('Freeze Note')} value={lotFreezeForm.data.freeze_note} onChange={(value) => lotFreezeForm.setData('freeze_note', value)} />
                                        <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotFreezeForm.processing} className="w-full">{t('Freeze Lot')}</Button></div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={lots} columns={[{ key: 'lot_reference', header: t('Lot Reference') }, { key: 'is_frozen', header: t('Frozen'), render: booleanLabel }, { key: 'freeze_note', header: t('Freeze Note'), render: optional }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create the first lot to begin tracking stock by lot.')} />} />
                        </TabsContent>

                        <TabsContent value="lot-unfreeze" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Unfreeze Lot')}</h2>
                                    </div>
                                    <form onSubmit={submitLotUnfreeze} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Unfreeze Lot')} value={lotUnfreezeForm.data.lot_id} onChange={(value) => lotUnfreezeForm.setData('lot_id', value)} options={lots.filter((lot) => lot.is_active !== false && lot.is_frozen === true).map((lot) => String(lot.id))} includeEmpty emptyLabel={t('Select lot to unfreeze')} required />
                                        <div className="flex items-end"><Button type="submit" variant="outline" disabled={lotUnfreezeForm.processing} className="w-full">{t('Unfreeze Lot')}</Button></div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={lots} columns={[{ key: 'lot_reference', header: t('Lot Reference') }, { key: 'is_frozen', header: t('Frozen'), render: booleanLabel }, { key: 'freeze_note', header: t('Freeze Note'), render: optional }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create the first lot to begin tracking stock by lot.')} />} />
                        </TabsContent>

                        <TabsContent value="physical-verification" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <MoveRight className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Physical Verification')}</h2>
                                    </div>
                                    <form onSubmit={submitPhysicalVerification} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Verify Lot')} value={physicalVerificationForm.data.lot_reference} onChange={(value) => physicalVerificationForm.setData('lot_reference', value)} options={lots.filter((lot) => lot.is_active !== false).map((lot) => lot.lot_reference)} includeEmpty emptyLabel={t('Select active lot')} required />
                                        <TextileField label={t('Counted Quantity')} type="number" value={physicalVerificationForm.data.counted_quantity} onChange={(value) => physicalVerificationForm.setData('counted_quantity', value)} required />
                                        <TextileSelectField label={t('Location')} value={physicalVerificationForm.data.location} onChange={(value) => physicalVerificationForm.setData('location', value)} options={locations.map((location) => location.name)} includeEmpty emptyLabel={t('Optional location')} />
                                        <div className="grid grid-cols-2 gap-2">
                                            <TextileField label={t('Unit')} value={physicalVerificationForm.data.unit} onChange={(value) => physicalVerificationForm.setData('unit', value)} />
                                            <div className="flex items-end"><Button type="submit" variant="outline" disabled={physicalVerificationForm.processing} className="w-full">{t('Post Verification')}</Button></div>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={cycleCounts} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'expected_quantity', header: t('Expected') }, { key: 'counted_quantity', header: t('Counted') }, { key: 'variance_quantity', header: t('Variance') }, { key: 'adjustment_direction', header: t('Direction'), render: optional }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No cycle counts found')} description={t('Post a cycle count to track counted variance by lot.')} />} />
                        </TabsContent>

                        <TabsContent value="cycle-count" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <MoveRight className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Cycle Count')}</h2>
                                    </div>

                                    <form onSubmit={submitCycleCount} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Cycle Count Lot')} value={cycleCountForm.data.lot_reference} onChange={(value) => cycleCountForm.setData('lot_reference', value)} options={lots.filter((lot) => lot.is_active !== false).map((lot) => lot.lot_reference)} includeEmpty emptyLabel={t('Select active lot')} required />
                                        <TextileField label={t('Counted Quantity')} type="number" value={cycleCountForm.data.counted_quantity} onChange={(value) => cycleCountForm.setData('counted_quantity', value)} required />
                                        <TextileSelectField label={t('Location')} value={cycleCountForm.data.location} onChange={(value) => cycleCountForm.setData('location', value)} options={locations.map((location) => location.name)} includeEmpty emptyLabel={t('Optional location')} />
                                        <TextileField label={t('Unit')} value={cycleCountForm.data.unit} onChange={(value) => cycleCountForm.setData('unit', value)} />
                                        <div className="xl:col-span-4">
                                            <TextileField label={t('Notes')} value={cycleCountForm.data.notes} onChange={(value) => cycleCountForm.setData('notes', value)} />
                                            <Button type="submit" variant="outline" disabled={cycleCountForm.processing} className="mt-2 w-full">{t('Post Cycle Count')}</Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={cycleCounts} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'expected_quantity', header: t('Expected') }, { key: 'counted_quantity', header: t('Counted') }, { key: 'variance_quantity', header: t('Variance') }, { key: 'adjustment_direction', header: t('Direction'), render: optional }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No cycle counts found')} description={t('Post a cycle count to track counted variance by lot.')} />} />
                        </TabsContent>

                        <TabsContent value="reservation-release" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Release Reservation')}</h2>
                                    </div>
                                    <form onSubmit={submitReservationRelease} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Reservation ID')} value={reservationReleaseForm.data.reservation_id} onChange={(value) => reservationReleaseForm.setData('reservation_id', value)} options={reservations.map((reservation) => String(reservation.id))} includeEmpty emptyLabel={t('Select active reservation')} required />
                                        <div className="flex items-end"><Button type="submit" variant="outline" disabled={reservationReleaseForm.processing} className="w-full">{t('Release Reservation')}</Button></div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={reservations} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'reserved_quantity', header: t('Reserved Qty') }, { key: 'reference_type', header: t('Reference Type'), render: optional }, { key: 'reference_id', header: t('Reference ID'), render: optionalNumber }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile reservations found')} description={t('Reserve stock to control lot availability by demand.')} />} />
                        </TabsContent>

                        <TabsContent value="reservation-allocate" className="space-y-6">
                            <Card>
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <Boxes className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Allocate Reservation')}</h2>
                                    </div>
                                    <form onSubmit={submitReservationAllocate} className="grid gap-4 xl:grid-cols-4">
                                        <TextileSelectField label={t('Allocate Reservation ID')} value={reservationAllocateForm.data.reservation_id} onChange={(value) => reservationAllocateForm.setData('reservation_id', value)} options={reservations.map((reservation) => String(reservation.id))} includeEmpty emptyLabel={t('Select active reservation')} required />
                                        <TextileField label={t('Allocation Reference ID')} type="number" value={reservationAllocateForm.data.allocation_reference_id} onChange={(value) => reservationAllocateForm.setData('allocation_reference_id', value)} required />
                                        <TextileField label={t('Allocation Reference Type')} value={reservationAllocateForm.data.allocation_reference_type} onChange={(value) => reservationAllocateForm.setData('allocation_reference_type', value)} />
                                        <div className="flex items-end"><Button type="submit" variant="outline" disabled={reservationAllocateForm.processing} className="w-full">{t('Link Allocation')}</Button></div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={reservations} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'reserved_quantity', header: t('Reserved Qty') }, { key: 'reference_type', header: t('Reference Type'), render: optional }, { key: 'reference_id', header: t('Reference ID'), render: optionalNumber }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile reservations found')} description={t('Reserve stock to control lot availability by demand.')} />} />
                        </TabsContent>
                    </Tabs>
                </TabsContent>

                <TabsContent value="records" className="space-y-6">
                    <Tabs
                        value={activeSection === 'records' ? activeSubsection : defaultSubsectionBySection.records}
                        onValueChange={(value) => router.get(route('textile.inventory.index'), { section: 'records', sub: value }, { preserveState: true, replace: true })}
                        className="space-y-4"
                    >
                        <TabsList className="grid w-full grid-cols-1 gap-2 h-auto p-1 sm:grid-cols-3 lg:grid-cols-5">
                            <TabsTrigger value="record-locations">{t('Locations')}</TabsTrigger>
                            <TabsTrigger value="record-lots">{t('Lots')}</TabsTrigger>
                            <TabsTrigger value="record-movements">{t('Movements')}</TabsTrigger>
                            <TabsTrigger value="record-cycle-counts">{t('Cycle Counts')}</TabsTrigger>
                            <TabsTrigger value="record-reservations">{t('Reservations')}</TabsTrigger>
                        </TabsList>

                        <TabsContent value="record-locations">
                            <TextileDataTableCard data={locations} columns={[{ key: 'name', header: t('Location') }, { key: 'code', header: t('Code'), render: optional }, { key: 'rack', header: t('Rack'), render: optional }, { key: 'bin', header: t('Bin'), render: optional }, { key: 'location_type', header: t('Type') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile locations found')} description={t('Create dedicated locations to control stock movement flow.')} />} />
                        </TabsContent>
                        <TabsContent value="record-lots">
                            <TextileDataTableCard data={lots} columns={[{ key: 'lot_reference', header: t('Lot Reference') }, { key: 'batch_number', header: t('Batch'), render: optional }, { key: 'barcode', header: t('Barcode'), render: optional }, { key: 'qr_code', header: t('QR Code'), render: optional }, { key: 'received_quantity', header: t('Received') }, { key: 'available_quantity', header: t('Available') }, { key: 'reserved_quantity', header: t('Reserved') }, { key: 'status', header: t('Status') }, { key: 'is_frozen', header: t('Frozen'), render: booleanLabel }, { key: 'freeze_note', header: t('Freeze Note'), render: optional }, { key: 'id', header: t('Details'), render: (value: number) => (<Button type="button" variant="ghost" size="sm" onClick={() => router.visit(route('textile.inventory.lots.show', value))}>{t('View')}</Button>) }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create the first lot to begin tracking stock by lot.')} />} />
                        </TabsContent>
                        <TabsContent value="record-movements">
                            <Card className="mb-6">
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-center gap-2">
                                        <MoveRight className="h-5 w-5 text-violet-600" />
                                        <h2 className="font-semibold">{t('Movement Filters')}</h2>
                                    </div>
                                    <form onSubmit={submitMovementFilter} className="grid gap-4 xl:grid-cols-5">
                                        <TextileSelectField label={t('Type')} value={movementFilterForm.data.movement_type} onChange={(value) => movementFilterForm.setData('movement_type', value)} options={movementTypes} includeEmpty emptyLabel={t('All types')} />
                                        <TextileSelectField label={t('Status')} value={movementFilterForm.data.status} onChange={(value) => movementFilterForm.setData('status', value)} options={movementStatuses} includeEmpty emptyLabel={t('All statuses')} />
                                        <TextileField label={t('Lot Reference')} value={movementFilterForm.data.lot_reference} onChange={(value) => movementFilterForm.setData('lot_reference', value)} />
                                        <TextileSelectField label={t('Location')} value={movementFilterForm.data.location} onChange={(value) => movementFilterForm.setData('location', value)} options={locations.map((location) => location.name)} includeEmpty emptyLabel={t('All locations')} />
                                        <div className="flex items-end gap-2"><Button type="submit" className="w-full">{t('Apply')}</Button><Button type="button" variant="outline" className="w-full" onClick={clearMovementFilter}>{t('Clear')}</Button></div>
                                    </form>
                                </CardContent>
                            </Card>

                            <TextileDataTableCard data={movements} columns={[{ key: 'movement_type', header: t('Type') }, { key: 'adjustment_direction', header: t('Direction'), render: optional }, { key: 'lot_reference', header: t('Lot'), render: optional }, { key: 'location_from', header: t('From'), render: optional }, { key: 'location_to', header: t('To'), render: optional }, { key: 'quantity', header: t('Qty') }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={MoveRight} title={t('No textile movements found')} description={t('Record the first inventory movement to build stock history.')} />} />
                        </TabsContent>
                        <TabsContent value="record-cycle-counts">
                            <TextileDataTableCard data={cycleCounts} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'expected_quantity', header: t('Expected') }, { key: 'counted_quantity', header: t('Counted') }, { key: 'variance_quantity', header: t('Variance') }, { key: 'adjustment_direction', header: t('Direction'), render: optional }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No cycle counts found')} description={t('Post a cycle count to track counted variance by lot.')} />} />
                        </TabsContent>
                        <TabsContent value="record-reservations">
                            <TextileDataTableCard data={reservations} columns={[{ key: 'lot_reference', header: t('Lot') }, { key: 'reserved_quantity', header: t('Reserved Qty') }, { key: 'reference_type', header: t('Reference Type'), render: optional }, { key: 'reference_id', header: t('Reference ID'), render: optionalNumber }, { key: 'status', header: t('Status') }]} emptyState={<NoRecordsFound icon={Boxes} title={t('No textile reservations found')} description={t('Reserve stock to control lot availability by demand.')} />} />
                        </TabsContent>
                    </Tabs>
                </TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}

function optionalNumber(value: number | null) {
    return value ? String(value) : '-';
}

function booleanLabel(value: boolean | null | undefined) {
    return value ? 'yes' : 'no';
}
