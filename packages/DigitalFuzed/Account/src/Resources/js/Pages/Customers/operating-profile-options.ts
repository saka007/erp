export const OPERATING_MODEL_OPTIONS = [
    { value: 'full_package_buyer', label: 'Full-package buyer (finished fabric)' },
    { value: 'jobwork_weaving_beam_supplied', label: 'Job-work weaving (beam/yarn supplied)' },
    { value: 'jobwork_processing_grey_supplied', label: 'Processing-only customer (grey supplied)' },
    { value: 'trader_bulk', label: 'Trader/distributor bulk buyer' },
    { value: 'export_compliance', label: 'Export/compliance customer' },
];

export const MATERIAL_OWNERSHIP_OPTIONS = [
    { value: 'company_owned', label: 'Company owned' },
    { value: 'customer_owned', label: 'Customer owned' },
    { value: 'mixed', label: 'Mixed' },
];

export const BILLING_MODE_OPTIONS = [
    { value: 'sale_value', label: 'Sale value' },
    { value: 'conversion_charge', label: 'Conversion charge' },
    { value: 'process_charge', label: 'Process charge' },
    { value: 'hybrid', label: 'Hybrid' },
];

export function resolveOperatingModelLabel(value?: string): string {
    return OPERATING_MODEL_OPTIONS.find((option) => option.value === value)?.label || value || '-';
}

export function resolveMaterialOwnershipLabel(value?: string): string {
    return MATERIAL_OWNERSHIP_OPTIONS.find((option) => option.value === value)?.label || value || '-';
}

export function resolveBillingModeLabel(value?: string): string {
    return BILLING_MODE_OPTIONS.find((option) => option.value === value)?.label || value || '-';
}
