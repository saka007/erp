import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Building2, Check, Factory, Search, ShoppingBag, Users, Wallet } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSection } from '@/components/textile/textile-section';
import { PageProps } from '@/types';

interface PartyMaster {
    party_id: number;
    party_type: 'supplier' | 'buyer';
    party_name: string;
    party_email?: string | null;
    supplier_type?: string | null;
    party_code?: string | null;
    credit_days?: number | null;
    credit_limit?: number | null;
    credit_enabled?: boolean;
    reminder_enabled: boolean;
    branch_id?: number | null;
    branch_name?: string | null;
}

interface CategoryOption {
    value: string;
    label: string;
}

function formatCurrency(value?: number | null): string {
    if (value == null) {
        return '-';
    }

    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(value);
}

const CATEGORY_LABELS: Record<string, { label: string; short: string }> = {
    yarn: { label: 'Yarn Supplier', short: 'Yarn' },
    sizing: { label: 'Sizing Vendor', short: 'Sizing' },
    powerloom: { label: 'Powerloom Vendor', short: 'Powerloom' },
    chemical: { label: 'Chemical Supplier', short: 'Chemical' },
    spare_part: { label: 'Spare Part Supplier', short: 'Spare Part' },
    processing: { label: 'Processing Vendor', short: 'Processing' },
    dyeing: { label: 'Dyeing Vendor', short: 'Dyeing' },
    transport: { label: 'Transport Vendor', short: 'Transport' },
    job_worker: { label: 'Job Worker', short: 'Job Worker' },
};

function CategoryBadge({ party }: { party: PartyMaster }) {
    const { t } = useTranslation();

    if (party.party_type === 'buyer') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                <ShoppingBag className="h-3.5 w-3.5" />
                {t('Customer')}
            </span>
        );
    }

    const meta = party.supplier_type ? CATEGORY_LABELS[party.supplier_type] : null;

    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
            <Factory className="h-3.5 w-3.5" />
            {t(meta?.label ?? party.supplier_type ?? 'Vendor')}
        </span>
    );
}

