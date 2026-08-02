import { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface TextileDataTableSectionProps {
    title: string;
    count?: number;
    data: unknown[];
    columns: Array<{ key: string; header: string; render?: (...args: any[]) => any }>;
    emptyState?: ReactNode;
    className?: string;
}

export function TextileDataTableSection({ title, data, columns, emptyState, className }: TextileDataTableSectionProps) {
    return (
        <div className={cn('space-y-2', className)}>
            <h3 className="text-sm font-semibold text-foreground">{title}</h3>
            <TextileDataTableCard data={data} columns={columns} emptyState={emptyState} />
        </div>
    );
}
