import { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';

interface TextileDataTableCardProps {
    data: unknown[];
    columns: Array<{ key: string; header: string; render?: (...args: any[]) => any }>;
    emptyState?: ReactNode;
    className?: string;
    searchable?: boolean;
    searchPlaceholder?: string;
    showPagination?: boolean;
    pageSize?: number;
    exportable?: boolean;
    exportFilename?: string;
    exportUrl?: string;
}

export function TextileDataTableCard({
    data,
    columns,
    emptyState,
    className,
    searchable = true,
    searchPlaceholder,
    showPagination = true,
    pageSize = 10,
    exportable,
    exportFilename,
    exportUrl,
}: TextileDataTableCardProps) {
    return (
        <Card className={className}>
            <CardContent className="p-0">
                <DataTable
                    data={data}
                    columns={columns.map((column) => ({ ...column, sortable: true }))}
                    emptyState={emptyState}
                    searchable={searchable}
                    searchPlaceholder={searchPlaceholder}
                    showPagination={showPagination}
                    pageSize={pageSize}
                    exportable={exportable}
                    exportFilename={exportFilename}
                    exportUrl={exportUrl}
                />
            </CardContent>
        </Card>
    );
}
