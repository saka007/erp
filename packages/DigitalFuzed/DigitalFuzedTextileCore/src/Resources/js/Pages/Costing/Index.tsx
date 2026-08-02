import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Calculator, Plus, CheckCircle2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NoRecordsFound from '@/components/no-records-found';

interface WorkflowDocument {
    id: number;
    document_number: string;
    document_type: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    metadata?: {
        material_cost?: number;
        conversion_cost?: number;
        overhead_cost?: number;
        variance_value?: number;
        revenue_value?: number;
        total_cost?: number;
        margin_value?: number;
        margin_percent?: number;
        unit_cost?: number;
    } | null;
}

interface CostingSummary {
    entries_count: number;
    snapshots_count: number;
    total_revenue: number;
    total_cost: number;
    total_margin: number;
    margin_percent: number;
}

export default function Index({
    costingEntries,
    marginSnapshots,
    costingSummary,
    eligibleSources,
}: {
    costingEntries: WorkflowDocument[];
    marginSnapshots: WorkflowDocument[];
    costingSummary: CostingSummary;
    eligibleSources: WorkflowDocument[];
}) {
    const { t } = useTranslation();

    const entryForm = useForm({
        source_document_id: '',
        party_name: '',
        lot_reference: '',
        quantity: '',
        unit: 'mtr',
        material_cost: '',
        conversion_cost: '',
        overhead_cost: '',
        variance_value: '',
        revenue_value: '',
        notes: '',
    });

    const finalizeForm = useForm({ costing_entry_id: '' });

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Costing') }]} pageTitle={t('Textile Costing and Margin')}>
            <Head title={t('Textile Costing and Margin')} />

            <div className="grid gap-6 xl:grid-cols-4">
                <Metric title={t('Cost Entries')} value={String(costingSummary.entries_count)} />
                <Metric title={t('Margin Snapshots')} value={String(costingSummary.snapshots_count)} />
                <Metric title={t('Total Revenue')} value={String(costingSummary.total_revenue)} />
                <Metric title={t('Total Margin %')} value={String(costingSummary.margin_percent)} />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <Calculator className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Capture Costing Entry')}</h2>
                        </div>

                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                entryForm.post(route('textile.costing.entries.store'), {
                                    onSuccess: () => entryForm.reset('source_document_id', 'party_name', 'lot_reference', 'quantity', 'material_cost', 'conversion_cost', 'overhead_cost', 'variance_value', 'revenue_value', 'notes'),
                                });
                            }}
                        >
                            <SelectField label={t('Source Document ID')} value={entryForm.data.source_document_id} onChange={(value) => entryForm.setData('source_document_id', value)} options={eligibleSources.map((row) => String(row.id))} includeEmpty emptyLabel={t('Select approved/released source')} required />
                            <Field label={t('Party')} value={entryForm.data.party_name} onChange={(value) => entryForm.setData('party_name', value)} />
                            <Field label={t('Lot Reference')} value={entryForm.data.lot_reference} onChange={(value) => entryForm.setData('lot_reference', value)} />
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Quantity')} type="number" value={entryForm.data.quantity} onChange={(value) => entryForm.setData('quantity', value)} />
                                <Field label={t('Unit')} value={entryForm.data.unit} onChange={(value) => entryForm.setData('unit', value)} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Material Cost')} type="number" value={entryForm.data.material_cost} onChange={(value) => entryForm.setData('material_cost', value)} required />
                                <Field label={t('Conversion Cost')} type="number" value={entryForm.data.conversion_cost} onChange={(value) => entryForm.setData('conversion_cost', value)} required />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('Overhead Cost')} type="number" value={entryForm.data.overhead_cost} onChange={(value) => entryForm.setData('overhead_cost', value)} required />
                                <Field label={t('Variance')} type="number" value={entryForm.data.variance_value} onChange={(value) => entryForm.setData('variance_value', value)} />
                            </div>
                            <Field label={t('Revenue Value')} type="number" value={entryForm.data.revenue_value} onChange={(value) => entryForm.setData('revenue_value', value)} required />
                            <Field label={t('Notes')} value={entryForm.data.notes} onChange={(value) => entryForm.setData('notes', value)} />

                            <Button type="submit" disabled={entryForm.processing} className="w-full">
                                <Plus className="mr-2 h-4 w-4" />{t('Create Cost Entry')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex items-center gap-2">
                            <CheckCircle2 className="h-5 w-5 text-violet-600" />
                            <h2 className="font-semibold">{t('Finalize and Post Margin')}</h2>
                        </div>

                        <form
                            className="grid grid-cols-[1fr_auto] gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                finalizeForm.post(route('textile.costing.entries.finalize'), {
                                    onSuccess: () => finalizeForm.reset('costing_entry_id'),
                                });
                            }}
                        >
                            <SelectField label={t('Draft Cost Entry ID')} value={finalizeForm.data.costing_entry_id} onChange={(value) => finalizeForm.setData('costing_entry_id', value)} options={costingEntries.filter((row) => row.status === 'draft').map((row) => String(row.id))} includeEmpty emptyLabel={t('Select draft entry')} required />
                            <Button type="submit" variant="outline" disabled={finalizeForm.processing} className="self-end">
                                <CheckCircle2 className="mr-2 h-4 w-4" />{t('Finalize')}
                            </Button>
                        </form>

                        <DataTable
                            data={eligibleSources}
                            columns={[
                                { key: 'id', header: t('ID') },
                                { key: 'document_type', header: t('Type') },
                                { key: 'document_number', header: t('Number') },
                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                { key: 'status', header: t('Status') },
                            ]}
                            emptyState={<NoRecordsFound icon={Calculator} title={t('No eligible source documents')} description={t('Approved or released textile workflow documents will be available for costing.')} />}
                        />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={costingEntries}
                            columns={[
                                { key: 'id', header: t('ID') },
                                { key: 'document_number', header: t('Number') },
                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                { key: 'quantity', header: t('Qty') },
                                { key: 'status', header: t('Status') },
                                { key: 'metadata', header: t('Total Cost'), render: (value: WorkflowDocument['metadata']) => String(value?.total_cost ?? '-') },
                            ]}
                            emptyState={<NoRecordsFound icon={Calculator} title={t('No costing entries found')} description={t('Capture a costing entry to start margin tracking.')} />}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={marginSnapshots}
                            columns={[
                                { key: 'id', header: t('ID') },
                                { key: 'document_number', header: t('Number') },
                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                { key: 'status', header: t('Status') },
                                { key: 'metadata', header: t('Margin'), render: (value: WorkflowDocument['metadata']) => String(value?.margin_value ?? '-') },
                                { key: 'metadata_percent', header: t('Margin %'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.margin_percent ?? '-') },
                            ]}
                            emptyState={<NoRecordsFound icon={CheckCircle2} title={t('No margin snapshots found')} description={t('Finalize a costing entry to generate margin snapshot records.')} />}
                        />
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function Metric({ title, value }: { title: string; value: string }) {
    return (
        <Card>
            <CardContent className="p-5">
                <p className="text-sm text-muted-foreground">{title}</p>
                <p className="mt-2 text-2xl font-semibold">{value}</p>
            </CardContent>
        </Card>
    );
}

function Field({ label, value, onChange, type = 'text', required = false }: { label: string; value: string; onChange: (value: string) => void; type?: string; required?: boolean }) {
    return (
        <div>
            <Label>{label}</Label>
            <Input type={type} value={value} required={required} onChange={(event) => onChange(event.target.value)} />
        </div>
    );
}

function SelectField({ label, value, onChange, options, required = false, includeEmpty = false, emptyLabel = 'Select' }: { label: string; value: string; onChange: (value: string) => void; options: string[]; required?: boolean; includeEmpty?: boolean; emptyLabel?: string }) {
    return (
        <div>
            <Label>{label}</Label>
            <select className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={value} required={required} onChange={(event) => onChange(event.target.value)}>
                {includeEmpty && <option value="">{emptyLabel}</option>}
                {options.map((option) => (
                    <option key={option} value={option}>{option}</option>
                ))}
            </select>
        </div>
    );
}

function optional(value: string | null) {
    return value || '-';
}
