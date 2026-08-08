import { useTranslation } from 'react-i18next';
import { BarChart3, Coins, Factory, FileBarChart2, Loader, Wallet, TrendingUp, ShoppingCart, ShoppingBag, Warehouse, Users, Wrench, HeartPulse } from 'lucide-react';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatTextileLabel } from '@/components/textile/textile-form-options';

export const CHART_COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#ec4899'];

export interface KpiItem {
    label: string;
    value: number | string;
    hint?: string;
}

interface TrendPoint {
    date: string;
    quantity?: number;
    revenue?: number;
    cost?: number;
    margin?: number;
    breakdowns?: number;
}

interface MovementPoint {
    date: string;
    receipt?: number;
    issue?: number;
    transfer?: number;
    adjustment?: number;
}

interface NamedValue {
    name: string;
    value: number;
}

export interface DomainData {
    kpis: KpiItem[];
    trend?: TrendPoint[];
    status?: NamedValue[];
    types?: NamedValue[];
    movementTrend?: MovementPoint[];
    lotStatus?: NamedValue[];
    costBreakdown?: NamedValue[];
    financialTrend?: TrendPoint[];
    powerTrend?: Array<{ period: string; units: number; cost: number }>;
    downtimeByMachine?: NamedValue[];
    costTrend?: Array<{ period: string; cost: number }>;
    attendanceTrend?: Array<{ date: string; present: number }>;
    employeesByDepartment?: NamedValue[];
}

