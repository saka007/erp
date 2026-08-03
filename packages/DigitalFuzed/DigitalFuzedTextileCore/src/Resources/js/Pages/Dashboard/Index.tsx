import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BarChart3, Coins, Factory, FileBarChart2, Loader, LogIn, ShieldAlert, TrendingUp, Wallet } from 'lucide-react';
import {
    ResponsiveContainer,
    AreaChart,
    Area,
    LineChart,
    Line,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
} from 'recharts';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import NoRecordsFound from '@/components/no-records-found';
import { formatTextileLabel } from '@/components/textile/textile-form-options';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileSectionGrid } from '@/components/textile/textile-section-grid';
import {
    PurchasePanel,
    InventoryPanel,
    SalesPanel,
    FinancePanel,
    MaintenancePanel,
    HrPanel,
    KpiCard,
    ChartCard,
    ChartEmpty,
    CHART_COLORS,
    type DomainData,
    type KpiItem,
} from './panels';

interface TrendPoint {
    date: string;
    quantity?: number;
    revenue?: number;
    cost?: number;
    margin?: number;
}

interface NamedValue {
    name: string;
    value: number;
}

interface EfficiencyRow {
    name: string;
    efficiency: number;
}

interface PowerRow {
    period: string;
    units: number;
    cost: number;
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

interface LoginHistoryRow {
    id: number;
    user_id: number;
    ip: string | null;
    date: string | null;
    type: string | null;
}

interface AuditLogRow {
    id: number;
    event_type: string;
    creator_id: number | null;
}

const DASHBOARD_VIEWS = ['overview', 'purchase', 'inventory', 'sales', 'finance', 'maintenance', 'hr'];

export default function Index({
    kpis,
    productionTrend,
    dispatchTrend,
    financialTrend,
    machineEfficiency,
    powerTrend,
    statusDistribution,
    typeDistribution,
    recentDocuments,
    recentMargins,
    loginHistoryCount,
    auditLogCount,
    recentLoginHistory,
    recentAuditLogs,
    purchase,
    inventory,
    sales,
    finance,
    maintenance,
    hr,
}: {
    kpis: KpiItem[];
    productionTrend: TrendPoint[];
    dispatchTrend: TrendPoint[];
    financialTrend: TrendPoint[];
    machineEfficiency: EfficiencyRow[];
    powerTrend: PowerRow[];
    statusDistribution: NamedValue[];
    typeDistribution: NamedValue[];
    recentDocuments: WorkflowDocument[];
    recentMargins: WorkflowDocument[];
    loginHistoryCount: number;
    auditLogCount: number;
    recentLoginHistory: LoginHistoryRow[];
    recentAuditLogs: AuditLogRow[];
    purchase: DomainData;
    inventory: DomainData;
    sales: DomainData;
    finance: DomainData;
    maintenance: DomainData;
    hr: DomainData;
}) {
    const { t } = useTranslation();

    const viewParam = new URLSearchParams(window.location.search).get('view');
    const activeView = viewParam && DASHBOARD_VIEWS.includes(viewParam) ? viewParam : 'overview';

    const productionChartData = productionTrend.map((point, index) => ({
        ...point,
        dispatch: dispatchTrend[index]?.quantity ?? 0,
    }));

    const switchView = (view: string) => {
        router.get(route('textile.dashboard.index'), { view }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Dashboard') }]} pageTitle={t('Textile Dashboard')}>
            <Head title={t('Textile Dashboard')} />

            <Tabs value={activeView} onValueChange={switchView}>
                <TabsList className="mb-6 flex-wrap">
                    <TabsTrigger value="overview">{t('Overview')}</TabsTrigger>
                    <TabsTrigger value="purchase">{t('Purchase')}</TabsTrigger>
                    <TabsTrigger value="inventory">{t('Inventory')}</TabsTrigger>
                    <TabsTrigger value="sales">{t('Sales')}</TabsTrigger>
                    <TabsTrigger value="finance">{t('Finance')}</TabsTrigger>
                    <TabsTrigger value="maintenance">{t('Maintenance')}</TabsTrigger>
                    <TabsTrigger value="hr">{t('HR')}</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="mt-0">
            <div className="space-y-6">
                {/* KPI row */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <KpiCard label={t('Total Revenue')} value={kpis[0]?.value ?? 0} hint={kpis[0]?.hint} icon={Wallet} tone="emerald" />
                    <KpiCard label={t('Total Cost')} value={kpis[1]?.value ?? 0} hint={kpis[1]?.hint} icon={Coins} tone="amber" />
                    <KpiCard label={t('Total Margin')} value={kpis[2]?.value ?? 0} hint={kpis[2]?.hint} icon={TrendingUp} tone="indigo" />
                    <KpiCard label={t('Margin %')} value={kpis[3]?.value ?? 0} hint={kpis[3]?.hint} icon={BarChart3} tone="sky" />
                    <KpiCard label={t('Workflow Documents')} value={kpis[4]?.value ?? 0} hint={kpis[4]?.hint} icon={FileBarChart2} tone="violet" />
                    <KpiCard label={t('In Progress')} value={kpis[5]?.value ?? 0} hint={kpis[5]?.hint} icon={Loader} tone="rose" />
                </div>

                {/* Production + financial trends */}
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <ChartCard title={t('Production Trend')} subtitle={t('Daily output vs dispatch (mtr) — last 14 days')}>
                        {productionChartData.some((point) => (point.quantity ?? 0) > 0 || point.dispatch > 0) ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={productionChartData} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="productionFill" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#6366f1" stopOpacity={0.35} />
                                            <stop offset="95%" stopColor="#6366f1" stopOpacity={0.02} />
                                        </linearGradient>
                                        <linearGradient id="dispatchFill" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#10b981" stopOpacity={0.3} />
                                            <stop offset="95%" stopColor="#10b981" stopOpacity={0.02} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <Tooltip />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Area type="monotone" dataKey="quantity" name={t('Production')} stroke="#6366f1" strokeWidth={2} fill="url(#productionFill)" />
                                    <Area type="monotone" dataKey="dispatch" name={t('Dispatch')} stroke="#10b981" strokeWidth={2} fill="url(#dispatchFill)" />
                                </AreaChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={Factory} title={t('No production data')} description={t('Daily production output will be charted here.')} />
                        )}
                    </ChartCard>

                    <ChartCard title={t('Revenue vs Cost')} subtitle={t('From margin snapshots — last 14 days')}>
                        {financialTrend.some((point) => (point.revenue ?? 0) > 0) ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={financialTrend} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <Tooltip />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Line type="monotone" dataKey="revenue" name={t('Revenue')} stroke="#10b981" strokeWidth={2} dot={false} />
                                    <Line type="monotone" dataKey="cost" name={t('Cost')} stroke="#f59e0b" strokeWidth={2} dot={false} />
                                    <Line type="monotone" dataKey="margin" name={t('Margin')} stroke="#6366f1" strokeWidth={2} dot={false} />
                                </LineChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={Wallet} title={t('No financial data')} description={t('Finalised margin snapshots will be charted here.')} />
                        )}
                    </ChartCard>
                </div>

                {/* Distributions + operational charts */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <ChartCard title={t('Document Mix')} subtitle={t('Workflow documents by type')}>
                        {typeDistribution.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie data={typeDistribution} dataKey="value" nameKey="name" innerRadius={45} outerRadius={75} paddingAngle={2}>
                                        {typeDistribution.map((entry, index) => (
                                            <Cell key={entry.name} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                    <Legend wrapperStyle={{ fontSize: 11 }} />
                                </PieChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={BarChart3} title={t('No documents yet')} description={t('Posted workflow documents will be charted here.')} />
                        )}
                    </ChartCard>

                    <ChartCard title={t('Status Distribution')} subtitle={t('Documents by workflow status')}>
                        {statusDistribution.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={statusDistribution} margin={{ top: 5, right: 5, left: -25, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <Tooltip />
                                    <Bar dataKey="value" name={t('Documents')} fill="#8b5cf6" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={BarChart3} title={t('No status data')} description={t('Status aggregates will be charted here.')} />
                        )}
                    </ChartCard>

                    <ChartCard title={t('Machine Efficiency')} subtitle={t('Latest efficiency % per loom')}>
                        {machineEfficiency.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={machineEfficiency} layout="vertical" margin={{ top: 5, right: 15, left: 10, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" horizontal={false} />
                                    <XAxis type="number" domain={[0, 100]} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <YAxis type="category" dataKey="name" width={70} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <Tooltip />
                                    <Bar dataKey="efficiency" name={t('Efficiency %')} fill="#06b6d4" radius={[0, 4, 4, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={Factory} title={t('No efficiency logs')} description={t('Loom efficiency logs will be charted here.')} />
                        )}
                    </ChartCard>

                    <ChartCard title={t('Power Consumption')} subtitle={t('Recent billing periods')}>
                        {powerTrend.length > 0 ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={powerTrend} margin={{ top: 5, right: 5, left: -20, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                    <XAxis dataKey="period" tick={{ fontSize: 10 }} tickLine={false} axisLine={false} />
                                    <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <Tooltip />
                                    <Legend wrapperStyle={{ fontSize: 11 }} />
                                    <Bar dataKey="units" name={t('Units')} fill="#f59e0b" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="cost" name={t('Cost')} fill="#ef4444" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={BarChart3} title={t('No power data')} description={t('Recorded power billing periods will be charted here.')} />
                        )}
                    </ChartCard>
                </div>

                {/* Activity tables */}
                <TextileSectionGrid className="xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <TextileDataTableCard
                            data={recentDocuments}
                            columns={[
                                { key: 'document_number', header: t('Document') },
                                { key: 'document_type', header: t('Type'), render: formatTextileLabel },
                                { key: 'party_name', header: t('Party'), render: optional },
                                { key: 'lot_reference', header: t('Lot'), render: optional },
                                { key: 'status', header: t('Status'), render: formatTextileLabel },
                            ]}
                            emptyState={<NoRecordsFound icon={BarChart3} title={t('No recent documents')} description={t('Recently posted workflow documents will appear here.')} />}
                        />
                    </div>
                    <div className="space-y-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <LogIn className="h-4 w-4 text-emerald-500" />
                                    {t('Login Events')} <span className="ml-auto text-muted-foreground">{loginHistoryCount}</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1.5">
                                {recentLoginHistory.length > 0 ? recentLoginHistory.map((row) => (
                                    <div key={row.id} className="flex items-center justify-between rounded-md bg-muted/40 px-2 py-1.5 text-xs">
                                        <span className="font-medium">{row.ip ?? '-'}</span>
                                        <span className="text-muted-foreground">{row.type ?? '-'} · {row.date ?? '-'}</span>
                                    </div>
                                )) : (
                                    <p className="text-xs text-muted-foreground">{t('No login history yet.')}</p>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <ShieldAlert className="h-4 w-4 text-rose-500" />
                                    {t('Audit Events')} <span className="ml-auto text-muted-foreground">{auditLogCount}</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1.5">
                                {recentAuditLogs.length > 0 ? recentAuditLogs.map((row) => (
                                    <div key={row.id} className="rounded-md bg-muted/40 px-2 py-1.5 text-xs">
                                        <span className="font-medium">{row.event_type}</span>
                                    </div>
                                )) : (
                                    <p className="text-xs text-muted-foreground">{t('No audit events yet.')}</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </TextileSectionGrid>
            </div>
                </TabsContent>
                <TabsContent value="purchase" className="mt-0"><PurchasePanel data={purchase} /></TabsContent>
                <TabsContent value="inventory" className="mt-0"><InventoryPanel data={inventory} /></TabsContent>
                <TabsContent value="sales" className="mt-0"><SalesPanel data={sales} /></TabsContent>
                <TabsContent value="finance" className="mt-0"><FinancePanel data={finance} /></TabsContent>
                <TabsContent value="maintenance" className="mt-0"><MaintenancePanel data={maintenance} /></TabsContent>
                <TabsContent value="hr" className="mt-0"><HrPanel data={hr} /></TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}

function optional(value: string | null) {
    return value || '-';
}

