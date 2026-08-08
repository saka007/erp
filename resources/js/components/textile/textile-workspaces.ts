import {
    AlertTriangle,
    BarChart3,
    Boxes,
    Briefcase,
    ClipboardCheck,
    ClipboardList,
    Droplets,
    Factory,
    FileQuestion,
    FileText,
    Fuel,
    Gauge,
    Layers,
    LayoutDashboard,
    type LucideIcon,
    Package,
    PackageCheck,
    PackageOpen,
    PaintBucket,
    PaintRoller,
    Receipt,
    Repeat,
    RotateCcw,
    Scissors,
    ScrollText,
    Settings,
    Shirt,
    ShoppingBag,
    ShoppingCart,
    SprayCan,
    TicketCheck,
    Truck,
    UserCheck,
    Wallet,
    Warehouse,
    Workflow,
    Wrench,
} from 'lucide-react';

export interface TextileSection {
    id: string;
    label: string;
    icon: LucideIcon;
    /** Fine-grained capability key (e.g. procurement_grn). Absent = visible (fail-open). */
    capability?: string;
}

export interface TextileWorkspace {
    id: string;
    title: string;
    routeName: string;
    capability?: string;
    sections: TextileSection[];
}

/**
 * Single source of truth for textile workspace pages.
 * Drives the sidebar menu and the in-page left-rail navigation so they never drift.
 */
