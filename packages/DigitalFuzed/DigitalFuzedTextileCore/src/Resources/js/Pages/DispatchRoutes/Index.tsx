import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, ArchiveX, MapPinned } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface DispatchRoute {
    id: number;
    route_name: string;
    route_code?: string | null;
    origin_location?: string | null;
    destination_location?: string | null;
    distance_km?: string | null;
    transit_hours?: string | null;
    transport_vendor_id?: number | null;
    transporter_name?: string | null;
    notes?: string | null;
}

interface EntityOption {
    id: number;
    label: string;
}

export default function Index({ routes, transportVendorOptions }: { routes: DispatchRoute[]; transportVendorOptions: EntityOption[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);
    const resolvedTransportVendorOptions = transportVendorOptions.map((value) => ({ value: String(value.id), label: value.label }));

    const { data, setData, post, processing, reset } = useForm({
        route_name: '',
        route_code: '',
        origin_location: '',
        destination_location: '',
        distance_km: '',
        transit_hours: '',
        transport_vendor_id: '',
        notes: '',
    });

    const { data: editData, setData: setEditData, post: postEdit, processing: editing, reset: resetEdit } = useForm({
        route_id: '',
        route_name: '',
        route_code: '',
        origin_location: '',
        destination_location: '',
        distance_km: '',
        transit_hours: '',
        transport_vendor_id: '',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('textile.dispatch-routes.store'), { onSuccess: () => reset() });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        postEdit(route('textile.dispatch-routes.update'));
    };

    const startEdit = (routeRow: DispatchRoute) => {
        setEditingId(routeRow.id);
        setEditData({
            route_id: String(routeRow.id),
            route_name: routeRow.route_name ?? '',
            route_code: routeRow.route_code ?? '',
            origin_location: routeRow.origin_location ?? '',
            destination_location: routeRow.destination_location ?? '',
            distance_km: routeRow.distance_km ?? '',
            transit_hours: routeRow.transit_hours ?? '',
            transport_vendor_id: routeRow.transport_vendor_id ? String(routeRow.transport_vendor_id) : '',
            notes: routeRow.notes ?? '',
        });
    };

    const archive = (routeId: number) => {
        router.post(route('textile.dispatch-routes.archive'), { route_id: routeId });
        if (editingId === routeId) {
            setEditingId(null);
            resetEdit();
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Dispatch Routes') }]} pageTitle={t('Dispatch Routes')}>
            <Head title={t('Dispatch Routes')} />
            <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                <TextileFormCard title={t('New Dispatch Route')} icon={MapPinned} contentClassName="p-5 space-y-6">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label={t('Route Name')} value={data.route_name} onChange={(value) => setData('route_name', value)} required />
                        <Field label={t('Route Code')} value={data.route_code} onChange={(value) => setData('route_code', value)} />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('From')} value={data.origin_location} onChange={(value) => setData('origin_location', value)} />
                            <Field label={t('To')} value={data.destination_location} onChange={(value) => setData('destination_location', value)} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Distance (km)')} type="number" value={data.distance_km} onChange={(value) => setData('distance_km', value)} />
                            <Field label={t('Transit Hours')} type="number" value={data.transit_hours} onChange={(value) => setData('transit_hours', value)} />
                        </div>
                        <SelectField label={t('Transport Vendor')} value={data.transport_vendor_id} onChange={(value: string) => setData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Use Account > Vendors with supplier type Transport Vendor.')} />
                        <Field label={t('Notes')} value={data.notes} onChange={(value) => setData('notes', value)} />
                        <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Route')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Dispatch Route')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Route Name')} value={editData.route_name} onChange={(value) => setEditData('route_name', value)} required />
                                <Field label={t('Route Code')} value={editData.route_code} onChange={(value) => setEditData('route_code', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('From')} value={editData.origin_location} onChange={(value) => setEditData('origin_location', value)} />
                                    <Field label={t('To')} value={editData.destination_location} onChange={(value) => setEditData('destination_location', value)} />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Distance (km)')} type="number" value={editData.distance_km} onChange={(value) => setEditData('distance_km', value)} />
                                    <Field label={t('Transit Hours')} type="number" value={editData.transit_hours} onChange={(value) => setEditData('transit_hours', value)} />
                                </div>
                                <SelectField label={t('Transport Vendor')} value={editData.transport_vendor_id} onChange={(value: string) => setEditData('transport_vendor_id', value)} options={resolvedTransportVendorOptions} includeEmpty emptyLabel={t('Select transport vendor')} helperText={t('Use Account > Vendors with supplier type Transport Vendor.')} />
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
                    data={routes}
                    columns={[
                        { key: 'route_name', header: t('Route Name') },
                        { key: 'route_code', header: t('Code'), render: optional },
                        { key: 'origin_location', header: t('From'), render: optional },
                        { key: 'destination_location', header: t('To'), render: optional },
                        { key: 'transporter_name', header: t('Transport Vendor'), render: optional },
                        { key: 'distance_km', header: t('Distance (km)'), render: optional },
                        { key: 'transit_hours', header: t('Transit Hours'), render: optional },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: DispatchRoute) => (
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
                    emptyState={<NoRecordsFound icon={MapPinned} title={t('No dispatch routes found')} description={t('Create route records for reusable dispatch planning and tracking.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}
