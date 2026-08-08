import { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';

interface TextileDataTableSectionProps {
    title: string;
    data?: unknown[];
    columns?: Array<{ key: string; header: string; render?: (...args: any[]) => any }>;
    emptyState?: ReactNode;
    className?: string;
    searchable?: boolean;
    searchPlaceholder?: string;
    showPagination?: boolean;
    pageSize?: number;
    exportable?: boolean;
    exportFilename?: string;
    exportUrl?: string;
    children?: ReactNode;
}

export function TextileDataTableSection({
    title,
    data,
    columns,
    emptyState,
    className,
    searchable,
    searchPlaceholder,
    showPagination,
    pageSize,
    exportable,
    exportFilename,
    exportUrl,
    children,
}: TextileDataTableSectionProps) {
    return (
        <div className={cn('space-y-2', className)}>
            <h3 className="text-sm font-semibold text-foreground">{title}</h3>
            {children ?? (
                <TextileDataTableCard
                    data={data!}
                    columns={columns!}
                    emptyState={emptyState}
                    searchable={searchable}
                    searchPlaceholder={searchPlaceholder}
                    showPagination={showPagination}
                    pageSize={pageSize}
                    exportable={exportable}
                    exportFilename={exportFilename}
                    exportUrl={exportUrl}
                />
            )}
        </div>
    );
}
