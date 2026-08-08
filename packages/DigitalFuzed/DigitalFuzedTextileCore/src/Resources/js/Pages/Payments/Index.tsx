import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownCircle, ArrowUpCircle, BellRing, Check, RefreshCw, Send, ShoppingBag, TrendingDown, TrendingUp, Wallet, Warehouse } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileSection } from '@/components/textile/textile-section';
import { TextileWorkspace } from '@/components/textile/textile-workspace';
import { getTextileWorkspace } from '@/components/textile/textile-workspaces';
import { PageProps } from '@/types';

interface Totals {
    payable: number;
    receivable: number;
    net: number;
    overdue_payable: number;
    overdue_receivable: number;
    parties: number;
}

interface BranchRow {
    id: number | null;
    name: string | null;
    payable: number;
    receivable: number;
    net: number;
    overdue_payable: number;
    overdue_receivable: number;
    vendor_count: number;
    buyer_count: number;
}

interface VendorRow {
    party_id: number;
    party_type: 'supplier' | 'buyer';
    party_name: string;
    party_email?: string | null;
    direction: 'pay' | 'receive';
    credit_days?: number | null;
    credit_limit?: number | null;
    reminder_enabled: boolean;
    branch_id?: number | null;
    branch_name?: string | null;
    branch_key: string;
    outstanding: number;
    due_invoices: number;
    oldest_due_date?: string | null;
    overdue_days: number;
    last_reminded_at?: string | null;
}

interface ReminderRow {
    id: number;
    party_type: 'supplier' | 'buyer';
    party_name?: string | null;
    invoice_number?: string | null;
    amount_due: number;
    due_date?: string | null;
    template_name?: string | null;
    reminded_at?: string | null;
    branch_id?: number | null;
}

interface PartyMaster {
    party_id: number;
    party_type: 'supplier' | 'buyer';
    party_name: string;
    party_email?: string | null;
    credit_days?: number | null;
    credit_limit?: number | null;
    reminder_enabled: boolean;
    branch_id?: number | null;
}

interface BranchOption {
    id: number;
    name: string;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(value);
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function DirectionBadge({ direction }: { direction: 'pay' | 'receive' }) {
    const { t } = useTranslation();

    if (direction === 'pay') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                <ArrowUpCircle className="h-3.5 w-3.5" />
                {t('Pay to vendor')}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
            <ArrowDownCircle className="h-3.5 w-3.5" />
            {t('Receive from buyer')}
        </span>
    );
}

