import { Head, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { SlidersHorizontal } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    options: {
        operatingModels: string[];
        materialOwnership: string[];
        billingModes: string[];
    };
    isSuperadmin: boolean;
    selectedCompanyId: number | null;
    companies: CompanyOption[];
}

export default function Index({ policy, capabilities, options, isSuperadmin, selectedCompanyId, companies }: Props) {
    const { t } = useTranslation();

    const form = useForm({
        company_id: selectedCompanyId ? String(selectedCompanyId) : '',
        operating_model: policy.operating_model,
        material_ownership: policy.material_ownership,
        billing_mode: policy.billing_mode,
    });

    const capabilityRows = useMemo(
        () => [
            ['procurement', t('Procurement')],
            ['manufacturing', t('Manufacturing')],
            ['processing', t('Processing')],
            ['grn_invoice_sync', t('GRN to Invoice Sync')],
        ],
        [t]
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
                                label={t('Operating Model')}
                                value={form.data.operating_model}
                                onChange={(value) => form.setData('operating_model', value)}
                                options={options.operatingModels.map((item) => ({ value: item, label: item }))}
                                includeEmpty
                                required
                            />

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

                            <Button type="submit" disabled={form.processing} className="w-full">
                                {t('Save Operating Model')}
                            </Button>
                        </form>
                </TextileFormCard>

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
            </div>
        </AuthenticatedLayout>
    );
}
