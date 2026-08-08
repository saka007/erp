export const SUPPLIER_TYPE_OPTIONS = [
    { value: 'yarn', label: 'Yarn Supplier' },
    { value: 'chemical', label: 'Chemical Supplier' },
    { value: 'spare_part', label: 'Spare Part Supplier' },
    { value: 'processing', label: 'Processing Vendor' },
    { value: 'sizing', label: 'Sizing Vendor' },
    { value: 'powerloom', label: 'Powerloom Vendor' },
    { value: 'dyeing', label: 'Dyeing Vendor' },
    { value: 'transport', label: 'Transport Vendor' },
    { value: 'job_worker', label: 'Job Worker' },
] as const;

export function resolveSupplierTypeLabel(value?: string | null): string {
    return SUPPLIER_TYPE_OPTIONS.find((option) => option.value === value)?.label || value || '-';
}
