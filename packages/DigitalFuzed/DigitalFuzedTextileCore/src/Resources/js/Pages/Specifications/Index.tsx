import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Factory, Pencil, ArchiveX } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DataTable } from '@/components/ui/data-table';
import NoRecordsFound from '@/components/no-records-found';

interface Specification {
    id: number;
    name: string;
    code?: string | null;
    family?: string | null;
    composition?: string | null;
    construction?: string | null;
    width?: string | null;
    gsm?: string | null;
    shade?: string | null;
}

export default function Index({ specifications }: { specifications: Specification[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);
    const { data, setData, post, processing, reset } = useForm({
        name: '', code: '', family: '', composition: '', construction: '', width: '', gsm: '', shade: '',
    });
    const {
        data: editData,
        setData: setEditData,
        post: postEdit,
        processing: editing,
        reset: resetEdit,
    } = useForm({
        specification_id: '', name: '', code: '', family: '', composition: '', construction: '', width: '', gsm: '', shade: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('textile.specifications.store'), { onSuccess: () => reset() });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        postEdit(route('textile.specifications.update'));
    };

    const startEdit = (specification: Specification) => {
        setEditingId(specification.id);
        setEditData({
            specification_id: String(specification.id),
            name: specification.name ?? '',
            code: specification.code ?? '',
            family: specification.family ?? '',
            composition: specification.composition ?? '',
            construction: specification.construction ?? '',
            width: specification.width ?? '',
            gsm: specification.gsm ?? '',
            shade: specification.shade ?? '',
        });
    };

    const archive = (specificationId: number) => {
        router.post(route('textile.specifications.archive'), { specification_id: specificationId });
        if (editingId === specificationId) {
            setEditingId(null);
            resetEdit();
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Specifications') }]} pageTitle={t('Textile Specifications')}>
            <Head title={t('Textile Specifications')} />
            <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <Card>
                    <CardContent className="p-5 space-y-6">
                        <div className="mb-5 flex items-center gap-2">
                            <Factory className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('New Specification')}</h2>
                        </div>
                        <form onSubmit={submit} className="space-y-4">
                            <div><Label>{t('Name')}</Label><Input value={data.name} onChange={(event) => setData('name', event.target.value)} required /></div>
                            <div><Label>{t('Code')}</Label><Input value={data.code} onChange={(event) => setData('code', event.target.value)} /></div>
                            <div><Label>{t('Family')}</Label><Input value={data.family} onChange={(event) => setData('family', event.target.value)} /></div>
                            <div><Label>{t('Composition')}</Label><Input value={data.composition} onChange={(event) => setData('composition', event.target.value)} /></div>
                            <div><Label>{t('Construction')}</Label><Input value={data.construction} onChange={(event) => setData('construction', event.target.value)} /></div>
                            <div className="grid grid-cols-2 gap-3">
                                <div><Label>{t('Width')}</Label><Input value={data.width} onChange={(event) => setData('width', event.target.value)} /></div>
                                <div><Label>{t('GSM')}</Label><Input value={data.gsm} onChange={(event) => setData('gsm', event.target.value)} /></div>
                            </div>
                            <div><Label>{t('Shade')}</Label><Input value={data.shade} onChange={(event) => setData('shade', event.target.value)} /></div>
                            <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Specification')}</Button>
                        </form>

                        {editingId !== null ? (
                            <>
                                <div className="border-t border-border pt-5" />
                                <h2 className="font-semibold">{t('Edit Specification')}</h2>
                                <form onSubmit={submitEdit} className="space-y-4">
                                    <div><Label>{t('Name')}</Label><Input value={editData.name} onChange={(event) => setEditData('name', event.target.value)} required /></div>
                                    <div><Label>{t('Code')}</Label><Input value={editData.code} onChange={(event) => setEditData('code', event.target.value)} /></div>
                                    <div><Label>{t('Family')}</Label><Input value={editData.family} onChange={(event) => setEditData('family', event.target.value)} /></div>
                                    <div><Label>{t('Composition')}</Label><Input value={editData.composition} onChange={(event) => setEditData('composition', event.target.value)} /></div>
                                    <div><Label>{t('Construction')}</Label><Input value={editData.construction} onChange={(event) => setEditData('construction', event.target.value)} /></div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div><Label>{t('Width')}</Label><Input value={editData.width} onChange={(event) => setEditData('width', event.target.value)} /></div>
                                        <div><Label>{t('GSM')}</Label><Input value={editData.gsm} onChange={(event) => setEditData('gsm', event.target.value)} /></div>
                                    </div>
                                    <div><Label>{t('Shade')}</Label><Input value={editData.shade} onChange={(event) => setEditData('shade', event.target.value)} /></div>
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
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={specifications}
                            columns={[
                                { key: 'name', header: t('Name') },
                                { key: 'code', header: t('Code'), render: (value: string | null) => value || '-' },
                                { key: 'family', header: t('Family'), render: (value: string | null) => value || '-' },
                                { key: 'composition', header: t('Composition'), render: (value: string | null) => value || '-' },
                                { key: 'width', header: t('Width'), render: (value: string | null) => value || '-' },
                                { key: 'gsm', header: t('GSM'), render: (value: string | null) => value || '-' },
                                {
                                    key: 'actions',
                                    header: t('Actions'),
                                    render: (_value: unknown, row: Specification) => (
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
                            emptyState={<NoRecordsFound icon={Factory} title={t('No textile specifications found')} description={t('Create the first specification to define textile products.')} onCreateClick={() => router.reload()} createButtonText={t('Refresh')} />}
                        />
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}