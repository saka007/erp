import type { ReactNode } from 'react';

export interface ExportColumn {
    key: string;
    header: string;
    render?: (value: any, row: any, index: number) => ReactNode;
}

type CellText = (value: any) => string;

export function tableRows<T>(data: T[], columns: ExportColumn[]): string[][] {
    return data.map((row) =>
        columns.map((column) => {
            const rendered = column.render ? column.render((row as any)[column.key], row, 0) : null;
            const value =
                typeof rendered === 'string' || typeof rendered === 'number'
                    ? rendered
                    : (row as any)[column.key];
            return cellText(value);
        })
    );
}

export function downloadCsv(rows: string[][], headers: string[], filename: string): void {
    const csv = [
        headers.map(csvCell).join(','),
        ...rows.map((row) => row.map(csvCell).join(',')),
    ].join('\n');
    downloadBlob(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }), `${filename}.csv`);
}

export function downloadExcelHtml(rows: string[][], headers: string[], filename: string): void {
    const table = `
        <table>
            <thead><tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead>
            <tbody>${rows
                .map(
                    (row) =>
                        `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`
                )
                .join('')}
            </tbody>
        </table>`;
    const html = `<html><head><meta charset="utf-8"></head><body>${table}</body></html>`;
    downloadBlob(new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' }), `${filename}.xls`);
}

export function printTable(title: string, headers: string[], rows: string[][], hint?: string): void {
    const printWindow = window.open('', '_blank', 'width=1000,height=700');
    if (!printWindow) return;
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8" />
            <title>${escapeHtml(title)}</title>
            <style>
                * { box-sizing: border-box; }
                body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
                h1 { font-size: 18px; margin: 0 0 4px; }
                .hint { font-size: 12px; color: #666; margin-bottom: 16px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
                th { background: #f0f0f0; font-weight: 700; }
                tr:nth-child(even) td { background: #fafafa; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <h1>${escapeHtml(title)}</h1>
            ${hint ? `<p class="hint">${escapeHtml(hint)}</p>` : ''}
            <table>
                <thead><tr>${headers.map((h) => `<th>${escapeHtml(h)}</th>`).join('')}</tr></thead>
                <tbody>${rows
                    .map(
                        (row) =>
                            `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`
                    )
                    .join('')}
                </tbody>
            </table>
            <script>
                window.onload = function () {
                    window.print();
                };
            <\/script>
        </body>
        </html>`);
    printWindow.document.close();
}

export function cellText(value: any): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'object') {
        try {
            return JSON.stringify(value);
        } catch {
            return '';
        }
    }
    return String(value);
}

function csvCell(value: string): string {
    return /[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function downloadBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}
