import { Factory } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const textileCoreCompanyMenu = (t: (key: string) => string) => [
    {
        name: 'textile',
        title: t('Textile ERP'),
        icon: Factory,
        order: 260,
        children: [
            {
                name: 'textile-operations',
                title: t('Daily Operations'),
                order: 10,
                children: [
                    {
                        title: t('Procurement'),
                        href: route('textile.procurement.index'),
                        capability: 'procurement',
                        children: [
                            {
                                title: t('Requisitions'),
                                href: route('textile.procurement.index', { section: 'requisitions' }),
                            },
                            {
                                title: t('Purchase Orders'),
                                href: route('textile.procurement.index', { section: 'purchase-orders' }),
                            },
                            {
                                title: t('GRN'),
                                href: route('textile.procurement.index', { section: 'grns' }),
                            },
                            {
                                title: t('Incoming QC'),
                                href: route('textile.procurement.index', { section: 'incoming-qc' }),
                            },
                        ],
                    },
                    {
                        title: t('Sales'),
                        href: route('textile.sales.index'),
                        children: [
                            {
                                title: t('Sales Orders'),
                                href: route('textile.sales.index', { section: 'sales-order' }),
                            },
                            {
                                title: t('Allocation and Dispatch'),
                                href: route('textile.sales.index', { section: 'allocation-dispatch' }),
                            },
                            {
                                title: t('Challan and POD'),
                                href: route('textile.sales.index', { section: 'challan-pod' }),
                            },
                        ],
                    },
                    {
                        title: t('Manufacturing'),
                        href: route('textile.manufacturing.index'),
                        capability: 'manufacturing',
                        children: [
                            {
                                title: t('Beam and Batch'),
                                href: route('textile.manufacturing.index', { section: 'beam-batch' }),
                            },
                            {
                                title: t('Weaving Output'),
                                href: route('textile.manufacturing.index', { section: 'weaving-output' }),
                            },
                            {
                                title: t('Waste'),
                                href: route('textile.manufacturing.index', { section: 'waste' }),
                            },
                            {
                                title: t('Rework'),
                                href: route('textile.manufacturing.index', { section: 'rework' }),
                            },
                        ],
                    },
                    {
                        title: t('Quality'),
                        href: route('textile.quality.index'),
                    },
                    {
                        title: t('Processing'),
                        href: route('textile.processing.index'),
                        capability: 'processing',
                    },
                ],
            },
            {
                name: 'textile-setup',
                title: t('Master Setup'),
                order: 20,
                children: [
                    {
                        title: t('Specifications'),
                        href: route('textile.specifications.index'),
                    },
                    {
                        title: t('Quality Profiles'),
                        href: route('textile.quality-profiles.index'),
                    },
                    {
                        title: t('Route Recipes'),
                        href: route('textile.route-recipes.index'),
                    },
                    {
                        title: t('Unit Conversions'),
                        href: route('textile.unit-conversions.index'),
                    },
                    {
                        title: t('Approvals'),
                        href: route('textile.approvals.index'),
                    },
                    {
                        title: t('Cost Centers'),
                        href: route('textile.cost-centers.index'),
                    },
                    {
                        title: t('Operating Model'),
                        href: route('textile.operating-policy.index'),
                    },
                ],
            },
            {
                name: 'textile-insights',
                title: t('Insights'),
                order: 30,
                children: [
                    {
                        title: t('Dashboard'),
                        href: route('textile.dashboard.index'),
                    },
                    {
                        title: t('Logs'),
                        href: route('textile.logs.index'),
                    },
                    {
                        title: t('Costing'),
                        href: route('textile.costing.index'),
                    },
                ],
            },
        ],
    },
];