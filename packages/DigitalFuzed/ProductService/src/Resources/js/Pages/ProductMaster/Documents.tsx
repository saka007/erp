import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FileText, Pencil, Trash2 } from 'lucide-react';
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

interface DocumentRow {
    id: number;
    product_id: number;
    document_type: string;
    document_number?: string | null;
    document_path: string;
    issued_on?: string | null;
    expires_on?: string | null;
    is_active: boolean;
    product?: ItemOption;
}

const documentTypeOptions = [
    { value: 'spec_sheet', label: 'Specification Sheet' },
    { value: 'test_certificate', label: 'Test Certificate' },
    { value: 'compliance_certificate', label: 'Compliance Certificate' },
    { value: 'msds', label: 'MSDS' },
    { value: 'other', label: 'Other' },
];

const yesNoOptions = [
    { value: '1', label: 'Yes' },
    { value: '0', label: 'No' },
];

export default function Documents({ documents, items, stats }: { documents: DocumentRow[]; items: ItemOption[]; stats: Record<string, number> }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const itemOptions = items.map((item) => ({
        value: String(item.id),
        label: `${item.name} (${item.sku})`,
    }));

    const createForm = useForm({
        product_id: '',
        document_type: 'spec_sheet',
        document_number: '',
        document_path: '',
        issued_on: '',
        expires_on: '',
        is_active: '1',
    });

    const editForm = useForm({
        document_type: 'spec_sheet',
        document_number: '',
        document_path: '',
        issued_on: '',
        expires_on: '',
        is_active: '1',
    });

    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('product-service.product-master.documents.store'));
    };

    const startEdit = (row: DocumentRow) => {
        setEditingId(row.id);
        editForm.setData({
            document_type: row.document_type,
            document_number: row.document_number || '',
            document_path: row.document_path,
            issued_on: row.issued_on || '',
            expires_on: row.expires_on || '',
            is_active: row.is_active ? '1' : '0',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('product-service.product-master.documents.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('product-service.product-master.documents.destroy', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('Product Setup') }, { label: t('Product Documents') }]}
            pageTitle={t('Product Documents')}
        >
            <Head title={t('Product Documents')} />

            <TextileKpiOverview
                title={t('Document Coverage')}
                className="mb-6"
                items={[
                    { label: t('Total Documents'), value: stats.total ?? 0 },
                    { label: t('Active'), value: stats.active ?? 0 },
                    { label: t('Inactive'), value: stats.inactive ?? 0 },
                    { label: t('Expiring in 30 Days'), value: stats.expiringSoon ?? 0 },
                ]}
            />

            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Product Document')} icon={FileText} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Item')} value={createForm.data.product_id} onChange={(value) => createForm.setData('product_id', value)} options={itemOptions} includeEmpty emptyLabel={t('Select item')} required />
                        <SelectField label={t('Document Type')} value={createForm.data.document_type} onChange={(value) => createForm.setData('document_type', value)} options={documentTypeOptions} required />
                        <Field label={t('Document Number')} value={createForm.data.document_number} onChange={(value) => createForm.setData('document_number', value)} />
                        <Field label={t('Document Path')} value={createForm.data.document_path} onChange={(value) => createForm.setData('document_path', value)} required />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Issued On')} type="date" value={createForm.data.issued_on} onChange={(value) => createForm.setData('issued_on', value)} />
                            <Field label={t('Expires On')} type="date" value={createForm.data.expires_on} onChange={(value) => createForm.setData('expires_on', value)} />
                        </div>
                        <SelectField label={t('Active')} value={createForm.data.is_active} onChange={(value) => createForm.setData('is_active', value)} options={yesNoOptions} required />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Document')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Product Document')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <SelectField label={t('Document Type')} value={editForm.data.document_type} onChange={(value) => editForm.setData('document_type', value)} options={documentTypeOptions} required />
                                <Field label={t('Document Number')} value={editForm.data.document_number} onChange={(value) => editForm.setData('document_number', value)} />
                                <Field label={t('Document Path')} value={editForm.data.document_path} onChange={(value) => editForm.setData('document_path', value)} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Issued On')} type="date" value={editForm.data.issued_on} onChange={(value) => editForm.setData('issued_on', value)} />
                                    <Field label={t('Expires On')} type="date" value={editForm.data.expires_on} onChange={(value) => editForm.setData('expires_on', value)} />
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
                    data={documents}
                    columns={[
                        { key: 'product', header: t('Item'), render: (_value: unknown, row: DocumentRow) => row.product ? `${row.product.name} (${row.product.sku})` : '-' },
                        { key: 'document_type', header: t('Type') },
                        { key: 'document_number', header: t('Number') },
                        { key: 'issued_on', header: t('Issued On') },
                        { key: 'expires_on', header: t('Expires On') },
                        { key: 'document_path', header: t('Path') },
                        { key: 'is_active', header: t('Status'), render: (value: unknown) => (value ? 'active' : 'inactive') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: DocumentRow) => (
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
                    emptyState={<NoRecordsFound icon={FileText} title={t('No product documents found')} description={t('Add product spec/test/compliance documents for downstream traceability.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
