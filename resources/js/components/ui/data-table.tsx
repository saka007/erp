import React, { useState, useMemo } from 'react';
import { Card, CardContent, CardHeader } from './card';
import { Input } from './input';
import { Button } from './button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './table';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, ChevronLeft, ChevronRight, FileDown, FileSpreadsheet, FileText, Printer } from "lucide-react";
import { cn } from "@/lib/utils";
import { tableRows, downloadCsv, downloadExcelHtml, printTable } from '@/lib/table-export';
import { formatTextileLabel } from '@/components/textile/textile-form-options';

function formatCellValue(value: unknown): React.ReactNode {
  if (value === null || value === undefined || value === '') return '-';
  return typeof value === 'string' ? formatTextileLabel(value) : (value as React.ReactNode);
}

export interface Column<T = any> {
  key: string;
  header: string;
  sortable?: boolean;
  render?: (value: any, row: T, index: number) => React.ReactNode;
  className?: string;
}

export interface DataTableProps<T = any> {
  data: T[];
  columns: Column<T>[];
  onSort?: (key: string) => void;
  sortKey?: string;
  sortDirection?: 'asc' | 'desc';
  emptyState?: React.ReactNode;
  className?: string;
  searchable?: boolean;
  searchPlaceholder?: string;
  pageSize?: number;
  showPagination?: boolean;
  exportable?: boolean;
  exportFilename?: string;
  exportUrl?: string;
  rowProps?: (row: T, index: number) => React.HTMLAttributes<HTMLTableRowElement>;
}

