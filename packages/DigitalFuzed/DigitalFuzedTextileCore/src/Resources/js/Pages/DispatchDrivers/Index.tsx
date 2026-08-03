import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, ArchiveX, UserRound } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface DispatchDriver {
    id: number;
    name: string;
    code?: string | null;
    driver_source?: string | null;
    phone?: string | null;
    license_number?: string | null;
    license_expiry_date?: string | null;
    transporter_name?: string | null;
    notes?: string | null;
    transport_vendor_id?: number | null;
}

interface EntityOption {
    id: number;
    label: string;
}

const driverSourceOptions = [
    { value: 'own', label: 'Own Driver' },
    { value: 'vendor', label: 'Vendor Driver' },
];

export default function Index({ drivers, transportVendorOptions }: { drivers: DispatchDriver[]; transportVendorOptions: EntityOption[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);
    const resolvedTransportVendorOptions = transportVendorOptions.map((value) => ({ value: String(value.id), label: value.label }));

    const { data, setData, post, processing, reset } = useForm({
        name: '',
        code: '',
        driver_source: 'own',
        phone: '',
        license_number: '',
        license_expiry_date: '',
        transport_vendor_id: '',
        notes: '',
    });

    const { data: editData, setData: setEditData, post: postEdit, processing: editing, reset: resetEdit } = useForm({
        driver_id: '',
        name: '',
        code: '',
        driver_source: 'own',
        phone: '',
        license_number: '',
        license_expiry_date: '',
        transport_vendor_id: '',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('textile.dispatch-drivers.store'), { onSuccess: () => reset() });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        postEdit(route('textile.dispatch-drivers.update'));
    };

    const startEdit = (driver: DispatchDriver) => {
        setEditingId(driver.id);
        setEditData({
            driver_id: String(driver.id),
            name: driver.name ?? '',
            code: driver.code ?? '',
            driver_source: driver.driver_source ?? 'own',
            phone: driver.phone ?? '',
            license_number: driver.license_number ?? '',
            license_expiry_date: driver.license_expiry_date ?? '',
            transport_vendor_id: driver.transport_vendor_id ? String(driver.transport_vendor_id) : '',
            notes: driver.notes ?? '',
        });
    };

    const archive = (driverId: number) => {
        router.post(route('textile.dispatch-drivers.archive'), { driver_id: driverId });
        if (editingId === driverId) {
            setEditingId(null);
            resetEdit();
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Dispatch Drivers') }]} pageTitle={t('Dispatch Drivers')}>
            <Head title={t('Dispatch Drivers')} />
            <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <TextileFormCard title={t('New Dispatch Driver')} icon={UserRound} contentClassName="p-5 space-y-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label={t('Name')} value={data.name} onChange={(value) => setData('name', value)} required />
                        <Field label={t('Code')} value={data.code} onChange={(value) => setData('code', value)} />
                        <SelectField label={t('Driver Source')} value={data.driver_source} onChange={(value: string) => setData('driver_source', value)} options={driverSourceOptions} includeEmpty={false} />
                        <Field label={t('Phone')} value={data.phone} onChange={(value) => setData('phone', value)} />
                        <Field label={t('License Number')} value={data.license_number} onChange={(value) => setData('license_number', value)} />
                        <Field label={t('License Expiry Date')} type="date" value={data.license_expiry_date} onChange={(value) => setData('license_expiry_date', value)} />
                        <SelectField label={t('Transport Vendor')} value={data.transport_vendor_id} onChange={(value: string) => setData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Required only for Vendor Driver source.')} disabled={data.driver_source !== 'vendor'} disabledReason={t('Set Driver Source to Vendor to select transport vendor.')} />
                        <Field label={t('Notes')} value={data.notes} onChange={(value) => setData('notes', value)} />
                        <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Driver')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Dispatch Driver')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Name')} value={editData.name} onChange={(value) => setEditData('name', value)} required />
                                <Field label={t('Code')} value={editData.code} onChange={(value) => setEditData('code', value)} />
                                <SelectField label={t('Driver Source')} value={editData.driver_source} onChange={(value: string) => setEditData('driver_source', value)} options={driverSourceOptions} includeEmpty={false} />
                                <Field label={t('Phone')} value={editData.phone} onChange={(value) => setEditData('phone', value)} />
                                <Field label={t('License Number')} value={editData.license_number} onChange={(value) => setEditData('license_number', value)} />
                                <Field label={t('License Expiry Date')} type="date" value={editData.license_expiry_date} onChange={(value) => setEditData('license_expiry_date', value)} />
                                <SelectField label={t('Transport Vendor')} value={editData.transport_vendor_id} onChange={(value: string) => setEditData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Required only for Vendor Driver source.')} disabled={editData.driver_source !== 'vendor'} disabledReason={t('Set Driver Source to Vendor to select transport vendor.')} />
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
                    data={drivers}
                    columns={[
                        { key: 'name', header: t('Name') },
                        { key: 'code', header: t('Code'), render: optional },
                        { key: 'driver_source', header: t('Source'), render: optional },
                        { key: 'phone', header: t('Phone'), render: optional },
                        { key: 'license_number', header: t('License Number'), render: optional },
                        { key: 'license_expiry_date', header: t('Expiry'), render: optional },
                        { key: 'transporter_name', header: t('Transporter'), render: optional },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: DispatchDriver) => (
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
                    emptyState={<NoRecordsFound icon={UserRound} title={t('No dispatch drivers found')} description={t('Create driver records for reusable dispatch assignments.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}
