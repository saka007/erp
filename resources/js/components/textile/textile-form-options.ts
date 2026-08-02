export interface TextileOption {
    value: string;
    label: string;
}

const defaultUnitOptions = ['kg', 'mtr', 'pcs', 'cone', 'roll', 'set', 'rpm'];

export const textileSourceTypeOptions: TextileOption[] = [
    { value: 'inventory_lot', label: 'Inventory Lot' },
    { value: 'sales_quotation', label: 'Sales Quotation' },
    { value: 'sales_order', label: 'Sales Order' },
    { value: 'purchase_order', label: 'Purchase Order' },
    { value: 'processing_order', label: 'Processing Order' },
    { value: 'factory', label: 'Factory' },
    { value: 'textile_workflow_document', label: 'Workflow Document' },
];

export const textileMachineTypeOptions: TextileOption[] = [
    { value: 'Rapier', label: 'Rapier' },
    { value: 'Airjet', label: 'Airjet' },
    { value: 'Waterjet', label: 'Waterjet' },
    { value: 'Projectile', label: 'Projectile' },
    { value: 'Shuttle', label: 'Shuttle' },
    { value: 'Circular', label: 'Circular' },
];

export function buildUnitOptions(masterUnits: string[] = []): TextileOption[] {
    const normalizedMasterUnits = masterUnits
        .map((unit) => (unit || '').trim())
        .filter((unit) => unit.length > 0);

    const normalizedDefaults = defaultUnitOptions.filter((unit) => !normalizedMasterUnits.includes(unit));

    return [
        ...normalizedMasterUnits.map((unit) => ({ value: unit, label: unit })),
        ...normalizedDefaults.map((unit) => ({ value: unit, label: unit })),
    ];
}
