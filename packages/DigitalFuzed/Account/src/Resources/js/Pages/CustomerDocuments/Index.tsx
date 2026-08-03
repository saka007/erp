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
import NoRecordsFound from '@/components/no-records-found';

interface CustomerDocumentRow {
    id: number;
    customer_id: number;
    document_name: string;
    document_type: string;
    document_reference?: string | null;
    status: string;
    issue_date?: string | null;
    expiry_date?: string | null;
    notes?: string | null;
    customer?: { company_name: string };
}

export default function Index({
    documents,
    customers,
    documentTypes,
    statusOptions,
}: {
    documents: CustomerDocumentRow[];
    customers: Array<{ id: number; company_name: string }>;
    documentTypes: string[];
    statusOptions: string[];
}) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const customerOptions = customers.map((customer) => ({ value: String(customer.id), label: customer.company_name }));
    const typeOptions = documentTypes.map((value) => ({ value, label: value }));
    const docStatusOptions = statusOptions.map((value) => ({ value, label: value }));

    const createForm = useForm({
        customer_id: '',
        document_name: '',
        document_type: documentTypes[0] || 'other',
        document_reference: '',
        status: statusOptions[0] || 'active',
        issue_date: '',
        expiry_date: '',
        notes: '',
    });

    const editForm = useForm({
        document_name: '',
        document_type: documentTypes[0] || 'other',
        document_reference: '',
        status: statusOptions[0] || 'active',
        issue_date: '',
        expiry_date: '',
        notes: '',
    });

    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('account.customer-documents.store'), {
            onSuccess: () => createForm.reset('document_name', 'document_reference', 'issue_date', 'expiry_date', 'notes'),
        });
    };

    const startEdit = (row: CustomerDocumentRow) => {
        setEditingId(row.id);
        editForm.setData({
            document_name: row.document_name,
            document_type: row.document_type,
            document_reference: row.document_reference || '',
            status: row.status,
            issue_date: row.issue_date || '',
            expiry_date: row.expiry_date || '',
            notes: row.notes || '',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('account.customer-documents.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('account.customer-documents.destroy', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('CRM Setup') }, { label: t('Customer Documents') }]}
            pageTitle={t('Customer Documents')}
        >
            <Head title={t('Customer Documents')} />
            <div className="grid gap-6 xl:grid-cols-[460px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Customer Document')} icon={FileText} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Customer')} value={createForm.data.customer_id} onChange={(value) => createForm.setData('customer_id', value)} options={customerOptions} includeEmpty emptyLabel={t('Select customer')} required />
                        <Field label={t('Document Name')} value={createForm.data.document_name} onChange={(value) => createForm.setData('document_name', value)} required />
                        <div className="grid grid-cols-2 gap-3">
                            <SelectField label={t('Document Type')} value={createForm.data.document_type} onChange={(value) => createForm.setData('document_type', value)} options={typeOptions} required />
                            <SelectField label={t('Status')} value={createForm.data.status} onChange={(value) => createForm.setData('status', value)} options={docStatusOptions} required />
                        </div>
                        <Field label={t('Document Reference')} value={createForm.data.document_reference} onChange={(value) => createForm.setData('document_reference', value)} />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Issue Date')} type="date" value={createForm.data.issue_date} onChange={(value) => createForm.setData('issue_date', value)} />
                            <Field label={t('Expiry Date')} type="date" value={createForm.data.expiry_date} onChange={(value) => createForm.setData('expiry_date', value)} />
                        </div>
                        <Field label={t('Notes')} value={createForm.data.notes} onChange={(value) => createForm.setData('notes', value)} />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Document')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Customer Document')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Document Name')} value={editForm.data.document_name} onChange={(value) => editForm.setData('document_name', value)} required />
                                <div className="grid grid-cols-2 gap-3">
                                    <SelectField label={t('Document Type')} value={editForm.data.document_type} onChange={(value) => editForm.setData('document_type', value)} options={typeOptions} required />
                                    <SelectField label={t('Status')} value={editForm.data.status} onChange={(value) => editForm.setData('status', value)} options={docStatusOptions} required />
                                </div>
                                <Field label={t('Document Reference')} value={editForm.data.document_reference} onChange={(value) => editForm.setData('document_reference', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Issue Date')} type="date" value={editForm.data.issue_date} onChange={(value) => editForm.setData('issue_date', value)} />
                                    <Field label={t('Expiry Date')} type="date" value={editForm.data.expiry_date} onChange={(value) => editForm.setData('expiry_date', value)} />
                                </div>
                                <Field label={t('Notes')} value={editForm.data.notes} onChange={(value) => editForm.setData('notes', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <Button type="submit" disabled={editForm.processing}>{t('Save')}</Button>
                                    <Button type="button" variant="outline" onClick={() => { setEditingId(null); editForm.reset(); }}>{t('Cancel')}</Button>
                                </div>
                            </form>
                        </>
                    ) : null}
                </TextileFormCard>

                <TextileDataTableCard
                    data={documents}
                    columns={[
                        { key: 'customer', header: t('Customer'), render: (_value: unknown, row: CustomerDocumentRow) => row.customer?.company_name || '-' },
                        { key: 'document_name', header: t('Document Name') },
                        { key: 'document_type', header: t('Type') },
                        { key: 'document_reference', header: t('Reference'), render: (value: unknown) => String(value || '-') },
                        { key: 'status', header: t('Status') },
                        { key: 'issue_date', header: t('Issue Date'), render: (value: unknown) => String(value || '-') },
                        { key: 'expiry_date', header: t('Expiry Date'), render: (value: unknown) => String(value || '-') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: CustomerDocumentRow) => (
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
                    emptyState={<NoRecordsFound icon={FileText} title={t('No customer documents found')} description={t('Maintain customer compliance and commercial records.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
