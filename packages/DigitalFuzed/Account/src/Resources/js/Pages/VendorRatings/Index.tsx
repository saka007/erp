import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Star, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import NoRecordsFound from '@/components/no-records-found';

interface VendorOption {
    id: number;
    company_name: string;
    vendor_code: string;
}

interface RatingRow {
    id: number;
    vendor_id: number;
    rating_date: string;
    quality_score: number;
    delivery_score: number;
    service_score: number;
    price_score: number;
    overall_score: string;
    remarks?: string | null;
    is_active: boolean;
    vendor?: { company_name: string; vendor_code: string };
}

const scoreOptions = ['1', '2', '3', '4', '5'].map((value) => ({ value, label: value }));
const yesNoOptions = [
    { value: '1', label: 'Yes' },
    { value: '0', label: 'No' },
];

export default function Index({ ratings, vendors }: { ratings: RatingRow[]; vendors: VendorOption[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const vendorOptions = vendors.map((vendor) => ({
        value: String(vendor.id),
        label: `${vendor.company_name} (${vendor.vendor_code})`,
    }));

    const createForm = useForm({
        vendor_id: '',
        rating_date: new Date().toISOString().slice(0, 10),
        quality_score: '3',
        delivery_score: '3',
        service_score: '3',
        price_score: '3',
        remarks: '',
        is_active: '1',
    });

    const editForm = useForm({
        rating_date: '',
        quality_score: '3',
        delivery_score: '3',
        service_score: '3',
        price_score: '3',
        remarks: '',
        is_active: '1',
    });

    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('account.vendor-ratings.store'), {
            onSuccess: () => createForm.reset('remarks'),
        });
    };

    const startEdit = (row: RatingRow) => {
        setEditingId(row.id);
        editForm.setData({
            rating_date: row.rating_date,
            quality_score: String(row.quality_score),
            delivery_score: String(row.delivery_score),
            service_score: String(row.service_score),
            price_score: String(row.price_score),
            remarks: row.remarks || '',
            is_active: row.is_active ? '1' : '0',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('account.vendor-ratings.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('account.vendor-ratings.destroy', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('CRM & Suppliers') }, { label: t('Vendor Ratings') }]}
            pageTitle={t('Vendor Ratings')}
        >
            <Head title={t('Vendor Ratings')} />
            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Vendor Rating')} icon={Star} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Vendor')} value={createForm.data.vendor_id} onChange={(value) => createForm.setData('vendor_id', value)} options={vendorOptions} includeEmpty emptyLabel={t('Select vendor')} required />
                        <Field label={t('Rating Date')} type="date" value={createForm.data.rating_date} onChange={(value) => createForm.setData('rating_date', value)} required />
                        <div className="grid grid-cols-2 gap-3">
                            <SelectField label={t('Quality')} value={createForm.data.quality_score} onChange={(value) => createForm.setData('quality_score', value)} options={scoreOptions} required />
                            <SelectField label={t('Delivery')} value={createForm.data.delivery_score} onChange={(value) => createForm.setData('delivery_score', value)} options={scoreOptions} required />
                            <SelectField label={t('Service')} value={createForm.data.service_score} onChange={(value) => createForm.setData('service_score', value)} options={scoreOptions} required />
                            <SelectField label={t('Price')} value={createForm.data.price_score} onChange={(value) => createForm.setData('price_score', value)} options={scoreOptions} required />
                        </div>
                        <Field label={t('Remarks')} value={createForm.data.remarks} onChange={(value) => createForm.setData('remarks', value)} />
                        <SelectField label={t('Active')} value={createForm.data.is_active} onChange={(value) => createForm.setData('is_active', value)} options={yesNoOptions} required />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Rating')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Rating')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Rating Date')} type="date" value={editForm.data.rating_date} onChange={(value) => editForm.setData('rating_date', value)} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <SelectField label={t('Quality')} value={editForm.data.quality_score} onChange={(value) => editForm.setData('quality_score', value)} options={scoreOptions} required />
                                    <SelectField label={t('Delivery')} value={editForm.data.delivery_score} onChange={(value) => editForm.setData('delivery_score', value)} options={scoreOptions} required />
                                    <SelectField label={t('Service')} value={editForm.data.service_score} onChange={(value) => editForm.setData('service_score', value)} options={scoreOptions} required />
                                    <SelectField label={t('Price')} value={editForm.data.price_score} onChange={(value) => editForm.setData('price_score', value)} options={scoreOptions} required />
                                </div>
                                <Field label={t('Remarks')} value={editForm.data.remarks} onChange={(value) => editForm.setData('remarks', value)} />
                                <SelectField label={t('Active')} value={editForm.data.is_active} onChange={(value) => editForm.setData('is_active', value)} options={yesNoOptions} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <Button type="submit" disabled={editForm.processing}>{t('Save')}</Button>
                                    <Button type="button" variant="outline" onClick={() => { setEditingId(null); editForm.reset(); }}>{t('Cancel')}</Button>
                                </div>
                            </form>
                        </>
                    ) : null}
                </TextileFormCard>

                <TextileDataTableCard
                    data={ratings}
                    columns={[
                        { key: 'vendor', header: t('Vendor'), render: (_value: unknown, row: RatingRow) => row.vendor ? `${row.vendor.company_name} (${row.vendor.vendor_code})` : '-' },
                        { key: 'rating_date', header: t('Date') },
                        { key: 'quality_score', header: t('Quality') },
                        { key: 'delivery_score', header: t('Delivery') },
                        { key: 'service_score', header: t('Service') },
                        { key: 'price_score', header: t('Price') },
                        { key: 'overall_score', header: t('Overall') },
                        { key: 'is_active', header: t('Status'), render: (value: unknown) => (value ? 'active' : 'inactive') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: RatingRow) => (
                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={() => startEdit(row)}>
                                        <Pencil className="mr-1 h-3.5 w-3.5" />
                                        {t('Edit')}
                                    </Button>
                                    <Button type="button" variant="destructive" size="sm" onClick={() => destroy(row.id)}>
                                        <Trash2 className="mr-1 h-3.5 w-3.5" />
                                        {t('Delete')}
                                    </Button>
                                </div>
                            ),
                        },
                    ]}
                    emptyState={<NoRecordsFound icon={Star} title={t('No vendor ratings found')} description={t('Add vendor ratings for supplier quality and delivery tracking.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
