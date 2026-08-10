import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, Trash2, Tags } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import NoRecordsFound from '@/components/no-records-found';

interface CustomerCategory {
    id: number;
    name: string;
    code?: string | null;
    description?: string | null;
    is_active: boolean;
}

const statusOptions = [
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' },
];

export default function Index({ categories }: { categories: CustomerCategory[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        name: '',
        code: '',
        description: '',
    });

    const editForm = useForm({
        name: '',
        code: '',
        description: '',
        is_active: '1',
    });
    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('account.customer-categories.store'), {
            onSuccess: () => createForm.reset('name', 'code', 'description'),
        });
    };

    const startEdit = (row: CustomerCategory) => {
        setEditingId(row.id);
        editForm.setData({
            name: row.name,
            code: row.code ?? '',
            description: row.description ?? '',
            is_active: row.is_active ? '1' : '0',
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!editingId) {
            return;
        }

        editForm.put(route('account.customer-categories.update', editingId), {
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('account.customer-categories.destroy', id));
        if (editingId === id) {
            setEditingId(null);
            editForm.reset();
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('CRM & Suppliers') }, { label: t('Customer Categories') }]}
            pageTitle={t('Customer Categories')}
        >
            <Head title={t('Customer Categories')} />
            <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                <TextileFormCard title={t('Create Category')} icon={Tags} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <Field label={t('Name')} value={createForm.data.name} onChange={(value) => createForm.setData('name', value)} required />
                        <Field label={t('Code')} value={createForm.data.code} onChange={(value) => createForm.setData('code', value)} />
                        <Field label={t('Description')} value={createForm.data.description} onChange={(value) => createForm.setData('description', value)} />
                        <Button type="submit" disabled={createForm.processing} className="w-full">
                            <Plus className="mr-2 h-4 w-4" />
                            {t('Create Category')}
                        </Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Category')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <Field label={t('Name')} value={editForm.data.name} onChange={(value) => editForm.setData('name', value)} required />
                                <Field label={t('Code')} value={editForm.data.code} onChange={(value) => editForm.setData('code', value)} />
                                <Field label={t('Description')} value={editForm.data.description} onChange={(value) => editForm.setData('description', value)} />
                                <SelectField label={t('Status')} value={editForm.data.is_active} onChange={(value) => editForm.setData('is_active', value)} options={statusOptions} required />
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
                    data={categories}
                    columns={[
                        { key: 'name', header: t('Name') },
                        { key: 'code', header: t('Code'), render: (value: unknown) => String(value || '-') },
                        { key: 'description', header: t('Description'), render: (value: unknown) => String(value || '-') },
                        { key: 'is_active', header: t('Status'), render: (value: unknown) => (value ? 'active' : 'inactive') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: CustomerCategory) => (
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
                    emptyState={<NoRecordsFound icon={Tags} title={t('No customer categories found')} description={t('Create categories to segment CRM customers.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
