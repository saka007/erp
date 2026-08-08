import { ReactNode } from 'react';
import { Check, CircleDashed, FileText, GitBranch, History, Landmark, ListTodo, PlayCircle, Send, Truck, UserRound } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface WorkflowStage {
    id: string;
    label: string;
    count: number;
    active?: boolean;
}

export interface SupplierSummary {
    id: number;
    name: string;
    contact_person?: string | null;
    contact_mobile?: string | null;
    primary_email?: string | null;
    credit_limit?: string | null;
    payment_terms?: string | null;
    currency_code?: string | null;
    doc_count: number;
    total_quantity: number;
    last_purchase_at?: string | null;
}

export interface ActivityItem {
    id: number;
    event_type: string;
    document_type?: string | null;
    document_number?: string | null;
    action?: string | null;
    from?: string | null;
    to?: string | null;
    actor_name?: string | null;
    created_at: string;
}

function formatRelativeTime(value: string): string {
    const seconds = Math.max(1, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
    if (seconds < 60) return `${seconds}s ago`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return days < 30 ? `${days}d ago` : new Date(value).toLocaleDateString();
}

function CardFrame({ title, icon, children }: { title: string; icon: ReactNode; children: ReactNode }) {
    return (
        <Card className="border border-border/70 bg-card/80 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0 px-4 py-3">
                <CardTitle className="text-sm font-semibold text-foreground">{title}</CardTitle>
                <span className="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-100 text-emerald-700">{icon}</span>
            </CardHeader>
            <CardContent className="px-4 pb-4">{children}</CardContent>
        </Card>
    );
}

export function TextileWorkflowStepper({ stages }: { stages: WorkflowStage[] }) {
    return (
        <CardFrame title="Workflow Status" icon={<GitBranch className="h-3.5 w-3.5" />}>
            <ol className="space-y-1">
                {stages.map((stage, index) => {
                    const done = stage.count > 0;
                    const isActive = stage.active;
                    return (
                        <li key={stage.id} className="relative flex items-start gap-3 pb-3 last:pb-0">
                            {index < stages.length - 1 ? (
                                <span
                                    className={cn(
                                        'absolute left-[9px] top-5 h-full w-px',
                                        done && stages[index + 1].count > 0 ? 'bg-emerald-400' : 'bg-border'
                                    )}
                                />
                            ) : null}
                            <span
                                className={cn(
                                    'mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border',
                                    isActive
                                        ? 'border-emerald-500 bg-emerald-500 text-white'
                                        : done
                                          ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                          : 'border-border bg-background text-muted-foreground'
                                )}
                            >
                                {done ? <Check className="h-3 w-3" /> : <CircleDashed className="h-3 w-3" />}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p
                                    className={cn(
                                        'truncate text-sm',
                                        isActive ? 'font-semibold text-emerald-700' : done ? 'font-medium text-foreground' : 'text-muted-foreground'
                                    )}
                                >
                                    {stage.label}
                                </p>
                            </div>
                            <span className="text-xs font-medium text-muted-foreground">{stage.count}</span>
                        </li>
                    );
                })}
            </ol>
        </CardFrame>
    );
}

export function SupplierSummaryCard({ supplier }: { supplier?: SupplierSummary | null }) {
    return (
        <CardFrame title="Supplier Summary" icon={<Landmark className="h-3.5 w-3.5" />}>
            {!supplier ? (
                <p className="text-sm text-muted-foreground">No supplier profiles yet. Create a vendor profile to see summaries here.</p>
            ) : (
                <div className="space-y-3">
                    <div className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <UserRound className="h-4 w-4" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-foreground">{supplier.name}</p>
                            <p className="text-xs text-muted-foreground">{supplier.contact_person ?? 'Active supplier'}</p>
                        </div>
                    </div>
                    <dl className="space-y-1.5 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Status</dt>
                            <dd className="inline-flex items-center gap-1.5 font-medium text-emerald-700">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                Active
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Credit Limit</dt>
                            <dd className="font-medium">{supplier.credit_limit ? Number(supplier.credit_limit).toLocaleString() : '—'}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Orders</dt>
                            <dd className="font-medium">{supplier.doc_count}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Total Qty</dt>
                            <dd className="font-medium">{Number(supplier.total_quantity || 0).toLocaleString()}</dd>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <dt className="text-muted-foreground">Last Purchase</dt>
                            <dd className="font-medium">{supplier.last_purchase_at ? formatRelativeTime(supplier.last_purchase_at) : '—'}</dd>
                        </div>
                    </dl>
                </div>
            )}
        </CardFrame>
    );
}

function activityMeta(eventType: string) {
    if (eventType.includes('status_changed')) return { label: 'Status changed', icon: GitBranch };
    if (eventType.includes('costing')) return { label: 'Costing finalized', icon: FileText };
    return { label: 'Document created', icon: eventType.includes('grn') ? Truck : Send };
}

export function RecentActivityCard({ activities }: { activities: ActivityItem[] }) {
    return (
        <CardFrame title="Recent Activity" icon={<History className="h-3.5 w-3.5" />}>
            {activities.length === 0 ? (
                <p className="text-sm text-muted-foreground">No activity recorded yet.</p>
            ) : (
                <ol className="space-y-3">
                    {activities.map((activity) => {
                        const meta = activityMeta(activity.event_type);
                        const Icon = meta.icon;
                        return (
                            <li key={activity.id} className="flex items-start gap-2.5">
                                <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    <Icon className="h-3.5 w-3.5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm text-foreground">
                                        {meta.label}
                                        {activity.document_number ? (
                                            <span className="font-medium text-emerald-700"> {activity.document_number}</span>
                                        ) : null}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {activity.actor_name ?? 'System'} · {formatRelativeTime(activity.created_at)}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}
        </CardFrame>
    );
}

export function MetricSummaryCard({ title, rows }: { title: string; rows: Array<{ label: string; value: string | number }> }) {
    return (
        <CardFrame title={title} icon={<FileText className="h-3.5 w-3.5" />}>
            <dl className="space-y-1.5 text-sm">
                {rows.map((row) => (
                    <div key={row.label} className="flex items-center justify-between gap-2">
                        <dt className="text-muted-foreground">{row.label}</dt>
                        <dd className="font-medium">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </CardFrame>
    );
}

export function UpcomingTasksCard({ tasks }: { tasks: Array<{ label: string; meta: string }> }) {
    return (
        <CardFrame title="Upcoming Tasks" icon={<ListTodo className="h-3.5 w-3.5" />}>
            {tasks.length === 0 ? (
                <p className="text-sm text-muted-foreground">No pending tasks. Everything is up to date.</p>
            ) : (
                <ol className="space-y-2.5">
                    {tasks.map((task, index) => (
                        <li key={`${task.label}-${index}`} className="flex items-start gap-2.5">
                            <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm text-foreground">{task.label}</p>
                                <p className="text-xs text-muted-foreground">{task.meta}</p>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </CardFrame>
    );
}

export function TextileInfoPanel({
    stages,
    supplier,
    activities,
}: {
    stages: WorkflowStage[];
    supplier?: SupplierSummary | null;
    activities: ActivityItem[];
}) {
    return (
        <div className="space-y-4">
            <TextileWorkflowStepper stages={stages} />
            {supplier ? <SupplierSummaryCard supplier={supplier} /> : null}
            <RecentActivityCard activities={activities} />
        </div>
    );
}
