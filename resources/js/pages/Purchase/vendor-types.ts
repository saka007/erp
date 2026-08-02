export const VENDOR_TYPE_OPTIONS = [
    { value: 'yarn', label: 'Yarn Supplier' },
    { value: 'chemical', label: 'Chemical Supplier' },
    { value: 'spare_part', label: 'Spare Part Supplier' },
    { value: 'processing', label: 'Processing Vendor' },
    { value: 'dyeing', label: 'Dyeing Vendor' },
    { value: 'transport', label: 'Transport Vendor' },
    { value: 'job_worker', label: 'Job Worker' },
] as const;

export function resolveVendorTypeLabel(value?: string | null): string {
    return VENDOR_TYPE_OPTIONS.find((option) => option.value === value)?.label || value || '-';
}