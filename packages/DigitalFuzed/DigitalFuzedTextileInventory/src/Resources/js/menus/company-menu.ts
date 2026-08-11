import { Boxes } from 'lucide-react';

export const textileInventoryCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Inventory'),
        href: route('textile.inventory.index'),
        parent: 'textile-operations',
        order: 50,
        icon: Boxes,
        capability: 'inventory',
    },
];
