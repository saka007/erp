export const ITEM_TYPE_OPTIONS = [
    { value: 'product', label: 'Product' },
    { value: 'service', label: 'Service' },
    { value: 'part', label: 'Part' },
    { value: 'yarn', label: 'Yarn' },
    { value: 'fabric', label: 'Fabric' },
    { value: 'grey_fabric', label: 'Grey Fabric' },
    { value: 'finished_fabric', label: 'Finished Fabric' },
    { value: 'chemical', label: 'Chemical' },
    { value: 'packing_material', label: 'Packing Material' },
    { value: 'spare_part', label: 'Spare Part' },
    { value: 'accessory', label: 'Accessory' },
] as const;

export function resolveItemTypeLabel(value?: string | null): string {
    return ITEM_TYPE_OPTIONS.find((option) => option.value === value)?.label || value || '-';
}