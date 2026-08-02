import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, ArchiveX, Landmark } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface CostCenter {
    id: number;
    name: string;
    code?: string | null;
    notes?: string | null;
}

export default function Index({ costCenters }: { costCenters: CostCenter[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const { data, setData, post, processing, reset } = useForm({
        name: '',
        code: '',
        notes: '',
    });

    const {
        data: editData,
        setData: setEditData,
        post: postEdit,
        processing: editing,
        reset: resetEdit,
    } = useForm({
        cost_center_id: '',
        name: '',
        code: '',
        notes: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('textile.cost-centers.store'), { onSuccess: () => reset() });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        postEdit(route('textile.cost-centers.update'));
    };

    const startEdit = (costCenter: CostCenter) => {
        setEditingId(costCenter.id);
        setEditData({
            cost_center_id: String(costCenter.id),
            name: costCenter.name ?? '',
            code: costCenter.code ?? '',
            notes: costCenter.notes ?? '',
        });
    };

    const archive = (costCenterId: number) => {
        router.post(route('textile.cost-centers.archive'), { cost_center_id: costCenterId });
        if (editingId === costCenterId) {
            setEditingId(null);
            resetEdit();
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Cost Centers') }]} pageTitle={t('Textile Cost Centers')}>
            <Head title={t('Textile Cost Centers')} />
            <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <TextileFormCard title={t('New Cost Center')} icon={Landmark} contentClassName="p-5 space-y-6">
                        <form onSubmit={submit} className="space-y-4">
                            <Field label={t('Name')} value={data.name} onChange={(value) => setData('name', value)} required />
                            <Field label={t('Code')} value={data.code} onChange={(value) => setData('code', value)} />
                            <Field label={t('Notes')} value={data.notes} onChange={(value) => setData('notes', value)} />
                            <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Cost Center')}</Button>
                        </form>

                        {editingId !== null ? (
                            <>
                                <div className="border-t border-border pt-5" />
                                <h2 className="font-semibold">{t('Edit Cost Center')}</h2>
                                <form onSubmit={submitEdit} className="space-y-4">
                                    <Field label={t('Name')} value={editData.name} onChange={(value) => setEditData('name', value)} required />
                                    <Field label={t('Code')} value={editData.code} onChange={(value) => setEditData('code', value)} />
                                    <Field label={t('Notes')} value={editData.notes} onChange={(value) => setEditData('notes', value)} />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Button type="submit" disabled={editing}>{t('Save Changes')}</Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                setEditingId(null);
                                                resetEdit();
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
                    data={costCenters}
                    columns={[
                        { key: 'name', header: t('Name') },
                        { key: 'code', header: t('Code'), render: optional },
                        { key: 'notes', header: t('Notes'), render: optional },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: CostCenter) => (
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
                    emptyState={<NoRecordsFound icon={Landmark} title={t('No cost centers found')} description={t('Create cost centers to tag textile operating costs by department or activity.')} onCreateClick={() => router.reload()} createButtonText={t('Refresh')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}
