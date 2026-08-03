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
                        capability: 'procurement_requisition',
                    },
                    {
                        title: t('Purchase Orders'),
                        href: route('textile.procurement.index', { section: 'purchase-orders' }),
                        capability: 'procurement_purchase_order',
                    },
                    {
                        title: t('RFQ'),
                        href: route('textile.procurement.index', { section: 'rfqs' }),
                        capability: 'procurement_rfq',
                    },
                    {
                        title: t('GRN'),
                        href: route('textile.procurement.index', { section: 'grns' }),
                        capability: 'procurement_grn',
                    },
                    {
                        title: t('Incoming QC'),
                        href: route('textile.procurement.index', { section: 'incoming-qc' }),
                        capability: 'procurement_incoming_qc',
                    },
                    {
                        title: t('Supplier Claims'),
                        href: route('textile.procurement.index', { section: 'supplier-claims' }),
                        capability: 'procurement_supplier_claims',
                    },
                ],
            },
            {
                title: t('Sales'),
                href: route('textile.sales.index'),
                capability: 'sales',
                children: [
                    {
                        title: t('Sales Orders'),
                        href: route('textile.sales.index', { section: 'sales-order' }),
                        capability: 'sales_order',
                    },
                    {
                        title: t('Allocation and Dispatch'),
                        href: route('textile.sales.index', { section: 'allocation-dispatch' }),
                        capability: 'sales_allocation_dispatch',
                    },
                    {
                        title: t('Challan and POD'),
                        href: route('textile.sales.index', { section: 'challan-pod' }),
                        capability: 'sales_challan_pod',
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
                        capability: 'manufacturing_warping',
                    },
                    {
                        title: t('Sizing and Chemical'),
                        href: route('textile.manufacturing.index', { section: 'warp-planning' }),
                        capability: 'manufacturing_sizing',
                    },
                    {
                        title: t('Beam and Batch'),
                        href: route('textile.manufacturing.index', { section: 'beam-batch' }),
                        capability: 'manufacturing_beam',
                    },
                    {
                        title: t('Loom Management'),
                        href: route('textile.manufacturing.index', { section: 'loom-management' }),
                        capability: 'manufacturing_loom',
                    },
                    {
                        title: t('Production Planning'),
                        href: route('textile.manufacturing.index', { section: 'machine-planning' }),
                        capability: 'manufacturing_planning',
                    },
                    {
                        title: t('Weaving Production'),
                        href: route('textile.manufacturing.index', { section: 'weaving-output' }),
                        capability: 'manufacturing_weaving',
                        children: [
                            {
                                title: t('Domain 13: Weaving Production'),
                                href: route('textile.manufacturing.index', { section: 'weaving-output', sub: 'domain-13' }),
                                capability: 'manufacturing_weaving',
                            },
                            {
                                title: t('Domain 14: Grey Fabric'),
                                href: route('textile.manufacturing.index', { section: 'weaving-output', sub: 'domain-14' }),
                                capability: 'manufacturing_weaving',
                            },
                        ],
                    },
                    {
                        title: t('Waste'),
                        href: route('textile.manufacturing.index', { section: 'waste' }),
                        capability: 'manufacturing_waste',
                    },
                    {
                        title: t('Rework'),
                        href: route('textile.manufacturing.index', { section: 'rework' }),
                        capability: 'manufacturing_rework',
                    },
                ],
            },
            {
                title: t('Quality'),
                href: route('textile.quality.index'),
                capability: 'quality',
                children: [
                    {
                        title: t('Incoming QC'),
                        href: route('textile.procurement.index', { section: 'incoming-qc' }),
                        capability: 'quality_inspection',
                    },
                    {
                        title: t('Process QC'),
                        href: route('textile.quality.index', { section: 'inspection', qc_stage: 'in_process_qc' }),
                        capability: 'quality_inspection',
                    },
                    {
                        title: t('Final QC'),
                        href: route('textile.quality.index', { section: 'inspection', qc_stage: 'final_qc' }),
                        capability: 'quality_inspection',
                    },
                    {
                        title: t('Shade Matching'),
                        href: route('textile.quality.index', { section: 'inspection', qc_stage: 'shade_matching' }),
                        capability: 'quality_inspection',
                    },
                    {
                        title: t('Fabric Inspection'),
                        href: route('textile.quality.index', { section: 'inspection' }),
                        capability: 'quality_inspection',
                    },
                    {
                        title: t('Reject'),
                        href: route('textile.quality.index', { section: 'inspection', decision: 'fail' }),
                        capability: 'quality_inspection',
                    },
                    {
                        title: t('Hold and Release'),
                        href: route('textile.quality.index', { section: 'hold-release' }),
                        capability: 'quality_hold_release',
                    },
                    {
                        title: t('Quality Certificates'),
                        href: route('textile.quality.index', { section: 'certificates' }),
                        capability: 'quality_inspection',
                    },
                ],
            },
            {
                title: t('Packing'),
                href: route('textile.packing.index'),
                capability: 'sales',
                children: [
                    {
                        title: t('Roll Packing'),
                        href: route('textile.packing.index', { section: 'roll-packing' }),
                        capability: 'sales_challan_pod',
                    },
                    {
                        title: t('Bundle Packing'),
                        href: route('textile.packing.index', { section: 'bundle-packing' }),
                        capability: 'sales_challan_pod',
                    },
                    {
                        title: t('Bale Packing'),
                        href: route('textile.packing.index', { section: 'bale-packing' }),
                        capability: 'sales_challan_pod',
                    },
                    {
                        title: t('Labels'),
                        href: route('textile.packing.index', { section: 'labels' }),
                        capability: 'sales_challan_pod',
                    },
                ],
            },
            {
                title: t('Processing'),
                href: route('textile.processing.index'),
                capability: 'processing',
                children: [
                    {
                        title: t('Internal Processing'),
                        href: route('textile.processing.index', { section: 'internal-processing' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Job Work Outward'),
                        href: route('textile.processing.index', { section: 'job-work-outward' }),
                        capability: 'processing_outward',
                    },
                    {
                        title: t('Processing Batch'),
                        href: route('textile.processing.index', { section: 'processing-batch' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Dyeing'),
                        href: route('textile.processing.index', { section: 'dyeing' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Printing'),
                        href: route('textile.processing.index', { section: 'printing' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Bleaching'),
                        href: route('textile.processing.index', { section: 'bleaching' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Calendaring'),
                        href: route('textile.processing.index', { section: 'calendaring' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Compacting'),
                        href: route('textile.processing.index', { section: 'compacting' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Finishing'),
                        href: route('textile.processing.index', { section: 'finishing' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Shade Card'),
                        href: route('textile.processing.index', { section: 'shade-card' }),
                        capability: 'processing_inward',
                    },
                    {
                        title: t('Process Cost'),
                        href: route('textile.processing.index', { section: 'process-cost' }),
                        capability: 'processing_batch',
                    },
                    {
                        title: t('Job Work Inward'),
                        href: route('textile.processing.index', { section: 'job-work-inward' }),
                        capability: 'processing_inward',
                    },
                    {
                        title: t('Reconciliation'),
                        href: route('textile.processing.index', { section: 'reconciliation' }),
                        capability: 'processing_reconciliation',
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
                            {
                                title: t('Fabric Defects'),
                                href: route('textile.master-domains.fabric-defects.index', { domain: 'manufacturing' }),
                            },
                            {
                                title: t('Fabric Grades'),
                                href: route('textile.master-domains.fabric-grades.index', { domain: 'manufacturing' }),
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
                            {
                                title: t('Inspection Results'),
                                href: route('textile.master-domains.inspection-results.index', { domain: 'quality' }),
                            },
                            {
                                title: t('Fabric Defects'),
                                href: route('textile.master-domains.fabric-defects.index', { domain: 'quality' }),
                            },
                        ],
                    },
                    {
                        title: t('Packing Setup'),
                        children: [
                            {
                                title: t('Source Types'),
                                href: route('textile.master-domains.source-types.index', { domain: 'packing' }),
                            },
                            {
                                title: t('Source Actions'),
                                href: route('textile.master-domains.source-actions.index', { domain: 'packing' }),
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