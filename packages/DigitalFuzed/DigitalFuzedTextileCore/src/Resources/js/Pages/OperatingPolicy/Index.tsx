import { Head, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { SlidersHorizontal } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CheckboxGroup } from '@/components/ui/checkbox-group';
import { TextileFormCard } from '@/components/textile/textile-form-card';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';

interface CompanyOption {
    id: number;
    name: string;
    email: string;
}

interface Policy {
    operating_model: string;
    material_ownership: string;
    billing_mode: string;
}

interface Props {
    policy: Policy;
    capabilities: Record<string, boolean>;
    settings: Record<string, boolean>;
    activeProfiles: string[];
    profileHistory: Array<{
        id: number;
        profile_key: string;
        is_active: boolean;
        effective_from: string | null;
        effective_to: string | null;
    }>;
    options: {
        operatingModels: string[];
        operatingProfiles: string[];
        materialOwnership: string[];
        billingModes: string[];
        settings: string[];
    };
    isSuperadmin: boolean;
    selectedCompanyId: number | null;
    companies: CompanyOption[];
}

export default function Index({ policy, capabilities, settings, activeProfiles, profileHistory, options, isSuperadmin, selectedCompanyId, companies }: Props) {
    const { t } = useTranslation();

    const form = useForm({
        company_id: selectedCompanyId ? String(selectedCompanyId) : '',
        operating_model: policy.operating_model,
        operating_profiles: activeProfiles,
        material_ownership: policy.material_ownership,
        billing_mode: policy.billing_mode,
        settings: Object.entries(settings).filter(([, enabled]) => enabled).map(([key]) => key),
    });

    const capabilityRows = useMemo(
        () => [
            ['procurement', t('Procurement')],
            ['manufacturing', t('Manufacturing')],
            ['processing', t('Processing')],
            ['quality', t('Quality')],
            ['sales', t('Sales')],
            ['inventory', t('Inventory')],
            ['grn_invoice_sync', t('GRN (Goods Received Note) to Invoice Sync')],
            ['procurement_requisition', t('Procurement Requisition')],
            ['procurement_rfq', t('Procurement RFQ (Request for Quotation)')],
            ['procurement_purchase_order', t('Procurement Purchase Order')],
            ['procurement_grn', t('Procurement GRN (Goods Received Note)')],
            ['procurement_incoming_qc', t('Procurement Incoming QC')],
            ['procurement_supplier_claims', t('Procurement Supplier Claims')],
            ['processing_outward', t('Processing Outward')],
            ['processing_batch', t('Processing Batch')],
            ['processing_inward', t('Processing Inward')],
            ['processing_reconciliation', t('Processing Reconciliation')],
            ['quality_inspection', t('Quality Inspection')],
            ['quality_hold_release', t('Quality Hold/Release')],
            ['sales_order', t('Sales Order')],
            ['sales_allocation_dispatch', t('Sales Allocation/Dispatch')],
            ['sales_challan_pod', t('Sales Challan/POD')],
            ['sales_dispatch_tracking', t('Sales Dispatch Tracking')],
            ['inventory_transactions', t('Inventory Transactions')],
            ['inventory_controls', t('Inventory Controls')],
            ['inventory_records', t('Inventory Records')],
            ['inventory_locations', t('Inventory Locations')],
            ['inventory_movements', t('Inventory Movements')],
            ['inventory_reservations', t('Inventory Reservations')],
            ['inventory_freeze', t('Inventory Freeze')],
            ['inventory_verification', t('Inventory Verification')],
            ['inventory_cycle_count', t('Inventory Cycle Count')],
            ['manufacturing_warping', t('Warping')],
            ['manufacturing_sizing', t('Sizing')],
            ['manufacturing_beam', t('Beam Management')],
            ['manufacturing_loom', t('Loom Management')],
            ['manufacturing_planning', t('Production Planning')],
            ['manufacturing_weaving', t('Weaving Output')],
            ['manufacturing_waste', t('Waste Tracking')],
            ['manufacturing_rework', t('Rework')],
            ['manufacturing_maintenance', t('Maintenance')],
        ],
        [t]
    );

    const settingRows = useMemo(
        () => options.settings.map((key) => ({
            value: key,
            label: key === 'has_transport_own'
                ? t('Own Transport')
                : key === 'has_transport_vendor'
                    ? t('Vendor Transport')
                    : key.replaceAll('_', ' '),
        })),
        [options.settings]
    );

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Textile') }, { label: t('Operating Model') }]} pageTitle={t('Textile Operating Model')}>
            <Head title={t('Textile Operating Model')} />

            <div className="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
                <TextileFormCard title={t('Policy Configuration')} icon={SlidersHorizontal}>
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(route('textile.operating-policy.update'));
                            }}
                        >
                            {isSuperadmin ? (
                                <SelectField
                                    label={t('Company')}
                                    value={form.data.company_id}
                                    onChange={(value) => {
                                        form.setData('company_id', value);
                                        router.get(route('textile.operating-policy.index', { company_id: value }), {}, { preserveState: true, replace: true });
                                    }}
                                    options={companies.map((company) => ({
                                        value: String(company.id),
                                        label: `${company.name} (${company.email})`,
                                    }))}
                                    includeEmpty
                                    required
                                />
                            ) : null}

                            <SelectField
                                label={t('Primary Operating Model')}
                                value={form.data.operating_model}
                                onChange={(value) => form.setData('operating_model', value)}
                                options={options.operatingModels.map((item) => ({ value: item, label: item }))}
                                includeEmpty
                                required
                            />

                            <div className="space-y-2">
                                <p className="text-sm font-medium text-foreground">{t('Enabled Operating Profiles')}</p>
                                <CheckboxGroup
                                    direction="vertical"
                                    options={options.operatingProfiles.map((item) => ({ value: item, label: item }))}
                                    value={form.data.operating_profiles}
                                    onValueChange={(value) => form.setData('operating_profiles', value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('Select one or more profiles. Capabilities are resolved across active profiles.')}
                                </p>
                                {form.errors.operating_profiles ? <p className="text-xs text-destructive">{form.errors.operating_profiles}</p> : null}
                            </div>

                            <SelectField
                                label={t('Material Ownership')}
                                value={form.data.material_ownership}
                                onChange={(value) => form.setData('material_ownership', value)}
                                options={options.materialOwnership.map((item) => ({ value: item, label: item }))}
                                includeEmpty
                                required
                            />

                            <SelectField
                                label={t('Billing Mode')}
                                value={form.data.billing_mode}
                                onChange={(value) => form.setData('billing_mode', value)}
                                options={options.billingModes.map((item) => ({ value: item, label: item }))}
                                includeEmpty
                                required
                            />

                            <div className="space-y-2">
                                <p className="text-sm font-medium text-foreground">{t('Operational Settings')}</p>
                                <CheckboxGroup
                                    direction="vertical"
                                    options={settingRows}
                                    value={form.data.settings}
                                    onValueChange={(value) => form.setData('settings', value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('Use these company-specific switches to show, hide, or block subflows such as sizing, loom planning, maintenance, and job-work paths.')}
                                </p>
                            </div>

                            <Button type="submit" disabled={form.processing} className="w-full">
                                {t('Save Operating Model')}
                            </Button>
                        </form>
                </TextileFormCard>

                <div className="space-y-6">
                    <Card>
                        <CardContent className="space-y-4 p-5">
                            <h2 className="font-semibold">{t('Resolved Capabilities')}</h2>
                            <div className="rounded-md border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/40 text-left">
                                            <th className="px-3 py-2">{t('Capability')}</th>
                                            <th className="px-3 py-2">{t('Allowed')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {capabilityRows.map(([key, label]) => (
                                            <tr key={key} className="border-b last:border-b-0">
                                                <td className="px-3 py-2">{label}</td>
                                                <td className="px-3 py-2">{capabilities[key] ? t('Yes') : t('No')}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-4 p-5">
                            <h2 className="font-semibold">{t('Profile History')}</h2>
                            <div className="rounded-md border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/40 text-left">
                                            <th className="px-3 py-2">{t('Profile')}</th>
                                            <th className="px-3 py-2">{t('Status')}</th>
                                            <th className="px-3 py-2">{t('Effective From')}</th>
                                            <th className="px-3 py-2">{t('Effective To')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {profileHistory.length === 0 ? (
                                            <tr>
                                                <td className="px-3 py-3 text-muted-foreground" colSpan={4}>
                                                    {t('No profile changes recorded yet.')}
                                                </td>
                                            </tr>
                                        ) : (
                                            profileHistory.map((item) => (
                                                <tr key={item.id} className="border-b last:border-b-0">
                                                    <td className="px-3 py-2">{item.profile_key}</td>
                                                    <td className="px-3 py-2">{item.is_active ? t('Active') : t('Inactive')}</td>
                                                    <td className="px-3 py-2">{item.effective_from || '-'}</td>
                                                    <td className="px-3 py-2">{item.effective_to || '-'}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
