import { Boxes } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const textileInventoryCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Inventory'),
        href: route('textile.inventory.index'),
        parent: 'textile',
        order: 50,
        icon: Boxes,
    },
];