export default function Index({
    parties,
    categoryOptions,
    selectedCategory,
    search,
}: {
    parties: PartyMaster[];
    categoryOptions: CategoryOption[];
    selectedCategory: string;
    search: string;
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;

    const creditForm = useForm({
        party_key: '',
        credit_days: '',
        credit_limit: '',
        credit_enabled: false,
        reminder_enabled: true,
    });

    const selectedParty = parties.find((party) => `${party.party_type}:${party.party_id}` === creditForm.data.party_key);

    const selectParty = (partyKey: string) => {
        const party = parties.find((item) => `${item.party_type}:${item.party_id}` === partyKey);
        creditForm.setData({
            party_key: partyKey,
            credit_days: party?.credit_days != null ? String(party.credit_days) : '',
            credit_limit: party?.credit_limit != null ? String(party.credit_limit) : '',
            credit_enabled: party?.credit_enabled ?? false,
            reminder_enabled: party?.reminder_enabled ?? true,
        });
    };

    const submitCredit = () => {
        if (!selectedParty) {
            return;
        }

        creditForm.transform((formData) => {
            const [partyType, partyId] = String(formData.party_key).split(':');
            return {
                ...formData,
                party_type: partyType,
                party_id: partyId,
            };
        });

        creditForm.post(route('textile.payments.credit.update'), {
            preserveScroll: true,
            onSuccess: () => {
                creditForm.setData('reminder_enabled', creditForm.data.reminder_enabled);
            },
        });
    };

    const applyFilter = (updates: { category?: string; search?: string }) => {
        const params: Record<string, string> = {};
        const category = updates.category ?? selectedCategory;
        const searchValue = updates.search !== undefined ? updates.search : search;

        if (category && category !== 'all') {
            params.category = category;
        }
        if (searchValue !== '') {
            params.search = searchValue;
        }

        router.get(route('textile.parties.index', params), {}, { preserveState: true, replace: true });
    };

    const counts = {
        total: parties.length,
        yarn: parties.filter((p) => p.party_type === 'supplier' && p.supplier_type === 'yarn').length,
        sizing: parties.filter((p) => p.party_type === 'supplier' && p.supplier_type === 'sizing').length,
        customers: parties.filter((p) => p.party_type === 'buyer').length,
        creditEnabled: parties.filter((p) => p.credit_enabled).length,
    };

    const canEditVendors = auth.user?.permissions?.includes('edit-vendors') ?? false;
    const canEditCustomers = auth.user?.permissions?.includes('edit-customers') ?? false;

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Parties') }]}
            pageTitle={t('Parties')}
        >
            <Head title={t('Parties')} />

            <div className="space-y-6">
                <TextileSection
                    kpis={[
                        { label: t('Parties'), value: counts.total, icon: Users },
                        { label: t('Yarn Suppliers'), value: counts.yarn, icon: Factory },
                        { label: t('Sizing Vendors'), value: counts.sizing, icon: Wallet },
                        { label: t('Customers'), value: counts.customers, icon: Building2 },
                        { label: t('Credit Enabled'), value: counts.creditEnabled, icon: Check },
                    ]}
                    formTitle={t('Party Filters')}
                    formIcon={Search}
                    form={
                        <div className="grid gap-4 md:grid-cols-2">
                            <SelectField
                                label={t('Category')}
                                value={selectedCategory}
                                onChange={(value) => applyFilter({ category: value })}
                                options={categoryOptions.map((option) => ({ value: option.value, label: t(option.label) }))}
                                helperText={t('Yarn suppliers, sizing vendors, powerloom vendors and customers.')}
                            />
                            <Field
                                label={t('Search')}
                                type="text"
                                value={search}
                                onChange={(value: string) => applyFilter({ search: value })}
                                helperText={t('Filter by party name or code.')}
                            />
                        </div>
                    }
                    table={
                        <TextileDataTableCard
                            data={parties}
                            columns={[
                                {
                                    key: 'party_name',
                                    header: t('Party'),
                                    render: (_value: unknown, row: PartyMaster) => (
                                        <div>
                                            <div className="font-medium text-gray-900">{row.party_name}</div>
                                            {row.party_code && <div className="text-xs text-gray-400">{row.party_code}</div>}
                                        </div>
                                    ),
                                },
                                {
                                    key: 'category',
                                    header: t('Type'),
                                    render: (_value: unknown, row: PartyMaster) => <CategoryBadge party={row} />,
                                },
                                {
                                    key: 'branch_name',
                                    header: t('Branch'),
                                    render: (_value: unknown, row: PartyMaster) => row.branch_name ?? t('Unassigned'),
                                },
                                {
                                    key: 'credit_enabled',
                                    header: t('Credit'),
                                    render: (_value: unknown, row: PartyMaster) => (row.credit_enabled ? (
                                        <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{t('On')}</span>
                                    ) : (
                                        <span className="text-gray-400">{t('Off')}</span>
                                    )),
                                },
                                {
                                    key: 'credit_days',
                                    header: t('Credit Days'),
                                    render: (_value: unknown, row: PartyMaster) => row.credit_days ?? '-',
                                },
                                {
                                    key: 'credit_limit',
                                    header: t('Credit Limit'),
                                    render: (_value: unknown, row: PartyMaster) => formatCurrency(row.credit_limit),
                                },
                                {
                                    key: 'reminder_enabled',
                                    header: t('Reminders'),
                                    render: (_value: unknown, row: PartyMaster) => (row.reminder_enabled ? (
                                        <span className="text-emerald-600">{t('On')}</span>
                                    ) : (
                                        <span className="text-gray-400">{t('Off')}</span>
                                    )),
                                },
                                {
                                    key: 'actions',
                                    header: t('Actions'),
                                    render: (_value: unknown, row: PartyMaster) => {
                                        const canEdit = row.party_type === 'supplier' ? canEditVendors : canEditCustomers;
                                        if (!canEdit) {
                                            return null;
                                        }

                                        return (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    const url = row.party_type === 'supplier'
                                                        ? route('vendors.index', { supplier_type: row.supplier_type ?? '' })
                                                        : route('account.customers.index');
                                                    router.visit(url);
                                                }}
                                            >
                                                {t('Open Master')}
                                            </Button>
                                        );
                                    },
                                },
                            ]}
                        />
                    }
                />

                <TextileFormCard title={t('Credit & Reminder Settings')} icon={Wallet}>
                    <div className="grid gap-4">
                        <SelectField
                            label={t('Party')}
                            value={creditForm.data.party_key}
                            onChange={(value: string) => selectParty(value)}
                            options={parties.map((party) => ({
                                value: `${party.party_type}:${party.party_id}`,
                                label: `${party.party_name} (${party.party_type === 'supplier' ? t('Supplier') : t('Buyer')})`,
                            }))}
                            includeEmpty
                            emptyLabel={t('Select a party')}
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
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={creditForm.data.credit_enabled}
                                onChange={(e) => creditForm.setData('credit_enabled', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-emerald-600"
                            />
                            {t('Credit enabled for this party (allow payment within credit days)')}
                        </label>
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
                </TextileFormCard>
            </div>
        </AuthenticatedLayout>
    );
}
