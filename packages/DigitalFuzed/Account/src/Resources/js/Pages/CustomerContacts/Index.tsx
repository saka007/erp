import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Contact, Pencil, Trash2 } from 'lucide-react';
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

interface ContactRow {
    id: number;
    customer_id: number;
    name: string;
    email?: string | null;
    mobile?: string | null;
    designation?: string | null;
    is_primary: boolean;
    is_active: boolean;
    customer?: { company_name: string };
}

const yesNoOptions: Option[] = [
    { value: '1', label: 'Yes' },
    { value: '0', label: 'No' },
];

export default function Index({ contacts, customers }: { contacts: ContactRow[]; customers: Array<{ id: number; company_name: string }> }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const customerOptions = customers.map((customer) => ({ value: String(customer.id), label: customer.company_name }));

    const createForm = useForm({
        customer_id: '',
        name: '',
        email: '',
        mobile: '',
        designation: '',
        is_primary: '0',
        is_active: '1',
    });

    const editForm = useForm({
        name: '',
        email: '',
        mobile: '',
        designation: '',
        is_primary: '0',
        is_active: '1',
    });

    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('account.customer-contacts.store'), {
            onSuccess: () => createForm.reset('name', 'email', 'mobile', 'designation'),
        });
    };

    const startEdit = (row: ContactRow) => {
        setEditingId(row.id);
        editForm.setData({
            name: row.name,
            email: row.email || '',
            mobile: row.mobile || '',
            designation: row.designation || '',
            is_primary: row.is_primary ? '1' : '0',
            is_active: row.is_active ? '1' : '0',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('account.customer-contacts.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('account.customer-contacts.destroy', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('CRM Setup') }, { label: t('Customer Contacts') }]}
            pageTitle={t('Customer Contacts')}
        >
            <Head title={t('Customer Contacts')} />
            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Customer Contact')} icon={Contact} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Customer')} value={createForm.data.customer_id} onChange={(value) => createForm.setData('customer_id', value)} options={customerOptions} includeEmpty emptyLabel={t('Select customer')} required />
                        <Field label={t('Name')} value={createForm.data.name} onChange={(value) => createForm.setData('name', value)} required />
                        <Field label={t('Email')} value={createForm.data.email} onChange={(value) => createForm.setData('email', value)} />
                        <Field label={t('Mobile')} value={createForm.data.mobile} onChange={(value) => createForm.setData('mobile', value)} />
                        <Field label={t('Designation')} value={createForm.data.designation} onChange={(value) => createForm.setData('designation', value)} />
                        <div className="grid grid-cols-2 gap-3">
                            <SelectField label={t('Primary Contact')} value={createForm.data.is_primary} onChange={(value) => createForm.setData('is_primary', value)} options={yesNoOptions} required />
                            <SelectField label={t('Active')} value={createForm.data.is_active} onChange={(value) => createForm.setData('is_active', value)} options={yesNoOptions} required />
                        </div>
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Contact')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Contact')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Name')} value={editForm.data.name} onChange={(value) => editForm.setData('name', value)} required />
                                <Field label={t('Email')} value={editForm.data.email} onChange={(value) => editForm.setData('email', value)} />
                                <Field label={t('Mobile')} value={editForm.data.mobile} onChange={(value) => editForm.setData('mobile', value)} />
                                <Field label={t('Designation')} value={editForm.data.designation} onChange={(value) => editForm.setData('designation', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <SelectField label={t('Primary Contact')} value={editForm.data.is_primary} onChange={(value) => editForm.setData('is_primary', value)} options={yesNoOptions} required />
                                    <SelectField label={t('Active')} value={editForm.data.is_active} onChange={(value) => editForm.setData('is_active', value)} options={yesNoOptions} required />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Button type="submit" disabled={editForm.processing}>{t('Save')}</Button>
                                    <Button type="button" variant="outline" onClick={() => { setEditingId(null); editForm.reset(); }}>{t('Cancel')}</Button>
                                </div>
                            </form>
                        </>
                    ) : null}
                </TextileFormCard>

                <TextileDataTableCard
                    data={contacts}
                    columns={[
                        { key: 'customer', header: t('Customer'), render: (_value: unknown, row: ContactRow) => row.customer?.company_name || '-' },
                        { key: 'name', header: t('Name') },
                        { key: 'designation', header: t('Designation'), render: (value: unknown) => String(value || '-') },
                        { key: 'email', header: t('Email'), render: (value: unknown) => String(value || '-') },
                        { key: 'mobile', header: t('Mobile'), render: (value: unknown) => String(value || '-') },
                        { key: 'is_primary', header: t('Primary'), render: (value: unknown) => (value ? 'yes' : 'no') },
                        { key: 'is_active', header: t('Status'), render: (value: unknown) => (value ? 'active' : 'inactive') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: ContactRow) => (
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
                    emptyState={<NoRecordsFound icon={Contact} title={t('No customer contacts found')} description={t('Add customer contacts for communication and follow-ups.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
