import { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';

interface TextileDataTableCardProps {
    data: unknown[];
    columns: Array<{ key: string; header: string; render?: (...args: any[]) => any }>;
    emptyState?: ReactNode;
    className?: string;
    exportable?: boolean;
    exportFilename?: string;
    exportUrl?: string;
}

export function TextileDataTableCard({ data, columns, emptyState, className, exportable, exportFilename, exportUrl }: TextileDataTableCardProps) {
    return (
        <Card className={className}>
            <CardContent className="p-0">
                <DataTable data={data} columns={columns} emptyState={emptyState} exportable={exportable} exportFilename={exportFilename} exportUrl={exportUrl} />
            </CardContent>
        </Card>
    );
}
