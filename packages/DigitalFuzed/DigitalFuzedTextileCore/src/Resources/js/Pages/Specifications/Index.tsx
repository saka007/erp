import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Factory, Pencil, ArchiveX } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface Specification {
    id: number;
    name: string;
    code?: string | null;
    family?: string | null;
    yarn_type?: string | null;
    yarn_count?: string | null;
    denier?: string | null;
    blend?: string | null;
    mill?: string | null;
    brand?: string | null;
    net_weight?: string | null;
    gross_weight?: string | null;
    moisture?: string | null;
    quality_grade?: string | null;
    yarn_cost?: number | null;
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
        name: '', code: '', family: '', yarn_type: '', yarn_count: '', denier: '', blend: '', mill: '', brand: '', net_weight: '', gross_weight: '', moisture: '', quality_grade: '', yarn_cost: '', composition: '', construction: '', width: '', gsm: '', shade: '',
    });
    const {
        data: editData,
        setData: setEditData,
        post: postEdit,
        processing: editing,
        reset: resetEdit,
    } = useForm({
        specification_id: '', name: '', code: '', family: '', yarn_type: '', yarn_count: '', denier: '', blend: '', mill: '', brand: '', net_weight: '', gross_weight: '', moisture: '', quality_grade: '', yarn_cost: '', composition: '', construction: '', width: '', gsm: '', shade: '',
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
            yarn_type: specification.yarn_type ?? '',
            yarn_count: specification.yarn_count ?? '',
            denier: specification.denier ?? '',
            blend: specification.blend ?? '',
            mill: specification.mill ?? '',
            brand: specification.brand ?? '',
            net_weight: specification.net_weight ?? '',
            gross_weight: specification.gross_weight ?? '',
            moisture: specification.moisture ?? '',
            quality_grade: specification.quality_grade ?? '',
            yarn_cost: specification.yarn_cost?.toString() ?? '',
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
                <TextileFormCard title={t('New Specification')} icon={Factory} contentClassName="p-5 space-y-6">
                        <form onSubmit={submit} className="space-y-4">
                            <Field label={t('Name')} value={data.name} onChange={(value) => setData('name', value)} required />
                            <Field label={t('Code')} value={data.code} onChange={(value) => setData('code', value)} />
                            <Field label={t('Family')} value={data.family} onChange={(value) => setData('family', value)} />
                            <div className="border-t pt-4 space-y-4">
                                <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{t('Yarn Attributes')}</h3>
                                <Field label={t('Yarn Type')} value={data.yarn_type} onChange={(value) => setData('yarn_type', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Yarn Count')} value={data.yarn_count} onChange={(value) => setData('yarn_count', value)} />
                                    <Field label={t('Denier')} value={data.denier} onChange={(value) => setData('denier', value)} />
                                </div>
                                <Field label={t('Blend')} value={data.blend} onChange={(value) => setData('blend', value)} />
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Mill')} value={data.mill} onChange={(value) => setData('mill', value)} />
                                    <Field label={t('Brand')} value={data.brand} onChange={(value) => setData('brand', value)} />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Net Weight')} value={data.net_weight} onChange={(value) => setData('net_weight', value)} />
                                    <Field label={t('Gross Weight')} value={data.gross_weight} onChange={(value) => setData('gross_weight', value)} />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label={t('Moisture')} value={data.moisture} onChange={(value) => setData('moisture', value)} />
                                    <Field label={t('Quality Grade')} value={data.quality_grade} onChange={(value) => setData('quality_grade', value)} />
                                </div>
                                <Field label={t('Yarn Cost')} value={data.yarn_cost} onChange={(value) => setData('yarn_cost', value)} />
                            </div>
                            <Field label={t('Composition')} value={data.composition} onChange={(value) => setData('composition', value)} />
                            <Field label={t('Construction')} value={data.construction} onChange={(value) => setData('construction', value)} />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Width')} value={data.width} onChange={(value) => setData('width', value)} />
                                <Field label={t('GSM')} value={data.gsm} onChange={(value) => setData('gsm', value)} />
                            </div>
                            <Field label={t('Shade')} value={data.shade} onChange={(value) => setData('shade', value)} />
                            <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Specification')}</Button>
                        </form>

                        {editingId !== null ? (
                            <>
                                <div className="border-t border-border pt-5" />
                                <h2 className="font-semibold">{t('Edit Specification')}</h2>
                                <form onSubmit={submitEdit} className="space-y-4">
                                    <Field label={t('Name')} value={editData.name} onChange={(value) => setEditData('name', value)} required />
                                    <Field label={t('Code')} value={editData.code} onChange={(value) => setEditData('code', value)} />
                                    <Field label={t('Family')} value={editData.family} onChange={(value) => setEditData('family', value)} />
                                    <div className="border-t pt-4 space-y-4">
                                        <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{t('Yarn Attributes')}</h3>
                                        <Field label={t('Yarn Type')} value={editData.yarn_type} onChange={(value) => setEditData('yarn_type', value)} />
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field label={t('Yarn Count')} value={editData.yarn_count} onChange={(value) => setEditData('yarn_count', value)} />
                                            <Field label={t('Denier')} value={editData.denier} onChange={(value) => setEditData('denier', value)} />
                                        </div>
                                        <Field label={t('Blend')} value={editData.blend} onChange={(value) => setEditData('blend', value)} />
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field label={t('Mill')} value={editData.mill} onChange={(value) => setEditData('mill', value)} />
                                            <Field label={t('Brand')} value={editData.brand} onChange={(value) => setEditData('brand', value)} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field label={t('Net Weight')} value={editData.net_weight} onChange={(value) => setEditData('net_weight', value)} />
                                            <Field label={t('Gross Weight')} value={editData.gross_weight} onChange={(value) => setEditData('gross_weight', value)} />
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field label={t('Moisture')} value={editData.moisture} onChange={(value) => setEditData('moisture', value)} />
                                            <Field label={t('Quality Grade')} value={editData.quality_grade} onChange={(value) => setEditData('quality_grade', value)} />
                                        </div>
                                        <Field label={t('Yarn Cost')} value={editData.yarn_cost} onChange={(value) => setEditData('yarn_cost', value)} />
                                    </div>
                                    <Field label={t('Composition')} value={editData.composition} onChange={(value) => setEditData('composition', value)} />
                                    <Field label={t('Construction')} value={editData.construction} onChange={(value) => setEditData('construction', value)} />
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field label={t('Width')} value={editData.width} onChange={(value) => setEditData('width', value)} />
                                        <Field label={t('GSM')} value={editData.gsm} onChange={(value) => setEditData('gsm', value)} />
                                    </div>
                                    <Field label={t('Shade')} value={editData.shade} onChange={(value) => setEditData('shade', value)} />
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
            </div>
        </AuthenticatedLayout>
    );
}