import { ReactNode, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { TextileKpiOverview, TextileKpiItem } from '@/components/textile/textile-kpi-overview';
import { TextileSection, TextileWorkspace as TextileWorkspaceDef } from '@/components/textile/textile-workspaces';

interface TextileWorkspaceProps {
    workspace: TextileWorkspaceDef;
    capabilities?: Record<string, boolean>;
    kpis?: (section: TextileSection) => TextileKpiItem[];
    aside?: (section: TextileSection) => ReactNode;
    children: (section: TextileSection) => ReactNode;
}

export function countSectionStatuses(rows: Array<{ status?: string | null }>) {
    return {
        total: rows.length,
        draft: rows.filter((row) => row.status === 'draft').length,
        approved: rows.filter((row) => row.status === 'approved').length,
        released: rows.filter((row) => row.status === 'released').length,
    };
}

/** Resolves which sections the current user may see: fine-grained capability keys win, otherwise fail-open. */
export function useTextileSection(workspace: TextileWorkspaceDef, capabilities?: Record<string, boolean>) {
    const hasFineGrainedCapabilities = capabilities
        ? Object.keys(capabilities).some((key) => key.startsWith(`${workspace.id}_`))
        : false;
    const visibleSections = hasFineGrainedCapabilities
        ? workspace.sections.filter((section) => !section.capability || capabilities?.[section.capability])
        : workspace.sections;
    const sectionParam = new URLSearchParams(window.location.search).get('section');
    const activeSection =
        visibleSections.find((section) => section.id === sectionParam) ?? visibleSections[0] ?? workspace.sections[0];
    return { visibleSections, activeSection };
}

export function TextileWorkspace({ workspace, capabilities, kpis, aside, children }: TextileWorkspaceProps) {
    const { visibleSections, activeSection } = useTextileSection(workspace, capabilities);

    const navigate = useCallback(
        (section: TextileSection) => {
            const url = section.routeName
                ? route(section.routeName, { section: section.targetSection ?? section.id })
                : route(workspace.routeName, { section: section.id });
            router.get(url, {}, { preserveState: true, replace: true });
        },
        [workspace.routeName]
    );

    return (
        <div className="flex flex-col gap-6 lg:flex-row">
            {/* Mobile: native section picker */}
            <select
                className="h-10 rounded-md border border-input bg-background px-3 text-sm lg:hidden"
                value={activeSection.id}
                onChange={(e) => {
                    const selected = visibleSections.find((section) => section.id === e.target.value) ?? visibleSections[0];
                    if (selected) navigate(selected);
                }}
                aria-label={workspace.title}
            >
                {visibleSections.map((section) => (
                    <option key={section.id} value={section.id}>
                        {section.label}
                    </option>
                ))}
            </select>

            {/* Desktop: left rail */}
            <aside
                className="w-full shrink-0 space-y-1 lg:sticky lg:top-24 lg:w-52 lg:self-start xl:w-56"
                aria-label={`${workspace.title} sections`}
            >
                {visibleSections.map((section) => {
                    const Icon = section.icon;
                    const isActive = section.id === activeSection.id;
                    return (
                        <button
                            key={section.id}
                            type="button"
                            onClick={() => navigate(section)}
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                'flex w-full items-center gap-2.5 rounded-lg border-l-[3px] px-3 py-2 text-sm font-medium transition-colors',
                                isActive
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-900'
                                    : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground'
                            )}
                        >
                            <span
                                className={cn(
                                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-md',
                                    isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground'
                                )}
                            >
                                <Icon className="h-3.5 w-3.5" />
                            </span>
                            {section.label}
                        </button>
                    );
                })}
            </aside>

            {/* Content column: per-section KPIs then section body */}
            <div className="min-w-0 flex-1 space-y-6">
                {kpis && activeSection ? <TextileKpiOverview items={kpis(activeSection)} /> : null}
                {children(activeSection)}
            </div>

            {/* Optional right information column */}
            {aside && activeSection ? (
                <aside className="w-full shrink-0 space-y-4 xl:w-72" aria-label={`${workspace.title} context panel`}>
                    {aside(activeSection)}
                </aside>
            ) : null}
        </div>
    );
}
