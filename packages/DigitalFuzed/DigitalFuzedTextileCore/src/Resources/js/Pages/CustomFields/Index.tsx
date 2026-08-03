import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Pencil, ArchiveX, SlidersHorizontal } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import NoRecordsFound from '@/components/no-records-found';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface CustomFieldRecord {
    id: number;
    module_key: string;
    sub_module_key?: string | null;
    field_key: string;
    label: string;
    field_type: string;
    options?: string[] | null;
    is_required: boolean;
    sort_order: number;
    help_text?: string | null;
}

const moduleOptions = [
    { value: 'core_erp', label: 'Core ERP' },
    { value: 'textile_procurement', label: 'Textile Procurement' },
    { value: 'textile_inventory', label: 'Textile Inventory' },
    { value: 'textile_sales', label: 'Textile Sales' },
    { value: 'textile_manufacturing', label: 'Textile Manufacturing' },
    { value: 'textile_quality', label: 'Textile Quality' },
    { value: 'textile_processing', label: 'Textile Processing' },
    { value: 'textile_costing', label: 'Textile Costing' },
];

const requiredOptions = [
    { value: '0', label: 'No' },
    { value: '1', label: 'Yes' },
];

export default function Index({ customFields, fieldTypes }: { customFields: CustomFieldRecord[]; fieldTypes: string[] }) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const { data, setData, post, processing, reset } = useForm({
        module_key: 'core_erp',
        sub_module_key: '',
        field_key: '',
        label: '',
        field_type: fieldTypes[0] ?? 'text',
        options_csv: '',
        is_required: '0',
        sort_order: '0',
        help_text: '',
    });

    const {
        data: editData,
        setData: setEditData,
        post: postEdit,
        processing: editing,
        reset: resetEdit,
    } = useForm({
        custom_field_id: '',
        module_key: 'core_erp',
        sub_module_key: '',
        field_key: '',
        label: '',
        field_type: fieldTypes[0] ?? 'text',
        options_csv: '',
        is_required: '0',
        sort_order: '0',
        help_text: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('textile.custom-fields.store'), {
            onSuccess: () => reset('field_key', 'label', 'options_csv', 'help_text'),
        });
    };

    const submitEdit = (event: React.FormEvent) => {
        event.preventDefault();
        postEdit(route('textile.custom-fields.update'));
    };

    const startEdit = (record: CustomFieldRecord) => {
        setEditingId(record.id);
        setEditData({
            custom_field_id: String(record.id),
            module_key: record.module_key,
            sub_module_key: record.sub_module_key ?? '',
            field_key: record.field_key,
            label: record.label,
            field_type: record.field_type,
            options_csv: (record.options ?? []).join(', '),
            is_required: record.is_required ? '1' : '0',
            sort_order: String(record.sort_order ?? 0),
            help_text: record.help_text ?? '',
        });
    };

    const archive = (customFieldId: number) => {
        router.post(route('textile.custom-fields.archive'), { custom_field_id: customFieldId });
        if (editingId === customFieldId) {
            setEditingId(null);
            resetEdit();
        }
    };

    const fieldTypeOptions = fieldTypes.map((value) => ({ value, label: value }));

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('Custom Fields') }]} pageTitle={t('Textile Custom Fields')}>
            <Head title={t('Textile Custom Fields')} />
            <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                <TextileFormCard title={t('New Custom Field')} icon={SlidersHorizontal} contentClassName="p-5 space-y-6">
                    <form onSubmit={submit} className="space-y-4">
                        <SelectField label={t('Module')} value={data.module_key} onChange={(value) => setData('module_key', value)} options={moduleOptions} required />
                        <Field label={t('Sub Module')} value={data.sub_module_key} onChange={(value) => setData('sub_module_key', value)} placeholder={t('optional e.g. sales_order')} />
                        <Field label={t('Field Key')} value={data.field_key} onChange={(value) => setData('field_key', value)} placeholder={t('e.g. customer_segment')} required />
                        <Field label={t('Label')} value={data.label} onChange={(value) => setData('label', value)} placeholder={t('e.g. Customer Segment')} required />
                        <SelectField label={t('Field Type')} value={data.field_type} onChange={(value) => setData('field_type', value)} options={fieldTypeOptions} required />
                        <Field label={t('Select Options (comma separated)')} value={data.options_csv} onChange={(value) => setData('options_csv', value)} placeholder={t('option_a, option_b')} />
                        <SelectField label={t('Required')} value={data.is_required} onChange={(value) => setData('is_required', value)} options={requiredOptions} required />
                        <Field label={t('Sort Order')} type="number" value={data.sort_order} onChange={(value) => setData('sort_order', value)} />
                        <Field label={t('Help Text')} value={data.help_text} onChange={(value) => setData('help_text', value)} />
                        <Button type="submit" disabled={processing} className="w-full"><Plus className="mr-2 h-4 w-4" />{t('Create Custom Field')}</Button>
                    </form>

                    {editingId !== null ? (
                        <>
                            <div className="border-t border-border pt-5" />
                            <h2 className="font-semibold">{t('Edit Custom Field')}</h2>
                            <form onSubmit={submitEdit} className="space-y-4">
                                <SelectField label={t('Module')} value={editData.module_key} onChange={(value) => setEditData('module_key', value)} options={moduleOptions} required />
                                <Field label={t('Sub Module')} value={editData.sub_module_key} onChange={(value) => setEditData('sub_module_key', value)} placeholder={t('optional e.g. sales_order')} />
                                <Field label={t('Field Key')} value={editData.field_key} onChange={(value) => setEditData('field_key', value)} required />
                                <Field label={t('Label')} value={editData.label} onChange={(value) => setEditData('label', value)} required />
                                <SelectField label={t('Field Type')} value={editData.field_type} onChange={(value) => setEditData('field_type', value)} options={fieldTypeOptions} required />
                                <Field label={t('Select Options (comma separated)')} value={editData.options_csv} onChange={(value) => setEditData('options_csv', value)} />
                                <SelectField label={t('Required')} value={editData.is_required} onChange={(value) => setEditData('is_required', value)} options={requiredOptions} required />
                                <Field label={t('Sort Order')} type="number" value={editData.sort_order} onChange={(value) => setEditData('sort_order', value)} />
                                <Field label={t('Help Text')} value={editData.help_text} onChange={(value) => setEditData('help_text', value)} />
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
                    data={customFields}
                    columns={[
                        { key: 'module_key', header: t('Module') },
                        { key: 'sub_module_key', header: t('Sub Module'), render: optional },
                        { key: 'field_key', header: t('Field Key') },
                        { key: 'label', header: t('Label') },
                        { key: 'field_type', header: t('Type') },
                        { key: 'options', header: t('Options'), render: renderOptions },
                        { key: 'is_required', header: t('Required'), render: booleanLabel },
                        { key: 'sort_order', header: t('Order') },
                        { key: 'help_text', header: t('Help Text'), render: optional },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: CustomFieldRecord) => (
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
                    emptyState={<NoRecordsFound icon={SlidersHorizontal} title={t('No custom fields found')} description={t('Create tenant-specific custom fields for core and textile workflows.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}

function renderOptions(value: string[] | null | undefined) {
    if (!value || value.length === 0) {
        return '-';
    }

    return value.join(', ');
}

function booleanLabel(value: boolean | null | undefined) {
    return value ? 'yes' : 'no';
}
