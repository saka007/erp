import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BarChart3 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import NoRecordsFound from '@/components/no-records-found';

interface AggregateRow {
    document_type?: string;
    status?: string;
    total: number;
}

interface WorkflowDocument {
    id: number;
    document_type: string;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string;
    unit?: string | null;
    status: string;
    metadata?: {
        revenue_value?: number;
        total_cost?: number;
        margin_value?: number;
        margin_percent?: number;
    } | null;
    updated_at: string;
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
    costingSummary,
    byType,
    byStatus,
    recentDocuments,
    recentMargins,
}: {
    costingSummary: CostingSummary;
    byType: AggregateRow[];
    byStatus: AggregateRow[];
    recentDocuments: WorkflowDocument[];
    recentMargins: WorkflowDocument[];
}) {
    const { t } = useTranslation();

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Dashboards') }]} pageTitle={t('Textile Dashboards and Reports')}>
            <Head title={t('Textile Dashboards and Reports')} />

            <div className="grid gap-6 xl:grid-cols-4">
                <Metric title={t('Total Revenue')} value={String(costingSummary.total_revenue)} />
                <Metric title={t('Total Cost')} value={String(costingSummary.total_cost)} />
                <Metric title={t('Total Margin')} value={String(costingSummary.total_margin)} />
                <Metric title={t('Margin %')} value={String(costingSummary.margin_percent)} />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={byType}
                            columns={[
                                { key: 'document_type', header: t('Workflow Type') },
                                { key: 'total', header: t('Count') },
                            ]}
                            emptyState={<NoRecordsFound icon={BarChart3} title={t('No workflow data by type')} description={t('Workflow type counts will appear as operations are posted.')} />}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={byStatus}
                            columns={[
                                { key: 'status', header: t('Status') },
                                { key: 'total', header: t('Count') },
                            ]}
                            emptyState={<NoRecordsFound icon={BarChart3} title={t('No workflow data by status')} description={t('Status aggregates will appear as operations are posted.')} />}
                        />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={recentDocuments}
                            columns={[
                                { key: 'id', header: t('ID') },
                                { key: 'document_type', header: t('Type') },
                                { key: 'document_number', header: t('Number') },
                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                { key: 'status', header: t('Status') },
                            ]}
                            emptyState={<NoRecordsFound icon={BarChart3} title={t('No recent documents')} description={t('Recently posted workflow documents will appear here.')} />}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <DataTable
                            data={recentMargins}
                            columns={[
                                { key: 'id', header: t('ID') },
                                { key: 'document_number', header: t('Snapshot Number') },
                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                { key: 'metadata_revenue', header: t('Revenue'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.revenue_value ?? '-') },
                                { key: 'metadata_cost', header: t('Cost'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.total_cost ?? '-') },
                                { key: 'metadata_margin', header: t('Margin %'), render: (_value: unknown, row: WorkflowDocument) => String(row.metadata?.margin_percent ?? '-') },
                            ]}
                            emptyState={<NoRecordsFound icon={BarChart3} title={t('No margin snapshots')} description={t('Costing margin snapshots will appear here after finalization.')} />}
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

function optional(value: string | null) {
    return value || '-';
}
