import { ReactNode } from 'react';
import { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { TextileKpiOverview, TextileKpiItem } from '@/components/textile/textile-kpi-overview';
import { TextileFormCard } from '@/components/textile/textile-form-card';

interface TextileSectionProps {
    /** Per-section KPI strip rendered above the form (standard flow: KPI -> form -> table). */
    kpis?: TextileKpiItem[];
    formTitle?: string;
    formIcon?: LucideIcon;
    form?: ReactNode;
    table: ReactNode;
    className?: string;
}

/** Standard workspace section body: optional KPI strip, create form, then records table. */
export function TextileSection({ kpis, formTitle, formIcon, form, table, className }: TextileSectionProps) {
    return (
        <div className={cn('space-y-6', className)}>
            {kpis?.length ? <TextileKpiOverview items={kpis} /> : null}
            {form ? (
                <TextileFormCard title={formTitle ?? ''} icon={formIcon}>
                    {form}
                </TextileFormCard>
            ) : null}
            {table}
        </div>
    );
}
