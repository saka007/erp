import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BadgeDollarSign, Pencil, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import NoRecordsFound from '@/components/no-records-found';

interface Option {
    value: string;
    label: string;
}

interface CustomerOption {
    id: number;
    company_name: string;
}

interface ItemOption {
    id: number;
    name: string;
    sku?: string | null;
    unit?: string | null;
}

interface PriceListRow {
    id: number;
    customer_id: number;
    product_service_item_id: number;
    unit_price: string;
    currency_code: string;
    min_quantity: string;
    is_active: boolean;
    notes?: string | null;
    customer?: { company_name: string };
    item?: { name: string; sku?: string | null; unit?: string | null };
}

const statusOptions: Option[] = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' },
];

export default function Index({
    priceLists,
    customers,
    items,
    currencyOptions,
    defaultCurrency,
}: {
    priceLists: PriceListRow[];
    customers: CustomerOption[];
    items: ItemOption[];
    currencyOptions: Option[];
    defaultCurrency: string;
}) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        customer_id: '',
        product_service_item_id: '',
        unit_price: '',
        currency_code: defaultCurrency || 'USD',
        min_quantity: '1',
        notes: '',
    });

    const editForm = useForm({
        unit_price: '',
        currency_code: defaultCurrency || 'USD',
        min_quantity: '1',
        is_active: '1',
        notes: '',
    });

    const deleteForm = useForm({});

    const customerOptions = customers.map((customer) => ({
        value: String(customer.id),
        label: customer.company_name,
    }));

    const itemOptions = items.map((item) => ({
        value: String(item.id),
        label: `${item.name}${item.sku ? ` (${item.sku})` : ''}`,
    }));

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('account.customer-price-lists.store'), {
            onSuccess: () => createForm.reset('product_service_item_id', 'unit_price', 'min_quantity', 'notes'),
        });
    };

    const startEdit = (row: PriceListRow) => {
        setEditingId(row.id);
        editForm.setData({
            unit_price: String(row.unit_price ?? ''),
            currency_code: row.currency_code || defaultCurrency || 'USD',
            min_quantity: String(row.min_quantity ?? '1'),
            is_active: row.is_active ? '1' : '0',
            notes: row.notes || '',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('account.customer-price-lists.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('account.customer-price-lists.destroy', id));
        if (editingId === id) {
            setEditingId(null);
            editForm.reset();
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('CRM & Suppliers') }, { label: t('Customer Price List') }]}
            pageTitle={t('Customer Price List')}
        >
            <Head title={t('Customer Price List')} />
            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Set Customer Price')} icon={BadgeDollarSign} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField
                            label={t('Customer')}
                            value={createForm.data.customer_id}
                            onChange={(value) => createForm.setData('customer_id', value)}
                            options={customerOptions}
                            includeEmpty
                            emptyLabel={t('Select customer')}
                            required
                        />
                        <SelectField
                            label={t('Item')}
                            value={createForm.data.product_service_item_id}
                            onChange={(value) => createForm.setData('product_service_item_id', value)}
                            options={itemOptions}
                            includeEmpty
                            emptyLabel={t('Select item')}
                            required
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Unit Price')} type="number" value={createForm.data.unit_price} onChange={(value) => createForm.setData('unit_price', value)} required />
                            <SelectField label={t('Currency')} value={createForm.data.currency_code} onChange={(value) => createForm.setData('currency_code', value)} options={currencyOptions} required />
                        </div>
                        <Field label={t('Minimum Quantity')} type="number" value={createForm.data.min_quantity} onChange={(value) => createForm.setData('min_quantity', value)} />
                        <Field label={t('Notes')} value={createForm.data.notes} onChange={(value) => createForm.setData('notes', value)} />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Price')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Customer Price')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Unit Price')} type="number" value={editForm.data.unit_price} onChange={(value) => editForm.setData('unit_price', value)} required />
                                    <SelectField label={t('Currency')} value={editForm.data.currency_code} onChange={(value) => editForm.setData('currency_code', value)} options={currencyOptions} required />
                                </div>
                                <Field label={t('Minimum Quantity')} type="number" value={editForm.data.min_quantity} onChange={(value) => editForm.setData('min_quantity', value)} />
                                <SelectField label={t('Status')} value={editForm.data.is_active} onChange={(value) => editForm.setData('is_active', value)} options={statusOptions} required />
                                <Field label={t('Notes')} value={editForm.data.notes} onChange={(value) => editForm.setData('notes', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <Button type="submit" disabled={editForm.processing}>{t('Save')}</Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setEditingId(null);
                                            editForm.reset();
                                        }}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                </div>
                            </form>
                        </>
                    ) : null}
                </TextileFormCard>

                <TextileDataTableCard
                    data={priceLists}
                    columns={[
                        { key: 'customer', header: t('Customer'), render: (_value: unknown, row: PriceListRow) => row.customer?.company_name || '-' },
                        { key: 'item', header: t('Item'), render: (_value: unknown, row: PriceListRow) => row.item?.name || '-' },
                        { key: 'sku', header: t('SKU'), render: (_value: unknown, row: PriceListRow) => row.item?.sku || '-' },
                        { key: 'unit_price', header: t('Unit Price') },
                        { key: 'currency_code', header: t('Currency') },
                        { key: 'min_quantity', header: t('Min Qty') },
                        { key: 'is_active', header: t('Status'), render: (value: unknown) => (value ? 'active' : 'inactive') },
                        { key: 'notes', header: t('Notes'), render: (value: unknown) => String(value || '-') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: PriceListRow) => (
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
                    emptyState={<NoRecordsFound icon={BadgeDollarSign} title={t('No customer prices found')} description={t('Create item-level customer pricing to standardize quotations and orders.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
