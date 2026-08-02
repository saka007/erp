import { Boxes } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const textileInventoryCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Inventory'),
        href: route('textile.inventory.index'),
        parent: 'textile-insights',
        order: 50,
        icon: Boxes,
        children: [
            {
                title: t('Transactions'),
                href: route('textile.inventory.index', { section: 'transactions' }),
                children: [
                    {
                        title: t('New Lot'),
                        href: route('textile.inventory.index', { section: 'transactions', sub: 'lot-create' }),
                    },
                    {
                        title: t('Record Movement'),
                        href: route('textile.inventory.index', { section: 'transactions', sub: 'movement-create' }),
                    },
                    {
                        title: t('Reserve Quantity'),
                        href: route('textile.inventory.index', { section: 'transactions', sub: 'reservation-create' }),
                    },
                ],
            },
            {
                title: t('Controls'),
                href: route('textile.inventory.index', { section: 'controls' }),
                children: [
                    {
                        title: t('Create Location'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'location-create' }),
                    },
                    {
                        title: t('Archive Location'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'location-archive' }),
                    },
                    {
                        title: t('Update Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-status-update' }),
                    },
                    {
                        title: t('Archive Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-status-archive' }),
                    },
                    {
                        title: t('Freeze Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-freeze' }),
                    },
                    {
                        title: t('Unfreeze Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-unfreeze' }),
                    },
                    {
                        title: t('Physical Verification'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'physical-verification' }),
                    },
                    {
                        title: t('Cycle Count'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'cycle-count' }),
                    },
                    {
                        title: t('Release Reservation'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'reservation-release' }),
                    },
                    {
                        title: t('Allocate Reservation'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'reservation-allocate' }),
                    },
                ],
            },
            {
                title: t('Records'),
                href: route('textile.inventory.index', { section: 'records' }),
                children: [
                    {
                        title: t('Locations'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-locations' }),
                    },
                    {
                        title: t('Lots'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-lots' }),
                    },
                    {
                        title: t('Movements'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-movements' }),
                    },
                    {
                        title: t('Cycle Counts'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-cycle-counts' }),
                    },
                    {
                        title: t('Reservations'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-reservations' }),
                    },
                ],
            },
        ],
    },
];
