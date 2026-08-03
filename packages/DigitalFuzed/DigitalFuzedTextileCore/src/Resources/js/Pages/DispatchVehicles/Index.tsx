import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, ArchiveX, Truck } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface DispatchVehicle {
    id: number;
    vehicle_number: string;
    code?: string | null;
    vehicle_type?: string | null;
    capacity?: string | null;
    capacity_unit?: string | null;
    ownership_type?: string | null;
    transport_vendor_id?: number | null;
    container_number?: string | null;
    transporter_name?: string | null;
    notes?: string | null;
}

interface EntityOption {
    id: number;
    label: string;
}

const vehicleTypeOptions = [
    { value: 'truck', label: 'Truck' },
    { value: 'container', label: 'Container' },
    { value: 'tempo', label: 'Tempo' },
    { value: 'van', label: 'Van' },
];

const capacityUnitOptions = [
    { value: 'kg', label: 'kg' },
    { value: 'ton', label: 'ton' },
    { value: 'mtr', label: 'mtr' },
    { value: 'roll', label: 'roll' },
];

const ownershipOptions = [
    { value: 'owned', label: 'Owned' },
    { value: 'hired', label: 'Hired' },
    { value: 'vendor', label: 'Vendor' },
];

export default function Index({ vehicles, transportVendorOptions }: { vehicles: DispatchVehicle[]; transportVendorOptions: EntityOption[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);
    const resolvedTransportVendorOptions = transportVendorOptions.map((value) => ({ value: String(value.id), label: value.label }));

    const { data, setData, post, processing, reset } = useForm({
        vehicle_number: '',
        code: '',
        vehicle_type: '',
        capacity: '',
        capacity_unit: '',
        ownership_type: '',
        transport_vendor_id: '',
        container_number: '',
        notes: '',
    });

    const { data: editData, setData: setEditData, post: postEdit, processing: editing, reset: resetEdit } = useForm({
        vehicle_id: '',
        vehicle_number: '',
        code: '',
        vehicle_type: '',
        capacity: '',
        capacity_unit: '',
        ownership_type: '',
        transport_vendor_id: '',
        container_number: '',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('textile.dispatch-vehicles.store'), { onSuccess: () => reset() });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        postEdit(route('textile.dispatch-vehicles.update'));
    };

    const startEdit = (vehicle: DispatchVehicle) => {
        setEditingId(vehicle.id);
        setEditData({
            vehicle_id: String(vehicle.id),
            vehicle_number: vehicle.vehicle_number ?? '',
            code: vehicle.code ?? '',
            vehicle_type: vehicle.vehicle_type ?? '',
            capacity: vehicle.capacity ?? '',
            capacity_unit: vehicle.capacity_unit ?? '',
            ownership_type: vehicle.ownership_type ?? '',
            transport_vendor_id: vehicle.transport_vendor_id ? String(vehicle.transport_vendor_id) : '',
            container_number: vehicle.container_number ?? '',
            notes: vehicle.notes ?? '',
        });
    };

    const archive = (vehicleId: number) => {
        router.post(route('textile.dispatch-vehicles.archive'), { vehicle_id: vehicleId });
        if (editingId === vehicleId) {
            setEditingId(null);
            resetEdit();
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Dispatch Vehicles') }]} pageTitle={t('Dispatch Vehicles')}>
            <Head title={t('Dispatch Vehicles')} />
            <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                <TextileFormCard title={t('New Dispatch Vehicle')} icon={Truck} contentClassName="p-5 space-y-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label={t('Vehicle Number')} value={data.vehicle_number} onChange={(value) => setData('vehicle_number', value)} required />
                        <Field label={t('Code')} value={data.code} onChange={(value) => setData('code', value)} />
                        <SelectField label={t('Vehicle Type')} value={data.vehicle_type} onChange={(value: string) => setData('vehicle_type', value)} options={vehicleTypeOptions} includeEmpty />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Capacity')} type="number" value={data.capacity} onChange={(value) => setData('capacity', value)} />
                            <SelectField label={t('Capacity Unit')} value={data.capacity_unit} onChange={(value: string) => setData('capacity_unit', value)} options={capacityUnitOptions} includeEmpty />
                        </div>
                        <SelectField label={t('Ownership')} value={data.ownership_type} onChange={(value: string) => setData('ownership_type', value)} options={ownershipOptions} includeEmpty />
                        <SelectField label={t('Transport Vendor')} value={data.transport_vendor_id} onChange={(value: string) => setData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Required only for Vendor ownership.')} disabled={data.ownership_type !== 'vendor'} disabledReason={t('Set Ownership to Vendor to select transport vendor.')} />
                        <Field label={t('Container Number')} value={data.container_number} onChange={(value) => setData('container_number', value)} />
                        <Field label={t('Notes')} value={data.notes} onChange={(value) => setData('notes', value)} />
                        <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Vehicle')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Dispatch Vehicle')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Vehicle Number')} value={editData.vehicle_number} onChange={(value) => setEditData('vehicle_number', value)} required />
                                <Field label={t('Code')} value={editData.code} onChange={(value) => setEditData('code', value)} />
                                <SelectField label={t('Vehicle Type')} value={editData.vehicle_type} onChange={(value: string) => setEditData('vehicle_type', value)} options={vehicleTypeOptions} includeEmpty />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Capacity')} type="number" value={editData.capacity} onChange={(value) => setEditData('capacity', value)} />
                                    <SelectField label={t('Capacity Unit')} value={editData.capacity_unit} onChange={(value: string) => setEditData('capacity_unit', value)} options={capacityUnitOptions} includeEmpty />
                                </div>
                                <SelectField label={t('Ownership')} value={editData.ownership_type} onChange={(value: string) => setEditData('ownership_type', value)} options={ownershipOptions} includeEmpty />
                                <SelectField label={t('Transport Vendor')} value={editData.transport_vendor_id} onChange={(value: string) => setEditData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Required only for Vendor ownership.')} disabled={editData.ownership_type !== 'vendor'} disabledReason={t('Set Ownership to Vendor to select transport vendor.')} />
                                <Field label={t('Container Number')} value={editData.container_number} onChange={(value) => setEditData('container_number', value)} />
                                <Field label={t('Notes')} value={editData.notes} onChange={(value) => setEditData('notes', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <Button type="submit" disabled={editing}>{t('Save Changes')}</Button>
                                    <Button type="button" variant="outline" onClick={() => { setEditingId(null); resetEdit(); }}>
                                        {t('Cancel')}
                                    </Button>
                                </div>
                            </form>
                        </>
                    ) : null}
                </TextileFormCard>

                <TextileDataTableCard
                    data={vehicles}
                    columns={[
                        { key: 'vehicle_number', header: t('Vehicle Number') },
                        { key: 'vehicle_type', header: t('Type'), render: optional },
                        { key: 'capacity', header: t('Capacity'), render: optional },
                        { key: 'capacity_unit', header: t('Unit'), render: optional },
                        { key: 'ownership_type', header: t('Ownership'), render: optional },
                        { key: 'container_number', header: t('Container'), render: optional },
                        { key: 'transporter_name', header: t('Transporter'), render: optional },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: DispatchVehicle) => (
                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={() => startEdit(row)}>
                                        <Pencil className="mr-1 h-3.5 w-3.5" />
                                        {t('Edit')}
                                    </Button>
                                    <Button type="button" variant="destructive" size="sm" onClick={() => archive(row.id)}>
                                        <ArchiveX className="mr-1 h-3.5 w-3.5" />
                                        {t('Deactivate')}
                                    </Button>
                                </div>
                            ),
                        },
                    ]}
                    emptyState={<NoRecordsFound icon={Truck} title={t('No dispatch vehicles found')} description={t('Create vehicle records for reusable dispatch assignments.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}
