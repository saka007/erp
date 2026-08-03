import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BarChart3, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileDataTableCard } from '@/components/textile/textile-data-table-card';
import NoRecordsFound from '@/components/no-records-found';

interface VendorOption {
    id: number;
    company_name: string;
    vendor_code: string;
}

interface SnapshotRow {
    id: number;
    vendor_id: number;
    period_month: string;
    rating_count: number;
    avg_quality_score: string;
    avg_delivery_score: string;
    avg_service_score: string;
    avg_price_score: string;
    avg_overall_score: string;
    remarks?: string | null;
    vendor?: { company_name: string; vendor_code: string };
}

export default function Index({ snapshots, vendors }: { snapshots: SnapshotRow[]; vendors: VendorOption[] }) {
    const { t } = useTranslation();

    const vendorOptions = vendors.map((vendor) => ({
        value: String(vendor.id),
        label: `${vendor.company_name} (${vendor.vendor_code})`,
    }));

    const createForm = useForm({
        vendor_id: '',
        period_month: new Date().toISOString().slice(0, 7),
        remarks: '',
    });

    const deleteForm = useForm({});

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post(route('account.vendor-performance.store'), {
            onSuccess: () => createForm.reset('remarks'),
        });
    };

    const destroy = (id: number) => {
        deleteForm.delete(route('account.vendor-performance.destroy', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Textile') }, { label: t('Master Setup') }, { label: t('Supplier Setup') }, { label: t('Vendor Performance') }]}
            pageTitle={t('Vendor Performance')}
        >
            <Head title={t('Vendor Performance')} />
            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Generate Monthly Performance Snapshot')} icon={BarChart3} contentClassName="p-5 space-y-6">
                    <form onSubmit={submitCreate} className="space-y-4">
                        <SelectField label={t('Vendor')} value={createForm.data.vendor_id} onChange={(value) => createForm.setData('vendor_id', value)} options={vendorOptions} includeEmpty emptyLabel={t('Select vendor')} required />
                        <Field label={t('Period Month')} type="month" value={createForm.data.period_month} onChange={(value) => createForm.setData('period_month', value)} required />
                        <Field label={t('Remarks')} value={createForm.data.remarks} onChange={(value) => createForm.setData('remarks', value)} />
                        <Button type="submit" disabled={createForm.processing} className="w-full">{t('Generate Snapshot')}</Button>
                    </form>
                </TextileFormCard>

                <TextileDataTableCard
                    data={snapshots}
                    columns={[
                        { key: 'vendor', header: t('Vendor'), render: (_value: unknown, row: SnapshotRow) => row.vendor ? `${row.vendor.company_name} (${row.vendor.vendor_code})` : '-' },
                        { key: 'period_month', header: t('Period') },
                        { key: 'rating_count', header: t('Ratings') },
                        { key: 'avg_quality_score', header: t('Quality') },
                        { key: 'avg_delivery_score', header: t('Delivery') },
                        { key: 'avg_service_score', header: t('Service') },
                        { key: 'avg_price_score', header: t('Price') },
                        { key: 'avg_overall_score', header: t('Overall') },
                        { key: 'remarks', header: t('Remarks'), render: (value: unknown) => String(value || '-') },
                        {
                            key: 'actions',
                            header: t('Actions'),
                            render: (_value: unknown, row: SnapshotRow) => (
                                <Button type="button" variant="destructive" size="sm" onClick={() => destroy(row.id)}>
                                    <Trash2 className="mr-1 h-3.5 w-3.5" />
                                    {t('Delete')}
                                </Button>
                            ),
                        },
                    ]}
                    emptyState={<NoRecordsFound icon={BarChart3} title={t('No vendor performance snapshots found')} description={t('Generate monthly snapshots from active vendor ratings.')} />}
                />
            </div>
        </AuthenticatedLayout>
    );
}
