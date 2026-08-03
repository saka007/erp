import { Boxes } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const textileInventoryCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Inventory'),
        href: route('textile.inventory.index'),
        parent: 'textile-operations',
        order: 50,
        icon: Boxes,
        capability: 'inventory',
        children: [
            {
                title: t('Transactions'),
                href: route('textile.inventory.index', { section: 'transactions' }),
                capability: 'inventory_transactions',
                children: [
                    {
                        title: t('New Lot'),
                        href: route('textile.inventory.index', { section: 'transactions', sub: 'lot-create' }),
                        capability: 'inventory_transactions',
                    },
                    {
                        title: t('Record Movement'),
                        href: route('textile.inventory.index', { section: 'transactions', sub: 'movement-create' }),
                        capability: 'inventory_movements',
                    },
                    {
                        title: t('Reserve Quantity'),
                        href: route('textile.inventory.index', { section: 'transactions', sub: 'reservation-create' }),
                        capability: 'inventory_reservations',
                    },
                ],
            },
            {
                title: t('Controls'),
                href: route('textile.inventory.index', { section: 'controls' }),
                capability: 'inventory_controls',
                children: [
                    {
                        title: t('Create Location'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'location-create' }),
                        capability: 'inventory_locations',
                    },
                    {
                        title: t('Archive Location'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'location-archive' }),
                        capability: 'inventory_locations',
                    },
                    {
                        title: t('Update Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-status-update' }),
                        capability: 'inventory_controls',
                    },
                    {
                        title: t('Archive Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-status-archive' }),
                        capability: 'inventory_controls',
                    },
                    {
                        title: t('Freeze Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-freeze' }),
                        capability: 'inventory_freeze',
                    },
                    {
                        title: t('Unfreeze Lot'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'lot-unfreeze' }),
                        capability: 'inventory_freeze',
                    },
                    {
                        title: t('Physical Verification'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'physical-verification' }),
                        capability: 'inventory_verification',
                    },
                    {
                        title: t('Cycle Count'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'cycle-count' }),
                        capability: 'inventory_cycle_count',
                    },
                    {
                        title: t('Release Reservation'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'reservation-release' }),
                        capability: 'inventory_reservations',
                    },
                    {
                        title: t('Allocate Reservation'),
                        href: route('textile.inventory.index', { section: 'controls', sub: 'reservation-allocate' }),
                        capability: 'inventory_reservations',
                    },
                ],
            },
            {
                title: t('Records'),
                href: route('textile.inventory.index', { section: 'records' }),
                capability: 'inventory_records',
                children: [
                    {
                        title: t('Locations'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-locations' }),
                        capability: 'inventory_records',
                    },
                    {
                        title: t('Lots'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-lots' }),
                        capability: 'inventory_records',
                    },
                    {
                        title: t('Movements'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-movements' }),
                        capability: 'inventory_records',
                    },
                    {
                        title: t('Cycle Counts'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-cycle-counts' }),
                        capability: 'inventory_records',
                    },
                    {
                        title: t('Reservations'),
                        href: route('textile.inventory.index', { section: 'records', sub: 'record-reservations' }),
                        capability: 'inventory_records',
                    },
                ],
            },
        ],
    },
];