export const textileWorkspaces: TextileWorkspace[] = [
    {
        id: 'procurement',
        title: 'Procurement',
        routeName: 'textile.procurement.index',
        capability: 'procurement',
        sections: [
            { id: 'overview', label: 'Overview', icon: LayoutDashboard },
            { id: 'requisitions', label: 'Requisitions', icon: ClipboardList, capability: 'procurement_requisition' },
            { id: 'rfqs', label: 'RFQ (Request for Quotation)', icon: FileQuestion, capability: 'procurement_rfq' },
            { id: 'purchase-orders', label: 'Purchase Orders', icon: FileText, capability: 'procurement_purchase_order' },
            { id: 'grns', label: 'GRN (Goods Received Note)', icon: PackageCheck, capability: 'procurement_grn' },
            { id: 'incoming-qc', label: 'Incoming QC', icon: ClipboardCheck, capability: 'procurement_incoming_qc' },
            { id: 'supplier-claims', label: 'Supplier Claims', icon: AlertTriangle, capability: 'procurement_supplier_claims' },
            { id: 'bills', label: 'Purchase Bills', icon: Receipt, capability: 'procurement' },
        ],
    },
    {
        id: 'inventory',
        title: 'Inventory',
        routeName: 'textile.inventory.index',
        capability: 'inventory',
        sections: [
            { id: 'overview', label: 'Overview', icon: LayoutDashboard },
            { id: 'yarn-stock', label: 'Yarn Stock', icon: Layers, capability: 'inventory' },
            { id: 'beam-stock', label: 'Beam Stock', icon: Boxes, capability: 'inventory' },
            { id: 'grey-fabric', label: 'Grey Fabric', icon: Package, capability: 'inventory' },
            { id: 'finished-fabric', label: 'Finished Fabric', icon: Shirt, capability: 'inventory' },
            { id: 'chemicals', label: 'Chemicals', icon: Droplets, capability: 'inventory' },
            { id: 'packing-materials', label: 'Packing Materials', icon: PackageOpen, capability: 'inventory' },
            { id: 'locations-controls', label: 'Locations & Controls', icon: Settings, capability: 'inventory_controls' },
        ],
    },
    {
        id: 'sales',
        title: 'Sales',
        routeName: 'textile.sales.index',
        capability: 'sales',
        sections: [
            { id: 'overview', label: 'Overview', icon: LayoutDashboard },
            { id: 'sales-order', label: 'Sales Order', icon: ShoppingBag, capability: 'sales_order' },
            { id: 'quotations', label: 'Quotations (Sauda)', icon: ScrollText, capability: 'sales' },
            { id: 'allocation-dispatch', label: 'Allocation & Dispatch', icon: Truck, capability: 'sales_allocation_dispatch' },
            { id: 'challan-pod', label: 'Challan & POD', icon: ScrollText, capability: 'sales_challan_pod' },
        ],
    },
    {
        id: 'manufacturing',
        title: 'Manufacturing',
        routeName: 'textile.manufacturing.index',
        capability: 'manufacturing',
        sections: [
            { id: 'overview', label: 'Overview', icon: LayoutDashboard },
            { id: 'warp-planning', label: 'Warp Planning', icon: Workflow },
            { id: 'beam-batch', label: 'Beam and Batch', icon: Layers },
            { id: 'loom-management', label: 'Loom Management', icon: Settings },
            { id: 'machine-planning', label: 'Production Planning', icon: Gauge },
            { id: 'weaving-output', label: 'Weaving Production', icon: Factory },
            { id: 'waste', label: 'Waste', icon: Scissors },
            { id: 'rework', label: 'Rework', icon: RotateCcw },
        ],
    },
    {
        id: 'quality',
        title: 'Quality',
        routeName: 'textile.quality.index',
        capability: 'quality',
        sections: [
            { id: 'overview', label: 'Overview', icon: LayoutDashboard },
            { id: 'inspection', label: 'Fabric Inspection', icon: ClipboardCheck, capability: 'quality_inspection' },
            { id: 'hold-release', label: 'Hold and Release', icon: TicketCheck, capability: 'quality_hold_release' },
            { id: 'certificates', label: 'Quality Certificates', icon: ScrollText, capability: 'quality_inspection' },
        ],
    },
    {
        id: 'packing',
        title: 'Packing',
        routeName: 'textile.packing.index',
        capability: 'packing',
        sections: [
            { id: 'overview', label: 'Overview', icon: LayoutDashboard },
            { id: 'roll-packing', label: 'Roll Packing', icon: Package },
            { id: 'bundle-packing', label: 'Bundle Packing', icon: PackageOpen },
            { id: 'bale-packing', label: 'Bale Packing', icon: Boxes },
            { id: 'labels', label: 'Labels', icon: TicketCheck },
        ],
    },
    {
        id: 'dispatch',
        title: 'Dispatch',
        routeName: 'textile.dispatch.index',
        capability: 'dispatch',
        sections: [
            { id: 'planning', label: 'Dispatch Planning', icon: Truck },
            { id: 'tracking', label: 'Dispatch Tracking', icon: Gauge },
        ],
    },
    {
        id: 'transport',
        title: 'Transport',
        routeName: 'textile.transport.index',
        capability: 'transport',
        sections: [
            { id: 'fuel', label: 'Fuel', icon: Fuel },
            { id: 'freight-cost', label: 'Freight Cost', icon: Receipt },
            { id: 'vehicle-maintenance', label: 'Vehicle Maintenance', icon: Wrench },
        ],
    },
    {
        id: 'maintenance',
        title: 'Maintenance',
        routeName: 'textile.maintenance.index',
        capability: 'maintenance',
        sections: [
            { id: 'pm', label: 'Preventive Maintenance', icon: Wrench },
            { id: 'breakdown', label: 'Breakdowns', icon: AlertTriangle },
            { id: 'service', label: 'Service Schedule', icon: Briefcase },
            { id: 'spare-parts', label: 'Spare Parts', icon: Package },
            { id: 'cost', label: 'Maintenance Cost', icon: Wallet },
            { id: 'history', label: 'Machine History', icon: Gauge },
        ],
    },
    {
        id: 'processing',
        title: 'Processing',
        routeName: 'textile.processing.index',
        capability: 'processing',
        sections: [
            { id: 'internal-processing', label: 'Internal Processing', icon: Settings },
            { id: 'job-work-outward', label: 'Yarn Issue to Weaver', icon: Truck },
            { id: 'processing-batch', label: 'Processing Batch', icon: Layers },
            { id: 'dyeing', label: 'Dyeing', icon: Droplets },
            { id: 'printing', label: 'Printing', icon: PaintRoller },
            { id: 'bleaching', label: 'Bleaching', icon: SprayCan },
            { id: 'calendaring', label: 'Calendaring', icon: Gauge },
            { id: 'compacting', label: 'Compacting', icon: Package },
            { id: 'finishing', label: 'Finishing', icon: Shirt },
            { id: 'shade-card', label: 'Shade Card', icon: PaintBucket },
            { id: 'process-cost', label: 'Process Cost', icon: Wallet },
            { id: 'job-work-inward', label: 'Job Work Inward', icon: PackageCheck },
            { id: 'reconciliation', label: 'Reconciliation', icon: Repeat },
        ],
    },
    {
        id: 'finance',
        title: 'Finance',
        routeName: 'textile.finance.index',
        capability: 'finance',
        sections: [
            { id: 'cost-per-meter', label: 'Cost Per Meter', icon: Receipt },
            { id: 'cost-per-roll', label: 'Cost Per Roll', icon: Package },
            { id: 'machine-cost', label: 'Machine Cost', icon: Settings },
            { id: 'power-cost', label: 'Power Cost', icon: Gauge },
            { id: 'chemical-cost', label: 'Chemical Cost', icon: Droplets },
            { id: 'labour-cost', label: 'Labour Cost', icon: UserCheck },
            { id: 'profitability', label: 'Profitability', icon: BarChart3 },
        ],
    },
    {
        id: 'reports',
        title: 'Reports',
        routeName: 'textile.reports.index',
        capability: 'reports',
        sections: [
            { id: 'production', label: 'Production', icon: Factory },
            { id: 'loom', label: 'Loom', icon: Settings },
            { id: 'operator', label: 'Operator', icon: UserCheck },
            { id: 'yarn-consumption', label: 'Yarn', icon: Layers },
            { id: 'beam', label: 'Beam', icon: Boxes },
            { id: 'grey-fabric', label: 'Grey Fabric', icon: Package },
            { id: 'finished-fabric', label: 'Finished', icon: Shirt },
            { id: 'dispatch', label: 'Dispatch', icon: Truck },
            { id: 'purchase', label: 'Purchase', icon: ShoppingCart },
            { id: 'sales', label: 'Sales', icon: ShoppingBag },
            { id: 'stock', label: 'Stock', icon: Warehouse },
            { id: 'profit', label: 'Profit', icon: BarChart3 },
            { id: 'machine-efficiency', label: 'Efficiency', icon: Gauge },
            { id: 'waste-analysis', label: 'Waste', icon: Scissors },
            { id: 'power-consumption', label: 'Power', icon: Gauge },
        ],
    },
    {
        id: 'dashboard',
        title: 'Dashboard',
        routeName: 'textile.dashboard.index',
        sections: [
            { id: 'overview', label: 'Overview', icon: LayoutDashboard },
            { id: 'purchase', label: 'Purchase', icon: ShoppingCart },
            { id: 'inventory', label: 'Inventory', icon: Warehouse },
            { id: 'sales', label: 'Sales', icon: ShoppingBag },
            { id: 'finance', label: 'Finance', icon: Wallet },
            { id: 'maintenance', label: 'Maintenance', icon: Wrench },
            { id: 'hr', label: 'HR', icon: UserCheck },
        ],
    },
];

export function getTextileWorkspace(id: string): TextileWorkspace | undefined {
    return textileWorkspaces.find((workspace) => workspace.id === id);
}
