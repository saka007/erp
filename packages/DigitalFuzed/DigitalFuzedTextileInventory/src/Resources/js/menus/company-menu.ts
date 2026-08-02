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
        children: [
            {
                title: t('Transactions'),
                href: route('textile.inventory.index', { section: 'transactions' }),
            },
            {
                title: t('Controls'),
                href: route('textile.inventory.index', { section: 'controls' }),
            },
            {
                title: t('Records'),
                href: route('textile.inventory.index', { section: 'records' }),
            },
        ],
    },
];
