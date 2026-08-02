import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Factory, Plus, Pencil, ArchiveX } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NoRecordsFound from '@/components/no-records-found';

type Master = 'quality-profiles' | 'route-recipes' | 'unit-conversions';
type RecordItem = Record<string, string | number | string[] | null> & { id: number };

const metadata = {
    'quality-profiles': {
        title: 'Quality Profiles',
        createRoute: 'textile.quality-profiles.store',
        updateRoute: 'textile.quality-profiles.update',
        archiveRoute: 'textile.quality-profiles.archive',
    },
    'route-recipes': {
        title: 'Route Recipes',
        createRoute: 'textile.route-recipes.store',
        updateRoute: 'textile.route-recipes.update',
        archiveRoute: 'textile.route-recipes.archive',
    },
    'unit-conversions': {
        title: 'Unit Conversions',
        createRoute: 'textile.unit-conversions.store',
        updateRoute: 'textile.unit-conversions.update',
        archiveRoute: 'textile.unit-conversions.archive',
    },
} as const;

export default function Index({ master, records }: { master: Master; records: RecordItem[] }) {
    const { t } = useTranslation();
    const config = metadata[master];
    const [editingId, setEditingId] = useState<number | null>(null);
    const { data, setData, post, processing, reset } = useForm({ name: '', code: '', grade: '', parameters: '', steps: '', from_unit: '', to_unit: '', factor: '' });
    const { data: editData, setData: setEditData, post: postEdit, processing: editing, reset: resetEdit } = useForm({ record_id: '', name: '', code: '', grade: '', parameters: '', steps: '', from_unit: '', to_unit: '', factor: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route(config.createRoute), { onSuccess: () => reset() });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        postEdit(route(config.updateRoute));
    };

    const startEdit = (row: RecordItem) => {
        setEditingId(row.id);
        setEditData({
            record_id: String(row.id),
            name: stringValue(row.name),
            code: stringValue(row.code),
            grade: stringValue(row.grade),
            parameters: stringValue(row.parameters),
            steps: Array.isArray(row.steps) ? row.steps.join('\n') : stringValue(row.steps),
            from_unit: stringValue(row.from_unit),
            to_unit: stringValue(row.to_unit),
            factor: stringValue(row.factor),
        });
    };

    const archive = (recordId: number) => {
        router.post(route(config.archiveRoute), { record_id: recordId });
        if (editingId === recordId) {
            setEditingId(null);
            resetEdit();
        }
    };

    const fields = master === 'quality-profiles'
        ? <><Field label={t('Name')} value={data.name} onChange={(value) => setData('name', value)} required /><Field label={t('Code')} value={data.code} onChange={(value) => setData('code', value)} /><Field label={t('Grade')} value={data.grade} onChange={(value) => setData('grade', value)} /><Field label={t('Parameters')} value={data.parameters} onChange={(value) => setData('parameters', value)} /></>
        : master === 'route-recipes'
            ? <><Field label={t('Name')} value={data.name} onChange={(value) => setData('name', value)} required /><Field label={t('Code')} value={data.code} onChange={(value) => setData('code', value)} /><Field label={t('Steps')} value={data.steps} onChange={(value) => setData('steps', value)} placeholder={t('One process step per line')} /></>
            : <><Field label={t('From Unit')} value={data.from_unit} onChange={(value) => setData('from_unit', value)} required /><Field label={t('To Unit')} value={data.to_unit} onChange={(value) => setData('to_unit', value)} required /><Field label={t('Factor')} value={data.factor} onChange={(value) => setData('factor', value)} type="number" required /></>;

    const columns = master === 'quality-profiles'
        ? [{ key: 'name', header: t('Name') }, { key: 'code', header: t('Code'), render: optional }, { key: 'grade', header: t('Grade'), render: optional }, { key: 'parameters', header: t('Parameters'), render: optional }]
        : master === 'route-recipes'
            ? [{ key: 'name', header: t('Name') }, { key: 'code', header: t('Code'), render: optional }, { key: 'steps', header: t('Steps'), render: (value: string[] | null) => value?.join(' -> ') || '-' }]
            : [{ key: 'from_unit', header: t('From Unit') }, { key: 'to_unit', header: t('To Unit') }, { key: 'factor', header: t('Factor') }];

    const editFields = master === 'quality-profiles'
        ? <><Field label={t('Name')} value={editData.name} onChange={(value) => setEditData('name', value)} required /><Field label={t('Code')} value={editData.code} onChange={(value) => setEditData('code', value)} /><Field label={t('Grade')} value={editData.grade} onChange={(value) => setEditData('grade', value)} /><Field label={t('Parameters')} value={editData.parameters} onChange={(value) => setEditData('parameters', value)} /></>
        : master === 'route-recipes'
            ? <><Field label={t('Name')} value={editData.name} onChange={(value) => setEditData('name', value)} required /><Field label={t('Code')} value={editData.code} onChange={(value) => setEditData('code', value)} /><Field label={t('Steps')} value={editData.steps} onChange={(value) => setEditData('steps', value)} placeholder={t('One process step per line')} /></>
            : <><Field label={t('From Unit')} value={editData.from_unit} onChange={(value) => setEditData('from_unit', value)} required /><Field label={t('To Unit')} value={editData.to_unit} onChange={(value) => setEditData('to_unit', value)} required /><Field label={t('Factor')} value={editData.factor} onChange={(value) => setEditData('factor', value)} type="number" required /></>;

    const columnsWithActions = [...columns, {
        key: 'actions',
        header: t('Actions'),
        render: (_value: unknown, row: RecordItem) => (
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
    }];

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t(config.title) }]} pageTitle={t(config.title)}>
            <Head title={t(config.title)} />
            <div className="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
                <Card>
                    <CardContent className="p-5 space-y-6">
                        <div className="mb-5 flex items-center gap-2"><Factory className="h-5 w-5 text-violet-600" /><h2 className="font-semibold">{t(`New ${config.title.slice(0, -1)}`)}</h2></div>
                        <form onSubmit={submit} className="space-y-4">{fields}<Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create')}</Button></form>
                        {editingId !== null ? (
                            <>
                                <div className="border-t border-border pt-5" />
                                <h2 className="font-semibold">{t(`Edit ${config.title.slice(0, -1)}`)}</h2>
                                <form onSubmit={submitEdit} className="space-y-4">
                                    {editFields}
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
                <Card><CardContent className="p-0"><DataTable data={records} columns={columnsWithActions} emptyState={<NoRecordsFound icon={Factory} title={t(`No ${config.title.toLowerCase()} found`)} description={t('Create the first record to begin textile setup.')} />} /></CardContent></Card>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ label, value, onChange, type = 'text', required = false, placeholder = '' }: { label: string; value: string; onChange: (value: string) => void; type?: string; required?: boolean; placeholder?: string }) {
    return <div><Label>{label}</Label><Input type={type} value={value} onChange={(event) => onChange(event.target.value)} required={required} placeholder={placeholder} /></div>;
}

function optional(value: string | null) {
    return value || '-';
}

function stringValue(value: string | number | string[] | null | undefined): string {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    return value == null ? '' : String(value);
}