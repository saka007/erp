import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CalendarCheck2, Pencil, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import NoRecordsFound from '@/components/no-records-found';

interface FollowUpRow {
    id: number;
    customer_id: number;
    customer_contact_id?: number | null;
    follow_up_date: string;
    next_follow_up_date?: string | null;
    channel: string;
    status: string;
    notes?: string | null;
    customer?: { company_name: string };
    contact?: { name: string };
}

interface ContactOption {
    id: number;
    customer_id: number;
    name: string;
}

export default function Index({
    followUps,
    customers,
    contacts,
    channelOptions,
    statusOptions,
}: {
    followUps: FollowUpRow[];
    customers: Array<{ id: number; company_name: string }>;
    contacts: ContactOption[];
    channelOptions: string[];
    statusOptions: string[];
}) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const customerOptions = customers.map((customer) => ({ value: String(customer.id), label: customer.company_name }));

    const createForm = useForm({
        customer_id: '',
        customer_contact_id: '',
        follow_up_date: '',
        next_follow_up_date: '',
        channel: channelOptions[0] || 'call',
        status: 'pending',
        notes: '',
    });

    const editForm = useForm({
        customer_contact_id: '',
        follow_up_date: '',
        next_follow_up_date: '',
        channel: channelOptions[0] || 'call',
        status: 'pending',
        notes: '',
    });

    const deleteForm = useForm({});

    const currentCreateContactOptions = contacts
        .filter((contact) => String(contact.customer_id) === createForm.data.customer_id)
        .map((contact) => ({ value: String(contact.id), label: contact.name }));

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('account.customer-follow-ups.store'), {
            onSuccess: () => createForm.reset('customer_contact_id', 'follow_up_date', 'next_follow_up_date', 'notes'),
        });
    };

    const startEdit = (row: FollowUpRow) => {
        setEditingId(row.id);
        editForm.setData({
            customer_contact_id: row.customer_contact_id ? String(row.customer_contact_id) : '',
            follow_up_date: row.follow_up_date,
            next_follow_up_date: row.next_follow_up_date || '',
            channel: row.channel,
            status: row.status,
            notes: row.notes || '',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('account.customer-follow-ups.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('account.customer-follow-ups.destroy', id));
    };

    const channelSelectOptions = channelOptions.map((value) => ({ value, label: value }));
    const statusSelectOptions = statusOptions.map((value) => ({ value, label: value }));

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('CRM Setup') }, { label: t('Customer Follow Ups') }]}
            pageTitle={t('Customer Follow Ups')}
        >
            <Head title={t('Customer Follow Ups')} />
            <div className="grid gap-6 xl:grid-cols-[460px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Follow Up')} icon={CalendarCheck2} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Customer')} value={createForm.data.customer_id} onChange={(value) => createForm.setData('customer_id', value)} options={customerOptions} includeEmpty emptyLabel={t('Select customer')} required />
                        <SelectField label={t('Customer Contact')} value={createForm.data.customer_contact_id} onChange={(value) => createForm.setData('customer_contact_id', value)} options={currentCreateContactOptions} includeEmpty emptyLabel={t('Optional contact')} />
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Follow Up Date')} type="date" value={createForm.data.follow_up_date} onChange={(value) => createForm.setData('follow_up_date', value)} required />
                            <Field label={t('Next Follow Up Date')} type="date" value={createForm.data.next_follow_up_date} onChange={(value) => createForm.setData('next_follow_up_date', value)} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <SelectField label={t('Channel')} value={createForm.data.channel} onChange={(value) => createForm.setData('channel', value)} options={channelSelectOptions} required />
                            <SelectField label={t('Status')} value={createForm.data.status} onChange={(value) => createForm.setData('status', value)} options={statusSelectOptions} required />
                        </div>
                        <Field label={t('Notes')} value={createForm.data.notes} onChange={(value) => createForm.setData('notes', value)} />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Save Follow Up')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Follow Up')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Follow Up Date')} type="date" value={editForm.data.follow_up_date} onChange={(value) => editForm.setData('follow_up_date', value)} required />
                                <Field label={t('Next Follow Up Date')} type="date" value={editForm.data.next_follow_up_date} onChange={(value) => editForm.setData('next_follow_up_date', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <SelectField label={t('Channel')} value={editForm.data.channel} onChange={(value) => editForm.setData('channel', value)} options={channelSelectOptions} required />
                                    <SelectField label={t('Status')} value={editForm.data.status} onChange={(value) => editForm.setData('status', value)} options={statusSelectOptions} required />
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
                    data={followUps}
                    columns={[
                        { key: 'customer', header: t('Customer'), render: (_value: unknown, row: FollowUpRow) => row.customer?.company_name || '-' },
                        { key: 'contact', header: t('Contact'), render: (_value: unknown, row: FollowUpRow) => row.contact?.name || '-' },
                        { key: 'follow_up_date', header: t('Follow Up Date') },
                        { key: 'next_follow_up_date', header: t('Next Date'), render: (value: unknown) => String(value || '-') },
                        { key: 'channel', header: t('Channel') },
                        { key: 'status', header: t('Status') },
                        { key: 'notes', header: t('Notes'), render: (value: unknown) => String(value || '-') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: FollowUpRow) => (
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
                    emptyState={<NoRecordsFound icon={CalendarCheck2} title={t('No follow ups found')} description={t('Track follow-up commitments against customer accounts.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