export function DataTable<T = any>({
  data,
  columns,
  onSort,
  sortKey,
  sortDirection,
  emptyState,
  className,
  searchable = false,
  searchPlaceholder = "Search...",
  pageSize = 10,
  showPagination = false,
  exportable = false,
  exportFilename = "export",
  exportUrl,
  rowProps
}: DataTableProps<T>) {
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [internalSortKey, setInternalSortKey] = useState<string | null>(null);
  const [internalSortDirection, setInternalSortDirection] = useState<'asc' | 'desc'>('asc');

  const activeSortKey = sortKey ?? internalSortKey;
  const activeSortDirection = sortDirection ?? internalSortDirection;

  const getSortIcon = (field: string) => {
      if (activeSortKey !== field) return <ArrowUpDown className="h-4 w-4" />;
      return activeSortDirection === 'asc' ? <ArrowUp className="h-4 w-4" /> : <ArrowDown className="h-4 w-4" />;
  };

  const handleSort = (key: string, sortable?: boolean) => {
    if (!sortable) return;
    if (onSort) {
      onSort(key);
      return;
    }
    if (internalSortKey === key) {
      setInternalSortDirection((dir) => (dir === 'asc' ? 'desc' : 'asc'));
    } else {
      setInternalSortKey(key);
      setInternalSortDirection('asc');
    }
    setCurrentPage(1);
  };

  const filteredData = useMemo(() => {
    // Ensure data is always an array
    const safeData = Array.isArray(data) ? data : [];
    let rows = safeData;

    // Cell text from a custom renderer, used for search and sort fallback.
    const renderedCell = (column: Column, row: any): string => {
      if (!column.render) return '';
      const rendered = column.render(row[column.key], row, 0);
      return typeof rendered === 'string' || typeof rendered === 'number' ? String(rendered) : '';
    };

    if (searchable && searchTerm) {
      const term = searchTerm.toLowerCase();
      rows = safeData.filter((row: any) =>
        columns.some((column) => {
          const raw = row[column.key];
          if (raw?.toString().toLowerCase().includes(term)) return true;
          return renderedCell(column, row).toLowerCase().includes(term);
        })
      );
    }

    if (activeSortKey) {
      const column = columns.find((c) => c.key === activeSortKey);
      rows = [...rows].sort((a: any, b: any) => {
        let av = a[activeSortKey];
        let bv = b[activeSortKey];
        if (column?.render) {
          if (av == null || av === '') av = renderedCell(column, a);
          if (bv == null || bv === '') bv = renderedCell(column, b);
        }
        const aNum = Number(av);
        const bNum = Number(bv);
        if (av != null && bv != null && av !== '' && bv !== '' && !Number.isNaN(aNum) && !Number.isNaN(bNum)) {
          return aNum - bNum;
        }
        return String(av ?? '').localeCompare(String(bv ?? ''), undefined, { numeric: true });
      });
      if (activeSortDirection === 'desc') {
        rows.reverse();
      }
    }

    return rows;
  }, [data, searchTerm, columns, searchable, activeSortKey, activeSortDirection]);

  const paginatedData = useMemo(() => {
    if (!showPagination) return filteredData;
    const startIndex = (currentPage - 1) * pageSize;
    return filteredData.slice(startIndex, startIndex + pageSize);
  }, [filteredData, currentPage, pageSize, showPagination]);

  const totalPages = Math.ceil(filteredData.length / pageSize);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const exportRows = () => tableRows(filteredData, columns);
  const exportHeaders = () => columns.map((column) => column.header);

  const handleExport = (format: 'csv' | 'excel' | 'pdf' | 'print') => {
    if ((format === 'excel' || format === 'pdf') && exportUrl) {
      window.location.href = `${exportUrl}${exportUrl.includes('?') ? '&' : '?'}format=${format}`;
      return;
    }
    const title = exportFilename.replace(/[-_]/g, ' ');
    const hint = `${filteredData.length} rows exported on ${new Date().toLocaleString()}`;
    switch (format) {
      case 'csv':
        downloadCsv(exportRows(), exportHeaders(), exportFilename);
        break;
      case 'excel':
        downloadExcelHtml(exportRows(), exportHeaders(), exportFilename);
        break;
      case 'pdf':
        printTable(title, exportHeaders(), exportRows(), `${hint} — use Save as PDF`);
        break;
      case 'print':
        printTable(title, exportHeaders(), exportRows(), hint);
        break;
    }
  };

  return (
    <Card className={className}>
      {(searchable || exportable) && (
        <CardHeader className="pb-4">
          <div className="flex items-center gap-2">
            {searchable && (
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                <Input
                  placeholder={searchPlaceholder}
                  value={searchTerm}
                  onChange={(e) => {
                    setSearchTerm(e.target.value);
                    setCurrentPage(1);
                  }}
                  className="pl-10"
                />
              </div>
            )}
            {exportable && (
              <div className="flex items-center gap-1.5">
                <Button variant="outline" size="sm" disabled={filteredData.length === 0} onClick={() => handleExport('csv')} title="Export CSV">
                  <FileText className="mr-1.5 h-4 w-4" />
                  CSV
                </Button>
                <Button variant="outline" size="sm" disabled={filteredData.length === 0} onClick={() => handleExport('excel')} title="Export Excel">
                  <FileSpreadsheet className="mr-1.5 h-4 w-4" />
                  Excel
                </Button>
                <Button variant="outline" size="sm" disabled={filteredData.length === 0} onClick={() => handleExport('pdf')} title="Export PDF">
                  <FileDown className="mr-1.5 h-4 w-4" />
                  PDF
                </Button>
                <Button variant="outline" size="sm" disabled={filteredData.length === 0} onClick={() => handleExport('print')} title="Print table">
                  <Printer className="mr-1.5 h-4 w-4" />
                  Print
                </Button>
              </div>
            )}
          </div>
        </CardHeader>
      )}
      <CardContent className="p-0">
        <Table>
          <TableHeader>
            <TableRow>
              {columns.map((column) => (
                <TableHead
                  key={column.key}
                  className={cn(
                    "font-bold bg-gray-100 dark:bg-gray-800 dark:text-gray-200",
                    column.sortable ? 'cursor-pointer' : '',
                    column.className || ''
                  )}
                  onClick={() => handleSort(column.key, column.sortable)}
                >
                  <div className="flex items-center gap-2">
                    {column.header}
                    {column.sortable && getSortIcon(column.key)}
                  </div>
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {paginatedData.length > 0 ? (
              paginatedData.map((row, index) => (
                <TableRow 
                  key={(row as any).id || index}
                  {...(rowProps ? rowProps(row, index) : {})}
                >
                  {columns.map((column) => (
                    <TableCell key={column.key} className={column.className}>
                      {column.render
                        ? column.render((row as any)[column.key], row, index)
                        : formatCellValue((row as any)[column.key])
                      }
                    </TableCell>
                  ))}
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-24 text-center">
                  {emptyState || (
                    <div className="flex flex-col items-center justify-center text-center">
                      <p className="text-muted-foreground">
                        {searchTerm ? 'No results found' : 'No data available'}
                      </p>
                    </div>
                  )}
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </CardContent>
      {showPagination && totalPages > 1 && (
        <CardContent className="pt-4">
          <div className="flex items-center justify-between">
            <div className="text-sm text-muted-foreground">
              Showing {((currentPage - 1) * pageSize) + 1} to {Math.min(currentPage * pageSize, filteredData.length)} of {filteredData.length} results
            </div>
            <div className="flex items-center space-x-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => handlePageChange(currentPage - 1)}
                disabled={currentPage === 1}
              >
                <ChevronLeft className="h-4 w-4" />
                Previous
              </Button>
              <div className="flex items-center space-x-1">
                {Array.from({ length: totalPages }, (_, i) => i + 1)
                  .filter(page => 
                    page === 1 || 
                    page === totalPages || 
                    (page >= currentPage - 1 && page <= currentPage + 1)
                  )
                  .map((page, index, array) => (
                    <React.Fragment key={page}>
                      {index > 0 && array[index - 1] !== page - 1 && (
                        <span key={`ellipsis-${page}`} className="px-2 text-muted-foreground">...</span>
                      )}
                      <Button
                        variant={currentPage === page ? "default" : "outline"}
                        size="sm"
                        onClick={() => handlePageChange(page)}
                        className="w-8 h-8 p-0"
                      >
                        {page}
                      </Button>
                    </React.Fragment>
                  ))
                }
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handlePageChange(currentPage + 1)}
                disabled={currentPage === totalPages}
              >
                Next
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardContent>
      )}
    </Card>
  );
}