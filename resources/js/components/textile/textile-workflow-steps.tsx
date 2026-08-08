import { ReactNode, useState } from 'react';
import { FileText, Table2, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type WorkflowStepStatus = 'completed' | 'pending' | 'waiting';

export interface WorkflowStep {
    id: string;
    title: string;
    icon: LucideIcon;
    /** Optional — omit for ad-hoc steps (e.g. breakdown/maintenance) that are not part of a sequence. */
    status?: WorkflowStepStatus;
    /** Number of existing records for this step, shown on the workflow chip. */
    count?: number;
    /** The single business action for this step; rendered in the Current Form tab. */
    form: ReactNode;
}

interface TextileWorkflowStepsProps {
    steps: WorkflowStep[];
    /** Currently selected step id; falls back to the first pending step. */
    openId: string | null;
    onOpenChange: (id: string | null) => void;
    /** Records tables rendered under the "Records" tab. */
    records: ReactNode;
}

/**
 * Horizontal workflow strip (one chip per process step, with status + record count)
 * and a Current Form | Records tab bar, so the active step's form is always visible
 * instead of being hidden behind accordions — matching the mockup's tab layout.
 */
export function TextileWorkflowSteps({ steps, openId, onOpenChange, records }: TextileWorkflowStepsProps) {
    const [tab, setTab] = useState<'form' | 'records'>('form');
    const active = steps.find((step) => step.id === openId) ?? steps[0];

    return (
        <div className="space-y-4">
            {/* Workflow strip */}
            <div className="flex flex-wrap gap-2">
                {steps.map((step) => {
                    const isActive = active?.id === step.id;
                    return (
                        <button
                            key={step.id}
                            type="button"
                            onClick={() => {
                                onOpenChange(isActive ? null : step.id);
                                if (!isActive) {
                                    setTab('form');
                                }
                            }}
                            aria-pressed={isActive}
                            className={cn(
                                'flex items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition-colors',
                                isActive ? 'border-emerald-300 bg-emerald-50/70 ring-1 ring-emerald-300' : 'border-border bg-card hover:bg-muted/50',
                            )}
                        >
                            <span className="max-w-44 truncate font-medium text-foreground">{step.title}</span>
                            {typeof step.count === 'number' && step.count > 0 ? (
                                <span className="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700">{step.count}</span>
                            ) : null}
                        </button>
                    );
                })}
            </div>

            {/* Tabs */}
            <div className="flex gap-1 border-b border-border">
                <button
                    type="button"
                    onClick={() => setTab('form')}
                    className={cn(
                        'flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                        tab === 'form' ? 'border-emerald-600 text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    <FileText className="h-3.5 w-3.5" />
                    Current Form
                </button>
                <button
                    type="button"
                    onClick={() => setTab('records')}
                    className={cn(
                        'flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                        tab === 'records' ? 'border-emerald-600 text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Table2 className="h-3.5 w-3.5" />
                    Records
                </button>
            </div>

            {/* Content */}
            {tab === 'form' ? (active ? <div key={active.id}>{active.form}</div> : null) : <div className="pt-4">{records}</div>}
        </div>
    );
}

/** Derives per-step status from record counts: completed → first empty step is "in progress" → rest "pending". */
export function workflowStepStatuses(counts: number[]): WorkflowStepStatus[] {
    let nextAssigned = false;
    return counts.map((count) => {
        if (count > 0) {
            return 'completed';
        }
        if (!nextAssigned) {
            nextAssigned = true;
            return 'pending';
        }
        return 'waiting';
    });
}