export default function Index({
    summary,
    partyMasters,
    branchOptions,
    selectedBranchId,
    templateNames,
}: {
    summary: {
        totals: Totals;
        branches: BranchRow[];
        vendors: VendorRow[];
        reminders: ReminderRow[];
    };
    partyMasters: PartyMaster[];
    branchOptions: BranchOption[];
    selectedBranchId: number | null;
    templateNames: { supplier: string; buyer: string };
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;

    const paymentsWorkspace = getTextileWorkspace('payments')!;

    const creditForm = useForm({
        party_key: '',
        credit_days: '',
        credit_limit: '',
        reminder_enabled: true,
        branch_id: '',
    });

    const selectedParty = partyMasters.find((party) => `${party.party_type}:${party.party_id}` === creditForm.data.party_key);

    const selectParty = (partyKey: string) => {
        const party = partyMasters.find((item) => `${item.party_type}:${item.party_id}` === partyKey);
        creditForm.setData({
            party_key: partyKey,
            credit_days: party?.credit_days != null ? String(party.credit_days) : '',
            credit_limit: party?.credit_limit != null ? String(party.credit_limit) : '',
            reminder_enabled: party?.reminder_enabled ?? true,
            branch_id: party?.branch_id != null ? String(party.branch_id) : '',
        });
    };

    const submitCredit = () => {
        if (!selectedParty) {
            return;
        }

        creditForm.post(route('textile.payments.credit.update'), {
            preserveScroll: true,
            onSuccess: () => {
                creditForm.setData('reminder_enabled', creditForm.data.reminder_enabled);
            },
        });
    };

    const sendReminders = (force = false) => {
        router.post(
            route('textile.payments.reminders.send'),
            { force, branch_id: selectedBranchId ?? undefined },
            { preserveScroll: true }
        );
    };

    const changeBranchFilter = (branchId: string) => {
        const section = new URLSearchParams(window.location.search).get('section') ?? 'overview';
        const params: Record<string, string | number> = { section };
        if (branchId !== '') {
            params.branch_id = branchId;
        }
        router.get(route('textile.payments.index', params), {}, { preserveState: true, replace: true });
    };

    const totals = summary.totals;

    return (
        <AuthenticatedLayout>
            <Head title={t('Textile Payments')} />

            <TextileWorkspace
                workspace={paymentsWorkspace}
                capabilities={auth.user?.textile_capabilities || {}}
                kpis={(section) => {
                    if (section.id === 'overview') {
                        return [
                            { label: t('Total Payable'), value: formatCurrency(totals.payable), hint: t('To yarn suppliers'), icon: TrendingUp },
                            { label: t('Total Receivable'), value: formatCurrency(totals.receivable), hint: t('From takha buyers'), icon: TrendingDown },
                            { label: t('Net Position'), value: formatCurrency(totals.net), hint: t('Receivable minus payable'), icon: Wallet },
                            { label: t('Overdue Payable'), value: formatCurrency(totals.overdue_payable), hint: t('Payments past due date'), icon: ArrowUpCircle },
                            { label: t('Overdue Receivable'), value: formatCurrency(totals.overdue_receivable), hint: t('Collections past due date'), icon: ArrowDownCircle },
                            { label: t('Parties'), value: totals.parties, hint: t('Suppliers and buyers'), icon: ShoppingBag },
                        ];
                    }
                    if (section.id === 'vendors') {
                        return [
                            { label: t('Suppliers'), value: summary.vendors.filter((row) => row.direction === 'pay').length, hint: t('Yarn and material suppliers'), icon: TrendingUp },
                            { label: t('Buyers'), value: summary.vendors.filter((row) => row.direction === 'receive').length, hint: t('Takha buyers'), icon: TrendingDown },
                            { label: t('Overdue Parties'), value: summary.vendors.filter((row) => row.overdue_days > 0).length, hint: t('With payments past due'), icon: BellRing },
                            { label: t('Reminders Disabled'), value: summary.vendors.filter((row) => !row.reminder_enabled).length, hint: t('No auto reminders'), icon: RefreshCw },
                        ];
                    }
                    return [
                        { label: t('Reminders Sent'), value: summary.reminders.length, hint: t('Recent reminder emails'), icon: Send },
                        { label: t('Template (Supplier)'), value: templateNames.supplier, hint: t('Assigned to yarn suppliers'), icon: Check },
                        { label: t('Template (Buyer)'), value: templateNames.buyer, hint: t('Assigned to takha buyers'), icon: Check },
                    ];
                }}
            >
                {(section) => {
                    switch (section.id) {
                        case 'overview':
                            return (
                                <TextileSection
                                    formTitle={t('Branch Filter')}
                                    formIcon={Warehouse}
                                    form={
                                        <div className="grid gap-4">
                                            <SelectField
                                                label={t('Branch')}
                                                value={selectedBranchId != null ? String(selectedBranchId) : ''}
                                                onChange={(value: string) => changeBranchFilter(value)}
                                                options={branchOptions.map((branch) => ({ value: String(branch.id), label: branch.name }))}
                                                includeEmpty
                                                emptyLabel={t('All branches')}
                                                helperText={t('Scope KPIs, branch overview, vendor activity, and reminders to a single branch.')}
                                            />
                                            <p className="text-xs text-gray-400">
                                                {selectedBranchId != null
                                                    ? t('Showing data for the selected branch only.')
                                                    : t('Showing data across all branches.')}
                                            </p>
                                        </div>
                                    }
                                    table={
                                        <TextileDataTableCard
                                            data={summary.branches}
                                            columns={[
                                                { key: 'name', header: t('Branch'), render: (row) => row.name ?? t('Unassigned') },
                                                { key: 'payable', header: t('Payable'), render: (row) => formatCurrency(row.payable) },
                                                { key: 'receivable', header: t('Receivable'), render: (row) => formatCurrency(row.receivable) },
                                                { key: 'net', header: t('Net'), render: (row) => formatCurrency(row.net) },
                                                { key: 'overdue_payable', header: t('Overdue Payable'), render: (row) => formatCurrency(row.overdue_payable) },
                                                { key: 'overdue_receivable', header: t('Overdue Receivable'), render: (row) => formatCurrency(row.overdue_receivable) },
                                                { key: 'vendor_count', header: t('Suppliers') },
                                                { key: 'buyer_count', header: t('Buyers') },
                                            ]}
                                        />
                                    }
                                />
                            );

                        case 'vendors':
                            return (
                                <>
                                    <TextileSection
                                        formTitle={t('Credit & Reminder Settings')}
                                        formIcon={Wallet}
                                        form={
                                            <div className="grid gap-4">
                                                <SelectField
                                                    label={t('Supplier / Buyer')}
                                                    value={creditForm.data.party_key}
                                                    onChange={(value: string) => selectParty(value)}
                                                    options={partyMasters.map((party) => ({
                                                        value: `${party.party_type}:${party.party_id}`,
                                                        label: `${party.party_name} (${party.party_type === 'supplier' ? t('Supplier') : t('Buyer')})`,
                                                    }))}
                                                    includeEmpty
                                                    emptyLabel={t('Select a supplier or buyer')}
                                                />
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Field
                                                        label={t('Credit Days')}
                                                        type="number"
                                                        min={0}
                                                        value={creditForm.data.credit_days}
                                                        onChange={(value: string) => creditForm.setData('credit_days', value)}
                                                    />
                                                    <Field
                                                        label={t('Credit Limit (INR)')}
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        value={creditForm.data.credit_limit}
                                                        onChange={(value: string) => creditForm.setData('credit_limit', value)}
                                                    />
                                                </div>
                                                <SelectField
                                                    label={t('Branch')}
                                                    value={creditForm.data.branch_id}
                                                    onChange={(value: string) => creditForm.setData('branch_id', value)}
                                                    options={branchOptions.map((branch) => ({ value: String(branch.id), label: branch.name }))}
                                                    includeEmpty
                                                    emptyLabel={t('Unassigned')}
                                                />
                                                <label className="flex items-center gap-2 text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={creditForm.data.reminder_enabled}
                                                        onChange={(e) => creditForm.setData('reminder_enabled', e.target.checked)}
                                                        className="h-4 w-4 rounded border-gray-300 text-emerald-600"
                                                    />
                                                    {t('Send automatic payment reminders after credit days end')}
                                                </label>
                                                <div className="flex justify-end">
                                                    <Button
                                                        onClick={submitCredit}
                                                        disabled={!selectedParty || creditForm.processing}
                                                        className="bg-emerald-600 hover:bg-emerald-700"
                                                    >
                                                        <Check className="mr-1.5 h-4 w-4" />
                                                        {t('Save Credit Settings')}
                                                    </Button>
                                                </div>
                                            </div>
                                        }
                                        table={
                                            <TextileDataTableCard
                                                data={summary.vendors}
                                                columns={[
                                                    { key: 'party_name', header: t('Party') },
                                                    {
                                                        key: 'direction',
                                                        header: t('Activity'),
                                                        render: (row) => <DirectionBadge direction={row.direction} />,
                                                    },
                                                    { key: 'branch_name', header: t('Branch'), render: (row) => row.branch_name ?? t('Unassigned') },
                                                    { key: 'outstanding', header: t('Outstanding'), render: (row) => formatCurrency(row.outstanding) },
                                                    { key: 'due_invoices', header: t('Due Invoices') },
                                                    { key: 'oldest_due_date', header: t('Oldest Due'), render: (row) => formatDate(row.oldest_due_date) },
                                                    {
                                                        key: 'overdue_days',
                                                        header: t('Overdue Days'),
                                                        render: (row) => (row.overdue_days > 0 ? (
                                                            <span className="font-semibold text-red-600">{row.overdue_days}</span>
                                                        ) : (
                                                            <span className="text-emerald-600">0</span>
                                                        )),
                                                    },
                                                    { key: 'credit_days', header: t('Credit Days'), render: (row) => row.credit_days ?? '-' },
                                                    {
                                                        key: 'reminder_enabled',
                                                        header: t('Reminders'),
                                                        render: (row) => (row.reminder_enabled ? (
                                                            <span className="text-emerald-600">{t('On')}</span>
                                                        ) : (
                                                            <span className="text-gray-400">{t('Off')}</span>
                                                        )),
                                                    },
                                                    {
                                                        key: 'last_reminded_at',
                                                        header: t('Last Reminded'),
                                                        render: (row) => (row.last_reminded_at ? new Date(row.last_reminded_at).toLocaleString('en-IN') : '-'),
                                                    },
                                                ]}
                                            />
                                        }
                                    />
                                </>
                            );

                        case 'reminders':
                            return (
                                <>
                                    <TextileSection
                                        formTitle={t('Send Payment Reminders')}
                                        formIcon={BellRing}
                                        form={
                                            <div className="grid gap-4">
                                                <p className="text-sm text-gray-600">
                                                    {t('Reminders are sent automatically every morning at 09:00 for invoices past their credit due date. Use the buttons below to trigger them manually.')}
                                                </p>
                                                <div className="grid gap-3 text-sm sm:grid-cols-2">
                                                    <div className="rounded-lg border border-gray-200 p-3">
                                                        <p className="font-medium text-gray-800">{t('Yarn suppliers (payable)')}</p>
                                                        <p className="mt-0.5 text-gray-500">{templateNames.supplier}</p>
                                                    </div>
                                                    <div className="rounded-lg border border-gray-200 p-3">
                                                        <p className="font-medium text-gray-800">{t('Takha buyers (receivable)')}</p>
                                                        <p className="mt-0.5 text-gray-500">{templateNames.buyer}</p>
                                                    </div>
                                                </div>
                                                <p className="text-xs text-gray-400">
                                                    {t('Templates are managed from Settings > Email Templates. Available variables: {party_name}, {invoice_number}, {amount_due}, {due_date}, {overdue_days}, {credit_days}.')}
                                                </p>
                                                <div className="flex flex-wrap justify-end gap-2">
                                                    <Button
                                                        onClick={() => sendReminders(false)}
                                                        disabled={creditForm.processing}
                                                        variant="outline"
                                                    >
                                                        <Send className="mr-1.5 h-4 w-4" />
                                                        {t('Send Reminders Now')}
                                                    </Button>
                                                    <Button
                                                        onClick={() => sendReminders(true)}
                                                        disabled={creditForm.processing}
                                                        className="bg-emerald-600 hover:bg-emerald-700"
                                                    >
                                                        <RefreshCw className="mr-1.5 h-4 w-4" />
                                                        {t('Force Send (ignore cooldown)')}
                                                    </Button>
                                                </div>
                                            </div>
                                        }
                                        table={
                                            <TextileDataTableCard
                                                data={summary.reminders}
                                                columns={[
                                                    { key: 'reminded_at', header: t('Sent At'), render: (row) => (row.reminded_at ? new Date(row.reminded_at).toLocaleString('en-IN') : '-') },
                                                    { key: 'party_name', header: t('Party'), render: (row) => row.party_name ?? '-' },
                                                    {
                                                        key: 'party_type',
                                                        header: t('Type'),
                                                        render: (row) => (row.party_type === 'supplier' ? t('Supplier') : t('Buyer')),
                                                    },
                                                    { key: 'invoice_number', header: t('Invoice'), render: (row) => row.invoice_number ?? '-' },
                                                    { key: 'amount_due', header: t('Amount Due'), render: (row) => formatCurrency(row.amount_due) },
                                                    { key: 'due_date', header: t('Due Date'), render: (row) => formatDate(row.due_date) },
                                                    { key: 'template_name', header: t('Template'), render: (row) => row.template_name ?? '-' },
                                                ]}
                                            />
                                        }
                                    />
                                </>
                            );

                        default:
                            return null;
                    }
                }}
            </TextileWorkspace>
        </AuthenticatedLayout>
    );
}
