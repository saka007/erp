import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Building2, GitBranch, Plus, Trash2, Users } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Badge } from '@/components/ui/badge';
import { MultiSelectEnhanced } from '@/components/ui/multi-select-enhanced';
import NoRecordsFound from '@/components/no-records-found';

type PartyRow = {
    id: number;
    name: string;
    is_active: boolean;
    assigned_branch_ids: number[];
};

type BranchOption = {
    id: number;
    name: string;
};

type IndexProps = {
    vendors: PartyRow[];
    customers: PartyRow[];
    branchOptions: BranchOption[];
};

export default function Index({ vendors, customers, branchOptions }: IndexProps) {
    const { t } = useTranslation();
    const branchOptionsForSelect = branchOptions.map((branch) => ({
        value: String(branch.id),
        label: branch.name,
    }));
    const branchName = (id: number) => branchOptions.find((branch) => branch.id === id)?.name ?? `#${id}`;

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('Party Branch Assignment') }]}
            pageTitle={t('Party Branch Assignment')}
        >
            <Head title={t('Party Branch Assignment')} />
            <div className="grid gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GitBranch className="h-5 w-5" />
                            {t('Party Branch Assignment')}
                        </CardTitle>
                        <CardDescription>
                            {t('Assign vendors and customers to specific branches. Parties with no assignment remain visible in all branches.')}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <PartySection
                    title={t('Vendors')}
                    icon={Users}
                    rows={vendors}
                    branchOptions={branchOptionsForSelect}
                    branchName={branchName}
                    partyType="vendor"
                />

                <PartySection
                    title={t('Customers')}
                    icon={Building2}
                    rows={customers}
                    branchOptions={branchOptionsForSelect}
                    branchName={branchName}
                    partyType="customer"
                />
            </div>
        </AuthenticatedLayout>
    );
}

function PartySection({
    title,
    icon: Icon,
    rows,
    branchOptions,
    branchName,
    partyType,
}: {
    title: string;
    icon: typeof Users;
    rows: PartyRow[];
    branchOptions: { value: string; label: string }[];
    branchName: (id: number) => string;
    partyType: 'vendor' | 'customer';
}) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<number[]>([]);
    const [branches, setBranches] = useState<string[]>([]);
    const [processing, setProcessing] = useState<'assign' | 'remove' | null>(null);

    const allSelected = rows.length > 0 && selected.length === rows.length;

    const toggleAll = () => {
        setSelected(allSelected ? [] : rows.map((row) => row.id));
    };

    const toggleRow = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
    };

    const submitAction = (action: 'assign' | 'remove') => {
        if (selected.length === 0 || branches.length === 0) {
            return;
        }

        setProcessing(action);
        router.post(route(`textile.party-branches.${action}`), {
            party_type: partyType,
            party_ids: selected,
            branch_ids: branches.map(Number),
        }, {
            preserveScroll: true,
            onFinish: () => setProcessing(null),
        });
    };

    const columns = [
        {
            key: 'select',
            header: t('Select'),
            render: (_value: unknown, row: PartyRow) => (
                <input
                    type="checkbox"
                    checked={selected.includes(row.id)}
                    onChange={() => toggleRow(row.id)}
                    className="h-4 w-4 rounded border-border"
                />
            ),
        },
        { key: 'name', header: t('Name') },
        {
            key: 'is_active',
            header: t('Status'),
            render: (value: boolean) => (
                value
                    ? <Badge variant="default">{t('Active')}</Badge>
                    : <Badge variant="secondary">{t('Inactive')}</Badge>
            ),
        },
        {
            key: 'assigned_branch_ids',
            header: t('Assigned Branches'),
            render: (value: number[]) => {
                const ids = Array.isArray(value) ? value : [];
                if (ids.length === 0) {
                    return <Badge variant="outline">{t('All Branches')}</Badge>;
                }

                return (
                    <div className="flex flex-wrap gap-1">
                        {ids.map((id) => (
                            <Badge key={id} variant="secondary">{branchName(id)}</Badge>
                        ))}
                    </div>
                );
            },
        },
    ];

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Icon className="h-5 w-5" />
                        {title}
                    </CardTitle>
                    <CardDescription>
                        {t('Select parties below, then choose branches to assign or remove.')}
                    </CardDescription>
                </div>
                {rows.length > 0 ? (
                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={allSelected}
                            onChange={toggleAll}
                            className="h-4 w-4 rounded border-border"
                        />
                        {t('Select all')} ({selected.length})
                    </label>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto_auto]">
                    <MultiSelectEnhanced
                        options={branchOptions}
                        value={branches}
                        onValueChange={setBranches}
                        placeholder={t('Select branches...')}
                        searchable
                    />
                    <Button
                        type="button"
                        disabled={processing !== null || selected.length === 0 || branches.length === 0}
                        onClick={() => submitAction('assign')}
                    >
                        <Plus className="mr-1 h-4 w-4" />
                        {t('Assign')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing !== null || selected.length === 0 || branches.length === 0}
                        onClick={() => submitAction('remove')}
                    >
                        <Trash2 className="mr-1 h-4 w-4" />
                        {t('Remove')}
                    </Button>
                </div>

                <DataTable
                    data={rows}
                    columns={columns}
                    searchable
                    searchPlaceholder={t('Search parties...')}
                    pageSize={10}
                    showPagination
                    emptyState={
                        <NoRecordsFound
                            icon={Icon}
                            title={t(`No ${title.toLowerCase()} found`)}
                            description={t('Parties from the Account module appear here.')}
                        />
                    }
                />
            </CardContent>
        </Card>
    );
}
