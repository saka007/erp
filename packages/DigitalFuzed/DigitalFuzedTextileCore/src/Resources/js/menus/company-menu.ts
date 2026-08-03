import { Factory } from 'lucide-react';

declare global {
    function route(name: string, params?: Record<string, unknown>): string;
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
                        title: t('RFQ'),
                        href: route('textile.procurement.index', { section: 'rfqs' }),
                    },
                    {
                        title: t('GRN'),
                        href: route('textile.procurement.index', { section: 'grns' }),
                    },
                    {
                        title: t('Incoming QC'),
                        href: route('textile.procurement.index', { section: 'incoming-qc' }),
                    },
                    {
                        title: t('Supplier Claims'),
                        href: route('textile.procurement.index', { section: 'supplier-claims' }),
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
                        title: t('Sizing and Chemical'),
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
                                title: t('Custom Fields'),
                                href: route('textile.custom-fields.index'),
                            },
                            {
                                title: t('Operating Model'),
                                href: route('textile.operating-policy.index'),
                            },
                        ],
                    },
                    {
                        title: t('Manufacturing Source Setup'),
                        children: [
                            {
                                title: t('Source Types'),
                                href: route('textile.master-domains.source-types.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Source Actions'),
                                href: route('textile.master-domains.source-actions.index', { domain: 'manufacturing' }),
                            },
                        ],
                    },
                    {
                        title: t('Loom Setup'),
                        children: [
                            {
                                title: t('Machine Types'),
                                href: route('textile.master-domains.machine-types.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Shed Types'),
                                href: route('textile.master-domains.shed-types.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Loom Statuses'),
                                href: route('textile.master-domains.loom-statuses.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Breakdown Reasons'),
                                href: route('textile.master-domains.breakdown-reasons.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Maintenance Types'),
                                href: route('textile.master-domains.maintenance-types.index', { domain: 'manufacturing' }),
                            },
                        ],
                    },
                    {
                        title: t('Beam and Cost Setup'),
                        children: [
                            {
                                title: t('Cost Types'),
                                href: route('textile.master-domains.cost-types.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Inspection Results'),
                                href: route('textile.master-domains.inspection-results.index', { domain: 'manufacturing' }),
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
                    {
                        title: t('CRM Setup'),
                        children: [
                            {
                                title: t('Customer Categories'),
                                href: route('account.customer-categories.index'),
                            },
                            {
                                title: t('Customer Price List'),
                                href: route('account.customer-price-lists.index'),
                            },
                            {
                                title: t('Customer Contacts'),
                                href: route('account.customer-contacts.index'),
                            },
                            {
                                title: t('Customer Follow Ups'),
                                href: route('account.customer-follow-ups.index'),
                            },
                            {
                                title: t('Customer Documents'),
                                href: route('account.customer-documents.index'),
                            },
                        ],
                    },
                    {
                        title: t('Supplier Setup'),
                        children: [
                            {
                                title: t('Vendor Ratings'),
                                href: route('account.vendor-ratings.index'),
                            },
                            {
                                title: t('Vendor Performance'),
                                href: route('account.vendor-performance.index'),
                            },
                        ],
                    },
                    {
                        title: t('Product Setup'),
                        children: [
                            {
                                title: t('Product Variants'),
                                href: route('product-service.product-master.variants.index'),
                            },
                            {
                                title: t('Product Images'),
                                href: route('product-service.product-master.images.index'),
                            },
                            {
                                title: t('Product Documents'),
                                href: route('product-service.product-master.documents.index'),
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