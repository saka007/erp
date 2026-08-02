import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Boxes, MoveRight, Plus } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NoRecordsFound from '@/components/no-records-found';

interface TextileLot {
    id: number;
    lot_reference: string;
    received_quantity: string;
    available_quantity: string;
    reserved_quantity: string;
    status: string;
    is_active?: boolean;
}

interface TextileMovement {
    id: number;
    movement_type: string;
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
    location_type: string;
    status?: string | null;
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
    locations,
    movementTypes,
    movementStatuses,
    filters,
}: {
    lots: TextileLot[];
    movements: TextileMovement[];
    reservations: TextileReservation[];
    locations: TextileLocation[];
    movementTypes: string[];
    movementStatuses: string[];
    filters: InventoryFilters;
}) {
    const { t } = useTranslation();

    const lotForm = useForm({
        lot_reference: '',
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

    const movementForm = useForm({
        movement_type: 'receipt',
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

    const submitLot = (event: React.FormEvent) => {
        event.preventDefault();
        lotForm.post(route('textile.inventory.lots.store'), {
            onSuccess: () => lotForm.reset('lot_reference', 'received_quantity', 'available_quantity'),
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
            onSuccess: () => locationForm.reset('name', 'code'),
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

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Inventory') }]} pageTitle={t('Textile Inventory')}>
            <Head title={t('Textile Inventory')} />
            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-5">
                        <div className="mb-5 flex items-center gap-2">
                            <Boxes className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('New Lot')}</h2>
                        </div>
                        <form onSubmit={submitLot} className="space-y-4">
                            <Field label={t('Lot Reference')} value={lotForm.data.lot_reference} onChange={(value) => lotForm.setData('lot_reference', value)} required />
                            <Field label={t('Received Quantity')} type="number" value={lotForm.data.received_quantity} onChange={(value) => lotForm.setData('received_quantity', value)} required />
                            <Field label={t('Opening Available Quantity')} type="number" value={lotForm.data.available_quantity} onChange={(value) => lotForm.setData('available_quantity', value)} />
                            <Field label={t('Status')} value={lotForm.data.status} onChange={(value) => lotForm.setData('status', value)} />
                            <Button type="submit" disabled={lotForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />
                                {t('Create Lot')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5">
                        <div className="mb-5 flex items-center gap-2">
                            <MoveRight className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Record Movement')}</h2>
                        </div>
                        <form onSubmit={submitMovement} className="space-y-4">
                            <SelectField
                                label={t('Movement Type')}
                                value={movementForm.data.movement_type}
                                onChange={(value) => movementForm.setData('movement_type', value)}
                                options={movementTypes}
                                required
                            />
                            <Field label={t('Lot Reference')} value={movementForm.data.lot_reference} onChange={(value) => movementForm.setData('lot_reference', value)} />
                            <div className="grid grid-cols-2 gap-3">
                                <SelectField
                                    label={t('From Location')}
                                    value={movementForm.data.location_from}
                                    onChange={(value) => movementForm.setData('location_from', value)}
                                    options={locations.map((location) => location.name)}
                                    includeEmpty
                                    emptyLabel={t('Select location')}
                                />
                                <SelectField
                                    label={t('To Location')}
                                    value={movementForm.data.location_to}
                                    onChange={(value) => movementForm.setData('location_to', value)}
                                    options={locations.map((location) => location.name)}
                                    includeEmpty
                                    emptyLabel={t('Select location')}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={movementForm.data.quantity} onChange={(value) => movementForm.setData('quantity', value)} required />
                                <Field label={t('Unit')} value={movementForm.data.unit} onChange={(value) => movementForm.setData('unit', value)} />
                            </div>
                            <SelectField
                                label={t('Status')}
                                value={movementForm.data.status}
                                onChange={(value) => movementForm.setData('status', value)}
                                options={movementStatuses}
                                required
                            />
                            <Field label={t('Notes')} value={movementForm.data.notes} onChange={(value) => movementForm.setData('notes', value)} />
                            <Button type="submit" disabled={movementForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />
                                {t('Save Movement')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6">
                <Card>
                    <CardContent className="p-5">
                        <div className="mb-5 flex items-center gap-2">
                            <Boxes className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Location Controls')}</h2>
                        </div>
                        <form onSubmit={submitLocation} className="grid gap-4 xl:grid-cols-4">
                            <Field label={t('Location Name')} value={locationForm.data.name} onChange={(value) => locationForm.setData('name', value)} required />
                            <Field label={t('Code')} value={locationForm.data.code} onChange={(value) => locationForm.setData('code', value)} />
                            <SelectField
                                label={t('Location Type')}
                                value={locationForm.data.location_type}
                                onChange={(value) => locationForm.setData('location_type', value)}
                                options={['warehouse', 'loom-floor', 'process-house', 'dispatch', 'quarantine']}
                                required
                            />
                            <div className="flex items-end">
                                <Button type="submit" disabled={locationForm.processing} className="w-full">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('Create Location')}
                                </Button>
                            </div>
                        </form>

                        <form onSubmit={submitLocationArchive} className="mt-4 grid gap-4 xl:grid-cols-4">
                            <SelectField
                                label={t('Archive Location')}
                                value={locationArchiveForm.data.location_id}
                                onChange={(value) => locationArchiveForm.setData('location_id', value)}
                                options={locations.map((location) => String(location.id))}
                                includeEmpty
                                emptyLabel={t('Select active location ID')}
                                required
                            />
                            <div className="flex items-end">
                                <Button type="submit" variant="outline" disabled={locationArchiveForm.processing} className="w-full">
                                    {t('Archive Location')}
                                </Button>
                            </div>
                        </form>

                        <form onSubmit={submitLotUpdate} className="mt-4 grid gap-4 xl:grid-cols-4">
                            <SelectField
                                label={t('Update Lot Status')}
                                value={lotUpdateForm.data.lot_id}
                                onChange={(value) => lotUpdateForm.setData('lot_id', value)}
                                options={lots.filter((lot) => lot.is_active !== false).map((lot) => String(lot.id))}
                                includeEmpty
                                emptyLabel={t('Select active lot ID')}
                                required
                            />
                            <SelectField
                                label={t('New Status')}
                                value={lotUpdateForm.data.status}
                                onChange={(value) => lotUpdateForm.setData('status', value)}
                                options={['active', 'hold', 'inactive']}
                                required
                            />
                            <div className="flex items-end">
                                <Button type="submit" variant="outline" disabled={lotUpdateForm.processing} className="w-full">
                                    {t('Update Lot')}
                                </Button>
                            </div>
                        </form>

                        <form onSubmit={submitLotArchive} className="mt-4 grid gap-4 xl:grid-cols-4">
                            <SelectField
                                label={t('Archive Lot')}
                                value={lotArchiveForm.data.lot_id}
                                onChange={(value) => lotArchiveForm.setData('lot_id', value)}
                                options={lots.filter((lot) => lot.is_active !== false).map((lot) => String(lot.id))}
                                includeEmpty
                                emptyLabel={t('Select active lot ID')}
                                required
                            />
                            <div className="flex items-end">
                                <Button type="submit" variant="outline" disabled={lotArchiveForm.processing} className="w-full">
                                    {t('Archive Lot')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6">
                <Card>
                    <CardContent className="p-5">
                        <div className="mb-5 flex items-center gap-2">
                            <Boxes className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Reserve Lot Quantity')}</h2>
                        </div>
                        <form onSubmit={submitReservation} className="grid gap-4 xl:grid-cols-4">
                            <Field label={t('Lot Reference')} value={reservationForm.data.lot_reference} onChange={(value) => reservationForm.setData('lot_reference', value)} required />
                            <Field label={t('Quantity')} type="number" value={reservationForm.data.quantity} onChange={(value) => reservationForm.setData('quantity', value)} required />
                            <Field label={t('Reference Type')} value={reservationForm.data.reference_type} onChange={(value) => reservationForm.setData('reference_type', value)} />
                            <Field label={t('Reference ID')} type="number" value={reservationForm.data.reference_id} onChange={(value) => reservationForm.setData('reference_id', value)} />
                            <div className="xl:col-span-4">
                                <Button type="submit" disabled={reservationForm.processing} className="w-full xl:w-auto">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('Reserve Quantity')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6">
                <Card>
                    <CardContent className="p-5">
                        <div className="mb-5 flex items-center gap-2">
                            <MoveRight className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Movement Filters')}</h2>
                        </div>
                        <form onSubmit={submitMovementFilter} className="grid gap-4 xl:grid-cols-5">
                            <SelectField
                                label={t('Type')}
                                value={movementFilterForm.data.movement_type}
                                onChange={(value) => movementFilterForm.setData('movement_type', value)}
                                options={movementTypes}
                                includeEmpty
                                emptyLabel={t('All types')}
                            />
                            <SelectField
                                label={t('Status')}
                                value={movementFilterForm.data.status}
                                onChange={(value) => movementFilterForm.setData('status', value)}
                                options={movementStatuses}
                                includeEmpty
                                emptyLabel={t('All statuses')}
                            />
                            <Field label={t('Lot Reference')} value={movementFilterForm.data.lot_reference} onChange={(value) => movementFilterForm.setData('lot_reference', value)} />
                            <SelectField
                                label={t('Location')}
                                value={movementFilterForm.data.location}
                                onChange={(value) => movementFilterForm.setData('location', value)}
                                options={locations.map((location) => location.name)}
                                includeEmpty
                                emptyLabel={t('All locations')}
                            />
                            <div className="flex items-end gap-2">
                                <Button type="submit" className="w-full">{t('Apply')}</Button>
                                <Button type="button" variant="outline" className="w-full" onClick={clearMovementFilter}>{t('Clear')}</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={locations}
                            columns={[
                                { key: 'name', header: t('Location') },
                                { key: 'code', header: t('Code'), render: optional },
                                { key: 'location_type', header: t('Type') },
                            ]}
                            emptyState={<NoRecordsFound icon={Boxes} title={t('No textile locations found')} description={t('Create dedicated locations to control stock movement flow.')} />}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={lots}
                            columns={[
                                { key: 'lot_reference', header: t('Lot Reference') },
                                { key: 'received_quantity', header: t('Received') },
                                { key: 'available_quantity', header: t('Available') },
                                { key: 'reserved_quantity', header: t('Reserved') },
                                { key: 'status', header: t('Status') },
                                {
                                    key: 'id',
                                    header: t('Details'),
                                    render: (value: number) => (
                                        <Button type="button" variant="ghost" size="sm" onClick={() => router.visit(route('textile.inventory.lots.show', value))}>
                                            {t('View')}
                                        </Button>
                                    ),
                                },
                            ]}
                            emptyState={<NoRecordsFound icon={Boxes} title={t('No textile lots found')} description={t('Create the first lot to begin tracking stock by lot.')} />}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={movements}
                            columns={[
                                { key: 'movement_type', header: t('Type') },
                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                { key: 'location_from', header: t('From'), render: optional },
                                { key: 'location_to', header: t('To'), render: optional },
                                { key: 'quantity', header: t('Qty') },
                                { key: 'status', header: t('Status') },
                            ]}
                            emptyState={<NoRecordsFound icon={MoveRight} title={t('No textile movements found')} description={t('Record the first inventory movement to build stock history.')} />}
                        />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6">
                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={reservations}
                            columns={[
                                { key: 'lot_reference', header: t('Lot') },
                                { key: 'reserved_quantity', header: t('Reserved Qty') },
                                { key: 'reference_type', header: t('Reference Type'), render: optional },
                                { key: 'reference_id', header: t('Reference ID'), render: optionalNumber },
                                { key: 'status', header: t('Status') },
                            ]}
                            emptyState={<NoRecordsFound icon={Boxes} title={t('No textile reservations found')} description={t('Reserve stock to control lot availability by demand.')} />}
                        />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6">
                <Card>
                    <CardContent className="p-5">
                        <div className="mb-5 flex items-center gap-2">
                            <Boxes className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Release Reservation')}</h2>
                        </div>
                        <form onSubmit={submitReservationRelease} className="grid gap-4 xl:grid-cols-4">
                            <SelectField
                                label={t('Reservation ID')}
                                value={reservationReleaseForm.data.reservation_id}
                                onChange={(value) => reservationReleaseForm.setData('reservation_id', value)}
                                options={reservations.map((reservation) => String(reservation.id))}
                                includeEmpty
                                emptyLabel={t('Select active reservation')}
                                required
                            />
                            <div className="flex items-end">
                                <Button type="submit" variant="outline" disabled={reservationReleaseForm.processing} className="w-full">
                                    {t('Release Reservation')}
                                </Button>
                            </div>
                        </form>

                        <form onSubmit={submitReservationAllocate} className="mt-4 grid gap-4 xl:grid-cols-4">
                            <SelectField
                                label={t('Allocate Reservation ID')}
                                value={reservationAllocateForm.data.reservation_id}
                                onChange={(value) => reservationAllocateForm.setData('reservation_id', value)}
                                options={reservations.map((reservation) => String(reservation.id))}
                                includeEmpty
                                emptyLabel={t('Select active reservation')}
                                required
                            />
                            <Field
                                label={t('Allocation Reference ID')}
                                type="number"
                                value={reservationAllocateForm.data.allocation_reference_id}
                                onChange={(value) => reservationAllocateForm.setData('allocation_reference_id', value)}
                                required
                            />
                            <Field
                                label={t('Allocation Reference Type')}
                                value={reservationAllocateForm.data.allocation_reference_type}
                                onChange={(value) => reservationAllocateForm.setData('allocation_reference_type', value)}
                            />
                            <div className="flex items-end">
                                <Button type="submit" variant="outline" disabled={reservationAllocateForm.processing} className="w-full">
                                    {t('Link Allocation')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    onChange,
    type = 'text',
    required = false,
    placeholder = '',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    placeholder?: string;
}) {
    return (
        <div>
            <Label>{label}</Label>
            <Input
                type={type}
                value={value}
                required={required}
                placeholder={placeholder}
                onChange={(event: React.ChangeEvent<HTMLInputElement>) => onChange(event.target.value)}
            />
        </div>
    );
}

function optional(value: string | null) {
    return value || '-';
}

function optionalNumber(value: number | null) {
    return value ? String(value) : '-';
}

function SelectField({
    label,
    value,
    onChange,
    options,
    required = false,
    includeEmpty = false,
    emptyLabel = 'Select',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    required?: boolean;
    includeEmpty?: boolean;
    emptyLabel?: string;
}) {
    return (
        <div>
            <Label>{label}</Label>
            <select
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={value}
                required={required}
                onChange={(event: React.ChangeEvent<HTMLSelectElement>) => onChange(event.target.value)}
            >
                {includeEmpty && <option value="">{emptyLabel}</option>}
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
        </div>
    );
}
