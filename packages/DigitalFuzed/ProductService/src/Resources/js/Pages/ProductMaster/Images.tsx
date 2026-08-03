import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Image as ImageIcon, Pencil, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import NoRecordsFound from '@/components/no-records-found';

interface ItemOption {
    id: number;
    name: string;
    sku: string;
    type: string;
}

interface ImageRow {
    id: number;
    product_id: number;
    image_path: string;
    sort_order: number;
    is_primary: boolean;
    is_active: boolean;
    product?: ItemOption;
}

const yesNoOptions = [
    { value: '1', label: 'Yes' },
    { value: '0', label: 'No' },
];

export default function Images({ images, items, stats }: { images: ImageRow[]; items: ItemOption[]; stats: Record<string, number> }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const itemOptions = items.map((item) => ({
        value: String(item.id),
        label: `${item.name} (${item.sku})`,
    }));

    const createForm = useForm({
        product_id: '',
        image_path: '',
        sort_order: '0',
        is_primary: '0',
        is_active: '1',
    });

    const editForm = useForm({
        image_path: '',
        sort_order: '0',
        is_primary: '0',
        is_active: '1',
    });

    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('product-service.product-master.images.store'));
    };

    const startEdit = (row: ImageRow) => {
        setEditingId(row.id);
        editForm.setData({
            image_path: row.image_path,
            sort_order: String(row.sort_order ?? 0),
            is_primary: row.is_primary ? '1' : '0',
            is_active: row.is_active ? '1' : '0',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('product-service.product-master.images.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('product-service.product-master.images.destroy', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('Product Setup') }, { label: t('Product Images') }]}
            pageTitle={t('Product Images')}
        >
            <Head title={t('Product Images')} />

            <TextileKpiOverview
                title={t('Image Coverage')}
                className="mb-6"
                items={[
                    { label: t('Total Images'), value: stats.total ?? 0 },
                    { label: t('Primary Images'), value: stats.primary ?? 0 },
                    { label: t('Active'), value: stats.active ?? 0 },
                    { label: t('Inactive'), value: stats.inactive ?? 0 },
                ]}
            />

            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Product Image')} icon={ImageIcon} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Item')} value={createForm.data.product_id} onChange={(value) => createForm.setData('product_id', value)} options={itemOptions} includeEmpty emptyLabel={t('Select item')} required />
                        <Field label={t('Image Path')} value={createForm.data.image_path} onChange={(value) => createForm.setData('image_path', value)} required />
                        <Field label={t('Sort Order')} type="number" value={createForm.data.sort_order} onChange={(value) => createForm.setData('sort_order', value)} />
                        <SelectField label={t('Primary')} value={createForm.data.is_primary} onChange={(value) => createForm.setData('is_primary', value)} options={yesNoOptions} required />
                        <SelectField label={t('Active')} value={createForm.data.is_active} onChange={(value) => createForm.setData('is_active', value)} options={yesNoOptions} required />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Image')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Product Image')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Image Path')} value={editForm.data.image_path} onChange={(value) => editForm.setData('image_path', value)} required />
                                <Field label={t('Sort Order')} type="number" value={editForm.data.sort_order} onChange={(value) => editForm.setData('sort_order', value)} />
                                <SelectField label={t('Primary')} value={editForm.data.is_primary} onChange={(value) => editForm.setData('is_primary', value)} options={yesNoOptions} required />
                                <SelectField label={t('Active')} value={editForm.data.is_active} onChange={(value) => editForm.setData('is_active', value)} options={yesNoOptions} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <Button type="submit" disabled={editForm.processing}>{t('Save')}</Button>
                                    <Button type="button" variant="outline" onClick={() => setEditingId(null)}>{t('Cancel')}</Button>
                                </div>
                            </form>
                        </>
                    ) : null}
                </TextileFormCard>

                <TextileDataTableCard
                    data={images}
                    columns={[
                        { key: 'product', header: t('Item'), render: (_value: unknown, row: ImageRow) => row.product ? `${row.product.name} (${row.product.sku})` : '-' },
                        { key: 'image_path', header: t('Image Path') },
                        { key: 'sort_order', header: t('Order') },
                        { key: 'is_primary', header: t('Primary'), render: (value: unknown) => (value ? 'yes' : 'no') },
                        { key: 'is_active', header: t('Status'), render: (value: unknown) => (value ? 'active' : 'inactive') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: ImageRow) => (
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
                    emptyState={<NoRecordsFound icon={ImageIcon} title={t('No product images found')} description={t('Add product image records with ordering and primary-image control.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
