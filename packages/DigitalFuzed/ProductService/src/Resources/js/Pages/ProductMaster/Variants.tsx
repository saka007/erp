import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Scale, Trash2 } from 'lucide-react';
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

interface VariantRow {
    id: number;
    product_id: number;
    variant_type: string;
    variant_label: string;
    variant_value: string;
    unit?: string | null;
    sku_suffix?: string | null;
    is_active: boolean;
    product?: ItemOption;
}

const variantTypeOptions = [
    { value: 'count', label: 'Count' },
    { value: 'denier', label: 'Denier' },
    { value: 'shade', label: 'Shade' },
    { value: 'width', label: 'Width' },
    { value: 'generic', label: 'Generic' },
];

const yesNoOptions = [
    { value: '1', label: 'Yes' },
    { value: '0', label: 'No' },
];

export default function Variants({ variants, items, stats }: { variants: VariantRow[]; items: ItemOption[]; stats: Record<string, number> }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const itemOptions = items.map((item) => ({
        value: String(item.id),
        label: `${item.name} (${item.sku})`,
    }));

    const createForm = useForm({
        product_id: '',
        variant_type: 'count',
        variant_label: '',
        variant_value: '',
        unit: '',
        sku_suffix: '',
        is_active: '1',
    });

    const editForm = useForm({
        variant_type: 'count',
        variant_label: '',
        variant_value: '',
        unit: '',
        sku_suffix: '',
        is_active: '1',
    });

    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('product-service.product-master.variants.store'));
    };

    const startEdit = (row: VariantRow) => {
        setEditingId(row.id);
        editForm.setData({
            variant_type: row.variant_type,
            variant_label: row.variant_label,
            variant_value: row.variant_value,
            unit: row.unit || '',
            sku_suffix: row.sku_suffix || '',
            is_active: row.is_active ? '1' : '0',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('product-service.product-master.variants.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('product-service.product-master.variants.destroy', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('Products') }, { label: t('Product Variants') }]}
            pageTitle={t('Product Variants')}
        >
            <Head title={t('Product Variants')} />

            <TextileKpiOverview
                title={t('Variant Coverage')}
                className="mb-6"
                items={[
                    { label: t('Total Variants'), value: stats.total ?? 0 },
                    { label: t('Active'), value: stats.active ?? 0 },
                    { label: t('Inactive'), value: stats.inactive ?? 0 },
                    { label: t('Yarn Scoped'), value: stats.yarnScoped ?? 0 },
                ]}
            />

            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Product Variant')} icon={Scale} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Item')} value={createForm.data.product_id} onChange={(value) => createForm.setData('product_id', value)} options={itemOptions} includeEmpty emptyLabel={t('Select item')} required />
                        <SelectField label={t('Variant Type')} value={createForm.data.variant_type} onChange={(value) => createForm.setData('variant_type', value)} options={variantTypeOptions} required />
                        <Field label={t('Variant Label')} value={createForm.data.variant_label} onChange={(value) => createForm.setData('variant_label', value)} required />
                        <Field label={t('Variant Value')} value={createForm.data.variant_value} onChange={(value) => createForm.setData('variant_value', value)} required />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Unit')} value={createForm.data.unit} onChange={(value) => createForm.setData('unit', value)} />
                            <Field label={t('SKU Suffix')} value={createForm.data.sku_suffix} onChange={(value) => createForm.setData('sku_suffix', value)} />
                        </div>
                        <SelectField label={t('Active')} value={createForm.data.is_active} onChange={(value) => createForm.setData('is_active', value)} options={yesNoOptions} required />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Variant')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Variant')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <SelectField label={t('Variant Type')} value={editForm.data.variant_type} onChange={(value) => editForm.setData('variant_type', value)} options={variantTypeOptions} required />
                                <Field label={t('Variant Label')} value={editForm.data.variant_label} onChange={(value) => editForm.setData('variant_label', value)} required />
                                <Field label={t('Variant Value')} value={editForm.data.variant_value} onChange={(value) => editForm.setData('variant_value', value)} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Unit')} value={editForm.data.unit} onChange={(value) => editForm.setData('unit', value)} />
                                    <Field label={t('SKU Suffix')} value={editForm.data.sku_suffix} onChange={(value) => editForm.setData('sku_suffix', value)} />
                                </div>
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
                    data={variants}
                    columns={[
                        { key: 'product', header: t('Item'), render: (_value: unknown, row: VariantRow) => row.product ? `${row.product.name} (${row.product.sku})` : '-' },
                        { key: 'variant_type', header: t('Type') },
                        { key: 'variant_label', header: t('Label') },
                        { key: 'variant_value', header: t('Value') },
                        { key: 'unit', header: t('Unit') },
                        { key: 'sku_suffix', header: t('SKU Suffix') },
                        { key: 'is_active', header: t('Status'), render: (value: unknown) => (value ? 'active' : 'inactive') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: VariantRow) => (
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
                    emptyState={<NoRecordsFound icon={Scale} title={t('No product variants found')} description={t('Add product variants for count, denier, shade, and width mapping.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
