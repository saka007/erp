import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Activity, ShieldCheck } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import NoRecordsFound from '@/components/no-records-found';
import { formatTextileLabel } from '@/components/textile/textile-form-options';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileKpiOverview } from '@/components/textile/textile-kpi-overview';
import { TextileSectionGrid } from '@/components/textile/textile-section-grid';

interface LoginHistoryRow {
    id: number;
    user_id: number;
    ip: string | null;
    date: string | null;
    details?: Record<string, unknown> | null;
    type: string | null;
    created_at: string;
}

interface AuditLogRow {
    id: number;
    event_type: string;
    payload?: Record<string, unknown> | null;
    creator_id: number | null;
    created_at: string;
}

export default function Index({ loginHistory, auditLogs }: { loginHistory: LoginHistoryRow[]; auditLogs: AuditLogRow[] }) {
    const { t } = useTranslation();

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Logs') }]} pageTitle={t('Textile Logs')}>
            <Head title={t('Textile Logs')} />

            <TextileKpiOverview
                title={t('Log Overview')}
                className="mb-6"
                items={[
                    { label: t('Login Events'), value: loginHistory.length, hint: t('Tenant-scoped login history') },
                    { label: t('Audit Events'), value: auditLogs.length, hint: t('Workflow and cost audit trail') },
                ]}
            />

            <TextileSectionGrid className="xl:grid-cols-2">
                <TextileDataTableCard
                    data={loginHistory}
                    columns={[
                        { key: 'id', header: t('ID') },
                        { key: 'user_id', header: t('User ID') },
                        { key: 'ip', header: t('IP'), render: optional },
                        { key: 'type', header: t('Type'), render: optional },
                        { key: 'date', header: t('Date'), render: optional },
                    ]}
                    emptyState={<NoRecordsFound icon={Activity} title={t('No login history found')} description={t('Recent login history for the textile tenant will appear here.')} />}
                />

                <TextileDataTableCard
                    data={auditLogs}
                    columns={[
                        { key: 'id', header: t('ID') },
                        { key: 'event_type', header: t('Event'), render: formatTextileLabel },
                        { key: 'creator_id', header: t('Actor') , render: optionalNumber },
                        { key: 'created_at', header: t('Created At'), render: optional },
                        { key: 'payload', header: t('Context'), render: (value: AuditLogRow['payload']) => formatTextileLabel(String(value?.document_number ?? value?.action ?? value?.entity_id ?? '')) },
                    ]}
                    emptyState={<NoRecordsFound icon={ShieldCheck} title={t('No textile audit logs found')} description={t('Workflow and costing audit logs will appear here.')} />}
                />
            </TextileSectionGrid>
        </AuthenticatedLayout>
    );
}

function optional(value: string | number | null) {
    return value === null || value === undefined || value === '' ? '-' : String(value);
}

function optionalNumber(value: number | null) {
    return value === null || value === undefined ? '-' : String(value);
}