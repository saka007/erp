import { cn } from '@/lib/utils';
import { Card, CardContent } from '@/components/ui/card';

export interface TextileKpiItem {
    label: string;
    value: number | string;
    hint?: string;
}

interface TextileKpiOverviewProps {
    title?: string;
    items: TextileKpiItem[];
    className?: string;
}

export function TextileKpiOverview({ title, items, className }: TextileKpiOverviewProps) {
    return (
        <section className={cn('space-y-3', className)}>
            {title ? <h2 className="text-base font-semibold text-foreground">{title}</h2> : null}
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {items.map((item) => (
                    <Card key={item.label} className="border border-border/70 bg-card/80">
                        <CardContent className="space-y-1 px-4 py-3">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{item.label}</p>
                            <p className="text-2xl font-semibold text-foreground">{item.value}</p>
                            {item.hint ? <p className="text-xs text-muted-foreground">{item.hint}</p> : null}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </section>
    );
}
