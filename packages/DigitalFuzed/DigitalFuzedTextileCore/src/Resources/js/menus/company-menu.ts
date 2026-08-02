import { Factory } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const textileCoreCompanyMenu = (t: (key: string) => string) => [
    {
        name: 'textile-operations',
        title: t('Daily Operations'),
        icon: Factory,
        order: 260,
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
                        title: t('Warp Planning'),
                        href: route('textile.manufacturing.index', { section: 'warp-planning' }),
                    },
                    {
                        title: t('Beam and Batch'),
                        href: route('textile.manufacturing.index', { section: 'beam-batch' }),
                    },
                    {
                        title: t('Loom Management'),
                        href: route('textile.manufacturing.index', { section: 'loom-management' }),
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
                children: [
                    {
                        title: t('Inspection'),
                        href: route('textile.quality.index'),
                    },
                    {
                        title: t('Hold and Release'),
                        href: route('textile.quality.index'),
                    },
                ],
            },
            {
                title: t('Processing'),
                href: route('textile.processing.index'),
                capability: 'processing',
                children: [
                    {
                        title: t('Job Work Outward'),
                        href: route('textile.processing.index'),
                    },
                    {
                        title: t('Processing Batch'),
                        href: route('textile.processing.index'),
                    },
                    {
                        title: t('Job Work Inward'),
                        href: route('textile.processing.index'),
                    },
                    {
                        title: t('Reconciliation'),
                        href: route('textile.processing.index'),
                    },
                ],
            },
        ],
    },
    {
        name: 'textile-master-setup',
        title: t('Master Setup'),
        icon: Factory,
        order: 261,
        children: [
                    {
                        title: t('Core Setup'),
                        children: [
                            {
                                title: t('Specifications'),
                                href: route('textile.specifications.index'),
                            },
                            {
                                title: t('Unit Conversions'),
                                href: route('textile.unit-conversions.index'),
                            },
                            {
                                title: t('Route Recipes'),
                                href: route('textile.route-recipes.index'),
                            },
                            {
                                title: t('Quality Profiles'),
                                href: route('textile.quality-profiles.index'),
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
                        title: t('Manufacturing Setup'),
                        children: [
                            {
                                title: t('Source Types'),
                                href: route('textile.master-domains.source-types.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Source Actions'),
                                href: route('textile.master-domains.source-actions.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Machine Types'),
                                href: route('textile.master-domains.machine-types.index', { domain: 'manufacturing' }),
                            },
                        ],
                    },
                    {
                        title: t('Inventory Setup'),
                        children: [
                            {
                                title: t('Source Types'),
                                href: route('textile.master-domains.source-types.index', { domain: 'inventory' }),
                            },
                            {
                                title: t('Source Actions'),
                                href: route('textile.master-domains.source-actions.index', { domain: 'inventory' }),
                            },
                        ],
                    },
                    {
                        title: t('Sales Setup'),
                        children: [
                            {
                                title: t('Source Types'),
                                href: route('textile.master-domains.source-types.index', { domain: 'sales' }),
                            },
                            {
                                title: t('Source Actions'),
                                href: route('textile.master-domains.source-actions.index', { domain: 'sales' }),
                            },
                        ],
                    },
                    {
                        title: t('Processing Setup'),
                        children: [
                            {
                                title: t('Source Types'),
                                href: route('textile.master-domains.source-types.index', { domain: 'processing' }),
                            },
                            {
                                title: t('Source Actions'),
                                href: route('textile.master-domains.source-actions.index', { domain: 'processing' }),
                            },
                        ],
                    },
                    {
                        title: t('Quality Setup'),
                        children: [
                            {
                                title: t('Source Types'),
                                href: route('textile.master-domains.source-types.index', { domain: 'quality' }),
                            },
                            {
                                title: t('Source Actions'),
                                href: route('textile.master-domains.source-actions.index', { domain: 'quality' }),
                            },
                        ],
                    },
                ],
            },
    {
        name: 'textile-insights',
        title: t('Insights'),
        icon: Factory,
        order: 262,
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
];