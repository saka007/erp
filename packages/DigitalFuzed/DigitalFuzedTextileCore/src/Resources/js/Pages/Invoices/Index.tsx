import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import {
    BadgeCheck,
    Banknote,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    Download,
    Edit as EditIcon,
    Eye,
    FileEdit,
    FileText,
    ListChecks,
    Receipt,
    ReceiptText,
    Send,
    ShoppingBag,
    ShoppingCart,
    Trash2,
    Workflow,
} from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileSection } from '@/components/textile/textile-section';
import { TextileWorkspace } from '@/components/textile/textile-workspace';
import { getTextileWorkspace } from '@/components/textile/textile-workspaces';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { formatCurrency } from '@/utils/helpers';
import { PageProps } from '@/types';

interface InvoiceRow {
    id: number;
    kind: 'sales' | 'purchase' | 'job-work';
    invoice_number: string;
    invoice_date?: string | null;
    due_date?: string | null;
    type?: string | null;
    party_name?: string | null;
    party_id?: number | null;
    subtotal: number;
    tax_amount: number;
    discount_amount: number;
    total_amount: number;
    paid_amount: number;
    balance_amount: number;
    status: string;
    item_count: number;
    payment_terms?: string | null;
    notes?: string | null;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function StatusBadge({ status }: { status: string }) {
    const { t } = useTranslation();

    const styles: Record<string, string> = {
        draft: 'bg-slate-100 text-slate-700',
        posted: 'bg-sky-100 text-sky-700',
        partial: 'bg-amber-100 text-amber-700',
        paid: 'bg-emerald-100 text-emerald-700',
        cancelled: 'bg-red-100 text-red-700',
        overdue: 'bg-red-100 text-red-700',
    };

    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${styles[status] ?? 'bg-slate-100 text-slate-700'}`}>
            {status}
        </span>
    );
}

export default function Index({
    salesInvoices,
    purchaseInvoices,
    jobWorkInvoices,
}: {
    salesInvoices: InvoiceRow[];
    purchaseInvoices: InvoiceRow[];
    jobWorkInvoices: InvoiceRow[];
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;

    const invoicesWorkspace = getTextileWorkspace('invoices')!;

    const allInvoices = [...salesInvoices, ...purchaseInvoices, ...jobWorkInvoices];
    const totalValue = allInvoices.reduce((sum, row) => sum + Number(row.total_amount || 0), 0);
    const unpaidBalance = allInvoices.reduce((sum, row) => sum + Number(row.balance_amount || 0), 0);
    const draftCount = allInvoices.filter((row) => row.status === 'draft').length;
    const postedCount = allInvoices.filter((row) => row.status === 'posted').length;
    const paidCount = allInvoices.filter((row) => row.status === 'paid').length;

    const invoiceNumberLink = (row: InvoiceRow, kind: 'sales' | 'purchase') => {
        const href = kind === 'sales'
            ? route('sales-invoices.show', { sales_invoice: row.id })
            : route('purchase-invoices.show', { purchase_invoice: row.id });

        return (
            <a
                href={href}
                className="inline-flex items-center gap-1 font-medium text-emerald-700 hover:text-emerald-900 hover:underline"
            >
                <ReceiptText className="h-3.5 w-3.5" />
                {row.invoice_number}
            </a>
        );
    };

    const [deleteTarget, setDeleteTarget] = useState<{ id: number; routeName: string } | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const invoiceActions = (row: InvoiceRow, kind: 'sales' | 'purchase') => {
        const permissions = auth.user?.permissions || [];
        const paramName = kind === 'sales' ? 'sales_invoice' : 'purchase_invoice';
        const printRoute = kind === 'sales' ? 'sales-invoices.print' : 'purchase-invoices.print';
        const showRoute = kind === 'sales' ? 'sales-invoices.show' : 'purchase-invoices.show';
        const postRoute = kind === 'sales' ? 'sales-invoices.post' : 'purchase-invoices.post';
        const editRoute = kind === 'sales' ? 'sales-invoices.edit' : 'purchase-invoices.edit';
        const destroyRoute = kind === 'sales' ? 'sales-invoices.destroy' : 'purchase-invoices.destroy';
        const canPrint = permissions.includes(`${kind === 'sales' ? 'print-sales-invoices' : 'print-purchase-invoices'}`);
        const canView = permissions.includes(`${kind === 'sales' ? 'view-sales-invoices' : 'view-purchase-invoices'}`);
        const canPost = permissions.includes(`${kind === 'sales' ? 'post-sales-invoices' : 'post-purchase-invoices'}`);
        const canEdit = permissions.includes(`${kind === 'sales' ? 'edit-sales-invoices' : 'edit-purchase-invoices'}`);
        const canDelete = permissions.includes(`${kind === 'sales' ? 'delete-sales-invoices' : 'delete-purchase-invoices'}`);

        return (
            <div className="flex gap-1">
                <TooltipProvider>
                    {canPrint && (
                        <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => window.open(route(printRoute, { [paramName]: row.id }) + '?download=pdf', '_blank')}
                                    className="h-8 w-8 p-0 text-orange-600 hover:text-orange-700"
                                >
                                    <Download className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('Download PDF')}</p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                    {canView && (
                        <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => router.get(route(showRoute, { [paramName]: row.id }))}
                                    className="h-8 w-8 p-0 text-green-600 hover:text-green-700"
                                >
                                    <Eye className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('View')}</p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                    {row.status === 'draft' && (
                        <>
                            {canPost && (
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => router.post(route(postRoute, { [paramName]: row.id }))}
                                            className="h-8 w-8 p-0 text-purple-600 hover:text-purple-700"
                                        >
                                            <FileText className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Post invoice to finalize and create journal entries')}</p>
                                    </TooltipContent>
                                </Tooltip>
                            )}
                            {canEdit && (
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => router.visit(route(editRoute, { [paramName]: row.id }))}
                                            className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700"
                                        >
                                            <EditIcon className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Edit')}</p>
                                    </TooltipContent>
                                </Tooltip>
                            )}
                            {canDelete && (
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setDeleteTarget({ id: row.id, routeName: destroyRoute })}
                                            className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Delete')}</p>
                                    </TooltipContent>
                                </Tooltip>
                            )}
                        </>
                    )}
                </TooltipProvider>
            </div>
        );
    };

    const actionsColumn = (kindForRow: (row: InvoiceRow) => 'sales' | 'purchase') => ({
        key: 'actions',
        header: t('Actions'),
        render: (_value: unknown, row: InvoiceRow) => invoiceActions(row, kindForRow(row)),
    });

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setIsDeleting(true);
        router.delete(route(deleteTarget.routeName, { [deleteTarget.routeName.includes('sales') ? 'sales_invoice' : 'purchase_invoice']: deleteTarget.id }), {
            onSuccess: () => {
                setDeleteTarget(null);
                setIsDeleting(false);
            },
            onError: () => setIsDeleting(false),
        });
    };

    const sharedColumns = (kind: 'sales' | 'purchase') => [
        { key: 'invoice_number', header: t('Invoice'), render: (_value: unknown, row: InvoiceRow) => invoiceNumberLink(row, kind) },
        { key: 'invoice_date', header: t('Date'), render: (_value: unknown, row: InvoiceRow) => formatDate(row.invoice_date) },
        { key: 'party_name', header: t('Party'), render: (_value: unknown, row: InvoiceRow) => row.party_name ?? '-' },
        { key: 'item_count', header: t('Items'), render: (_value: unknown, row: InvoiceRow) => row.item_count },
        { key: 'subtotal', header: t('Subtotal'), render: (_value: unknown, row: InvoiceRow) => formatCurrency(row.subtotal) },
        { key: 'tax_amount', header: t('Tax'), render: (_value: unknown, row: InvoiceRow) => formatCurrency(row.tax_amount) },
        { key: 'total_amount', header: t('Total'), render: (_value: unknown, row: InvoiceRow) => formatCurrency(row.total_amount) },
        { key: 'balance_amount', header: t('Balance'), render: (_value: unknown, row: InvoiceRow) => formatCurrency(row.balance_amount) },
        { key: 'status', header: t('Status'), render: (_value: unknown, row: InvoiceRow) => <StatusBadge status={row.status} /> },
        { key: 'due_date', header: t('Due Date'), render: (_value: unknown, row: InvoiceRow) => formatDate(row.due_date) },
        ...(hasAnyInvoicePermission ? [actionsColumn(() => kind)] : []),
    ];

    const hasAnyInvoicePermission = (auth.user?.permissions || []).some((permission: string) => [
        'view-sales-invoices',
        'edit-sales-invoices',
        'delete-sales-invoices',
        'post-sales-invoices',
        'print-sales-invoices',
        'view-purchase-invoices',
        'edit-purchase-invoices',
        'delete-purchase-invoices',
        'post-purchase-invoices',
        'print-purchase-invoices',
    ].includes(permission));

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Textile') },
                { label: t('Invoices') },
            ]}
            pageTitle={t('Textile Invoices')}
        >
            <Head title={t('Textile Invoices')} />

            <TextileWorkspace
                workspace={invoicesWorkspace}
                capabilities={auth.user?.textile_capabilities || {}}
                kpis={(section) => {
                    const rows: InvoiceRow[] = section.id === 'sales'
                        ? salesInvoices
                        : section.id === 'purchase'
                            ? purchaseInvoices
                            : section.id === 'job-work'
                                ? jobWorkInvoices
                                : allInvoices;

                    if (section.id === 'overview') {
                        return [
                            { label: t('Total Invoices'), value: allInvoices.length, hint: t('Sales + Purchase + Job Work'), icon: ListChecks },
                            { label: t('Invoice Value'), value: formatCurrency(totalValue), hint: t('Sum of invoice totals'), icon: CircleDollarSign },
                            { label: t('Unpaid Balance'), value: formatCurrency(unpaidBalance), hint: t('Open receivables and payables'), icon: Banknote },
                            { label: t('Draft'), value: draftCount, hint: t('Awaiting posting'), icon: FileEdit },
                            { label: t('Posted'), value: postedCount, hint: t('Active invoices'), icon: Send },
                            { label: t('Paid'), value: paidCount, hint: t('Fully settled'), icon: CheckCircle2 },
                        ];
                    }

                    const sectionTotal = rows.reduce((sum, row) => sum + Number(row.total_amount || 0), 0);
                    const sectionBalance = rows.reduce((sum, row) => sum + Number(row.balance_amount || 0), 0);
                    return [
                        { label: t('Invoices'), value: rows.length, hint: t('In this tab'), icon: ListChecks },
                        { label: t('Total Value'), value: formatCurrency(sectionTotal), hint: t('Sum of totals'), icon: CircleDollarSign },
                        { label: t('Unpaid Balance'), value: formatCurrency(sectionBalance), hint: t('Open balance'), icon: Banknote },
                        { label: t('Draft'), value: rows.filter((row) => row.status === 'draft').length, hint: t('Awaiting posting'), icon: FileEdit },
                        { label: t('Overdue'), value: rows.filter((row) => row.status === 'overdue').length, hint: t('Past due date'), icon: Clock },
                    ];
                }}
                aside={(section) => {
                    const rows: InvoiceRow[] = section.id === 'sales'
                        ? salesInvoices
                        : section.id === 'purchase'
                            ? purchaseInvoices
                            : section.id === 'job-work'
                                ? jobWorkInvoices
                                : allInvoices;

                    const receivables = rows.filter((row) => row.balance_amount > 0).reduce((sum, row) => sum + Number(row.balance_amount || 0), 0);

                    return (
                        <div className="space-y-4">
                            <div className="rounded-lg border bg-card p-4 text-card-foreground shadow-sm">
                                <div className="flex items-center gap-2 text-sm font-medium">
                                    <Receipt className="h-4 w-4 text-emerald-600" />
                                    {t('Invoice Summary')}
                                </div>
                                <dl className="mt-3 space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">{t('Sales Invoices')}</dt>
                                        <dd className="font-medium">{salesInvoices.length}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">{t('Purchase Invoices')}</dt>
                                        <dd className="font-medium">{purchaseInvoices.length}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">{t('Job Work Invoices')}</dt>
                                        <dd className="font-medium">{jobWorkInvoices.length}</dd>
                                    </div>
                                    <div className="flex justify-between border-t pt-2">
                                        <dt className="text-muted-foreground">{t('Open Balance')}</dt>
                                        <dd className="font-medium text-amber-700">{formatCurrency(receivables)}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div className="rounded-lg border bg-card p-4 text-card-foreground shadow-sm">
                                <div className="flex items-center gap-2 text-sm font-medium">
                                    <BadgeCheck className="h-4 w-4 text-emerald-600" />
                                    {t('Status Legend')}
                                </div>
                                <dl className="mt-3 space-y-2 text-xs">
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status="draft" />
                                        <span className="text-muted-foreground">{t('Created, not yet posted')}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status="posted" />
                                        <span className="text-muted-foreground">{t('Posted, awaiting payment')}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status="partial" />
                                        <span className="text-muted-foreground">{t('Partially paid')}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status="paid" />
                                        <span className="text-muted-foreground">{t('Fully settled')}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status="overdue" />
                                        <span className="text-muted-foreground">{t('Past due date')}</span>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    );
                }}
            >
                {(section) => {
                    switch (section.id) {
                        case 'overview':
                            return (
                                <TextileSection
                                    table={
                                        <TextileDataTableCard
                                            data={allInvoices}
                                            columns={[
                                                { key: 'kind', header: t('Type'), render: (_value: unknown, row: InvoiceRow) => (
                                                    <span className="inline-flex items-center gap-1 text-sm">
                                                        {row.kind === 'job-work'
                                                            ? (<><Workflow className="h-3.5 w-3.5 text-violet-500" />{t('Job Work')}</>)
                                                            : row.kind === 'sales'
                                                                ? (<><ShoppingBag className="h-3.5 w-3.5 text-emerald-600" />{t('Sales')}</>)
                                                                : (<><ShoppingCart className="h-3.5 w-3.5 text-sky-600" />{t('Purchase')}</>)}
                                                    </span>
                                                )},
                                                { key: 'invoice_number', header: t('Invoice'), render: (_value: unknown, row: InvoiceRow) => invoiceNumberLink(row, row.kind === 'purchase' ? 'purchase' : 'sales') },
                                                { key: 'invoice_date', header: t('Date'), render: (_value: unknown, row: InvoiceRow) => formatDate(row.invoice_date) },
                                                { key: 'party_name', header: t('Party'), render: (_value: unknown, row: InvoiceRow) => row.party_name ?? '-' },
                                                { key: 'item_count', header: t('Items'), render: (_value: unknown, row: InvoiceRow) => row.item_count },
                                                { key: 'total_amount', header: t('Total'), render: (_value: unknown, row: InvoiceRow) => formatCurrency(row.total_amount) },
                                                { key: 'balance_amount', header: t('Balance'), render: (_value: unknown, row: InvoiceRow) => formatCurrency(row.balance_amount) },
                                                { key: 'status', header: t('Status'), render: (_value: unknown, row: InvoiceRow) => <StatusBadge status={row.status} /> },
                                                ...(hasAnyInvoicePermission ? [actionsColumn((row) => row.kind === 'purchase' ? 'purchase' : 'sales')] : []),
                                            ]}
                                        />
                                    }
                                />
                            );

                        case 'sales':
                            return (
                                <TextileSection
                                    table={
                                        <TextileDataTableCard
                                            data={salesInvoices}
                                            columns={sharedColumns('sales')}
                                        />
                                    }
                                />
                            );

                        case 'purchase':
                            return (
                                <TextileSection
                                    table={
                                        <TextileDataTableCard
                                            data={purchaseInvoices}
                                            columns={sharedColumns('purchase')}
                                        />
                                    }
                                />
                            );

                        case 'job-work':
                            return (
                                <TextileSection
                                    table={
                                        <TextileDataTableCard
                                            data={jobWorkInvoices}
                                            columns={sharedColumns('sales')}
                                        />
                                    }
                                />
                            );

                        default:
                            return null;
                    }
                }}
            </TextileWorkspace>

            <ConfirmationDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => { if (!open) { setDeleteTarget(null); } }}
                title={t('Delete Invoice')}
                message={t('Are you sure you want to delete this invoice? This action cannot be undone.')}
                confirmText={isDeleting ? t('Deleting...') : t('Delete')}
                cancelText={t('Cancel')}
                variant="destructive"
                onConfirm={confirmDelete}
            />
        </AuthenticatedLayout>
    );
}