export function PurchasePanel({ data }: { data: DomainData }) {
    const { t } = useTranslation();
    return (
        <div className="space-y-4">
            <KpiRow items={data.kpis ?? []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <ChartCard title={t('Purchase Trend')} subtitle={t('Daily purchase quantity — last 14 days')}>
                    {hasValues(data.trend ?? [], 'quantity') ? (
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={data.trend} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="purchaseFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#f59e0b" stopOpacity={0.35} />
                                        <stop offset="95%" stopColor="#f59e0b" stopOpacity={0.02} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <Tooltip />
                                <Area type="monotone" dataKey="quantity" name={t('Quantity')} stroke="#f59e0b" strokeWidth={2} fill="url(#purchaseFill)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    ) : (
                        <ChartEmpty icon={ShoppingCart} title={t('No purchase data')} description={t('Purchase documents will be charted here.')} />
                    )}
                </ChartCard>
                <ChartCard title={t('Document Mix')} subtitle={t('Orders, requisitions and GRNs (Goods Received Notes)')}>
                    {(data.types ?? []).length > 0 ? (
                        <Donut data={data.types ?? []} />
                    ) : (
                        <ChartEmpty icon={ShoppingCart} title={t('No documents yet')} description={t('Purchase documents will appear here.')} />
                    )}
                </ChartCard>
            </div>
            <ChartCard title={t('Status Distribution')} subtitle={t('Purchase documents by status')}>
                {(data.status ?? []).length > 0 ? (
                    <VerticalBars data={data.status ?? []} color="#f59e0b" dataKey="value" name={t('Documents')} />
                ) : (
                    <ChartEmpty icon={BarChart3} title={t('No status data')} description={t('Status aggregates will be charted here.')} />
                )}
            </ChartCard>
        </div>
    );
}

export function InventoryPanel({ data }: { data: DomainData }) {
    const { t } = useTranslation();
    return (
        <div className="space-y-4">
            <KpiRow items={data.kpis ?? []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <ChartCard title={t('Stock Movement Trend')} subtitle={t('Daily movement quantity by type — last 14 days')}>
                        {hasValues(data.movementTrend ?? [], 'receipt') ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={data.movementTrend} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <Tooltip />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Bar dataKey="receipt" name={t('Receipts')} fill="#10b981" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="issue" name={t('Issues')} fill="#ef4444" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="transfer" name={t('Transfers')} fill="#6366f1" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="adjustment" name={t('Adjustments')} fill="#f59e0b" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={Warehouse} title={t('No movements')} description={t('Stock movements will be charted here.')} />
                        )}
                    </ChartCard>
                </div>
                <ChartCard title={t('Lot Status')} subtitle={t('Inventory lots by status')}>
                    {(data.lotStatus ?? []).length > 0 ? (
                        <Donut data={data.lotStatus ?? []} />
                    ) : (
                        <ChartEmpty icon={Warehouse} title={t('No lots')} description={t('Inventory lots will appear here.')} />
                    )}
                </ChartCard>
            </div>
        </div>
    );
}

export function SalesPanel({ data }: { data: DomainData }) {
    const { t } = useTranslation();
    return (
        <div className="space-y-4">
            <KpiRow items={data.kpis ?? []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <ChartCard title={t('Sales Trend')} subtitle={t('Daily order quantity — last 14 days')}>
                    {hasValues(data.trend ?? [], 'quantity') ? (
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={data.trend} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="salesFill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#10b981" stopOpacity={0.35} />
                                        <stop offset="95%" stopColor="#10b981" stopOpacity={0.02} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <Tooltip />
                                <Area type="monotone" dataKey="quantity" name={t('Quantity')} stroke="#10b981" strokeWidth={2} fill="url(#salesFill)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    ) : (
                        <ChartEmpty icon={ShoppingBag} title={t('No sales data')} description={t('Sales orders will be charted here.')} />
                    )}
                </ChartCard>
                <ChartCard title={t('Status Distribution')} subtitle={t('Sales orders by status')}>
                    {(data.status ?? []).length > 0 ? (
                        <VerticalBars data={data.status ?? []} color="#10b981" dataKey="value" name={t('Orders')} />
                    ) : (
                        <ChartEmpty icon={BarChart3} title={t('No status data')} description={t('Order status will be charted here.')} />
                    )}
                </ChartCard>
            </div>
        </div>
    );
}

export function FinancePanel({ data }: { data: DomainData }) {
    const { t } = useTranslation();
    return (
        <div className="space-y-4">
            <KpiRow items={data.kpis ?? []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <ChartCard title={t('Revenue vs Cost')} subtitle={t('From margin snapshots — last 14 days')}>
                        {hasValues(data.financialTrend ?? [], 'revenue') ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={data.financialTrend} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
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
                <ChartCard title={t('Cost Breakdown')} subtitle={t('Recorded operational cost totals')}>
                    {(data.costBreakdown ?? []).length > 0 ? (
                        <Donut data={data.costBreakdown ?? []} />
                    ) : (
                        <ChartEmpty icon={Coins} title={t('No cost records')} description={t('Power, chemical, labour, machine and maintenance costs will appear here.')} />
                    )}
                </ChartCard>
            </div>
            <ChartCard title={t('Power Consumption')} subtitle={t('Recent billing periods')}>
                {(data.powerTrend ?? []).length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data.powerTrend} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                            <XAxis dataKey="period" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                            <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                            <Tooltip />
                            <Legend wrapperStyle={{ fontSize: 12 }} />
                            <Bar dataKey="units" name={t('Units')} fill="#f59e0b" radius={[4, 4, 0, 0]} />
                            <Bar dataKey="cost" name={t('Cost')} fill="#ef4444" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                ) : (
                    <ChartEmpty icon={BarChart3} title={t('No power data')} description={t('Recorded power billing periods will be charted here.')} />
                )}
            </ChartCard>
        </div>
    );
}

export function MaintenancePanel({ data }: { data: DomainData }) {
    const { t } = useTranslation();
    return (
        <div className="space-y-4">
            <KpiRow items={data.kpis ?? []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <ChartCard title={t('Breakdown Trend')} subtitle={t('Daily breakdown events — last 14 days')}>
                    {hasValues(data.trend ?? [], 'breakdowns') ? (
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={data.trend} margin={{ top: 5, right: 10, left: -25, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <YAxis allowDecimals={false} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <Tooltip />
                                <Bar dataKey="breakdowns" name={t('Breakdowns')} fill="#ef4444" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    ) : (
                        <ChartEmpty icon={Wrench} title={t('No breakdowns')} description={t('Recorded breakdown events will be charted here.')} />
                    )}
                </ChartCard>
                <ChartCard title={t('Downtime by Machine')} subtitle={t('Hours per machine across events')}>
                    {(data.downtimeByMachine ?? []).length > 0 ? (
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={data.downtimeByMachine} layout="vertical" margin={{ top: 5, right: 15, left: 10, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" horizontal={false} />
                                <XAxis type="number" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <YAxis type="category" dataKey="name" width={80} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                <Tooltip />
                                <Bar dataKey="hours" name={t('Hours')} fill="#ef4444" radius={[0, 4, 4, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    ) : (
                        <ChartEmpty icon={Factory} title={t('No downtime data')} description={t('Machine downtime will be charted here.')} />
                    )}
                </ChartCard>
            </div>
            <ChartCard title={t('Maintenance Cost Trend')} subtitle={t('Recent maintenance cost entries')}>
                {(data.costTrend ?? []).length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data.costTrend} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                            <XAxis dataKey="period" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                            <YAxis tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                            <Tooltip />
                            <Bar dataKey="cost" name={t('Cost')} fill="#f59e0b" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                ) : (
                    <ChartEmpty icon={Wrench} title={t('No cost entries')} description={t('Maintenance cost records will be charted here.')} />
                )}
            </ChartCard>
        </div>
    );
}

export function HrPanel({ data }: { data: DomainData }) {
    const { t } = useTranslation();
    return (
        <div className="space-y-4">
            <KpiRow items={data.kpis ?? []} />
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <ChartCard title={t('Attendance Trend')} subtitle={t('Daily attendance records — last 14 days')}>
                        {hasValues(data.attendanceTrend ?? [], 'present') ? (
                            <ResponsiveContainer width="100%" height="100%">
                                <AreaChart data={data.attendanceTrend} margin={{ top: 5, right: 10, left: -25, bottom: 0 }}>
                                    <defs>
                                        <linearGradient id="attendanceFill" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="5%" stopColor="#8b5cf6" stopOpacity={0.35} />
                                            <stop offset="95%" stopColor="#8b5cf6" stopOpacity={0.02} />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                    <XAxis dataKey="date" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                                    <Tooltip />
                                    <Area type="monotone" dataKey="present" name={t('Present')} stroke="#8b5cf6" strokeWidth={2} fill="url(#attendanceFill)" />
                                </AreaChart>
                            </ResponsiveContainer>
                        ) : (
                            <ChartEmpty icon={Users} title={t('No attendance data')} description={t('Attendance records will be charted here.')} />
                        )}
                    </ChartCard>
                </div>
                <ChartCard title={t('Employees by Department')} subtitle={t('Headcount per department')}>
                    {(data.employeesByDepartment ?? []).length > 0 ? (
                        <Donut data={data.employeesByDepartment ?? []} />
                    ) : (
                        <ChartEmpty icon={HeartPulse} title={t('No employees')} description={t('Employee headcount will appear here.')} />
                    )}
                </ChartCard>
            </div>
        </div>
    );
}

export function KpiRow({ items }: { items: KpiItem[] }) {
    const tones = ['emerald', 'amber', 'indigo', 'sky', 'violet', 'rose'];
    const icons = [Wallet, Coins, TrendingUp, BarChart3, FileBarChart2, Loader];
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
            {items.map((item, index) => (
                <KpiCard key={item.label} label={item.label} value={item.value} hint={item.hint} icon={icons[index % icons.length]} tone={tones[index % tones.length]} />
            ))}
        </div>
    );
}

export function KpiCard({ label, value, hint, icon: Icon, tone }: { label: string; value: number | string; hint?: string; icon: React.ComponentType<{ className?: string }>; tone: string }) {
    const tones: Record<string, string> = {
        emerald: 'from-emerald-50 to-teal-100 border-emerald-200 text-emerald-700',
        amber: 'from-amber-50 to-yellow-100 border-amber-200 text-amber-700',
        indigo: 'from-indigo-50 to-blue-100 border-indigo-200 text-indigo-700',
        sky: 'from-sky-50 to-cyan-100 border-sky-200 text-sky-700',
        violet: 'from-violet-50 to-purple-100 border-violet-200 text-violet-700',
        rose: 'from-rose-50 to-pink-100 border-rose-200 text-rose-700',
    };
    return (
        <Card className={`bg-gradient-to-br ${tones[tone]} hover:shadow-md transition-shadow`}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-xs font-semibold uppercase tracking-wide opacity-80">{label}</CardTitle>
                <Icon className="h-4 w-4 opacity-70" />
            </CardHeader>
            <CardContent className="space-y-1">
                <p className="text-2xl font-bold">{value}</p>
                {hint ? <p className="text-xs opacity-70">{hint}</p> : null}
            </CardContent>
        </Card>
    );
}

export function ChartCard({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm">{title}</CardTitle>
                <p className="text-xs text-muted-foreground">{subtitle}</p>
            </CardHeader>
            <CardContent className="h-64 p-2">{children}</CardContent>
        </Card>
    );
}

export function ChartEmpty({ icon: Icon, title, description }: { icon: React.ComponentType<{ className?: string }>; title: string; description: string }) {
    return (
        <div className="flex h-full flex-col items-center justify-center gap-1 text-center">
            <Icon className="h-8 w-8 text-muted-foreground/50" />
            <p className="text-sm font-medium text-muted-foreground">{title}</p>
            <p className="max-w-[220px] text-xs text-muted-foreground/70">{description}</p>
        </div>
    );
}

function Donut({ data }: { data: NamedValue[] }) {
    const displayData = data.map((entry) => ({ ...entry, name: formatTextileLabel(entry.name) }));
    return (
        <ResponsiveContainer width="100%" height="100%">
            <PieChart>
                <Pie data={displayData} dataKey="value" nameKey="name" innerRadius={45} outerRadius={75} paddingAngle={2}>
                    {displayData.map((entry, index) => (
                        <Cell key={entry.name} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                    ))}
                </Pie>
                <Tooltip />
                <Legend wrapperStyle={{ fontSize: 11 }} />
            </PieChart>
        </ResponsiveContainer>
    );
}

function VerticalBars({ data, color, dataKey, name }: { data: NamedValue[]; color: string; dataKey: string; name: string }) {
    const displayData = data.map((entry) => ({ ...entry, name: formatTextileLabel(entry.name) }));
    return (
        <ResponsiveContainer width="100%" height="100%">
            <BarChart data={displayData} margin={{ top: 5, right: 10, left: -25, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                <XAxis dataKey="name" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                <Tooltip />
                <Bar dataKey={dataKey} name={name} fill={color} radius={[4, 4, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}

function hasValues<T>(data: T[], key: keyof T): boolean {
    return data.some((point) => Number(point[key]) > 0);
}
