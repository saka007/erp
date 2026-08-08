import { Boxes, Droplets, Layers, Package, PackageOpen, Settings, Shirt } from 'lucide-react';

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
                title: t('Yarn Stock'),
                href: route('textile.inventory.index', { section: 'yarn-stock' }),
                icon: Layers,
                capability: 'inventory',
            },
            {
                title: t('Beam Stock'),
                href: route('textile.inventory.index', { section: 'beam-stock' }),
                icon: Boxes,
                capability: 'inventory',
            },
            {
                title: t('Grey Fabric'),
                href: route('textile.inventory.index', { section: 'grey-fabric' }),
                icon: Package,
                capability: 'inventory',
            },
            {
                title: t('Finished Fabric'),
                href: route('textile.inventory.index', { section: 'finished-fabric' }),
                icon: Shirt,
                capability: 'inventory',
            },
            {
                title: t('Chemicals'),
                href: route('textile.inventory.index', { section: 'chemicals' }),
                icon: Droplets,
                capability: 'inventory',
            },
            {
                title: t('Packing Materials'),
                href: route('textile.inventory.index', { section: 'packing-materials' }),
                icon: PackageOpen,
                capability: 'inventory',
            },
            {
                title: t('Locations & Controls'),
                href: route('textile.inventory.index', { section: 'locations-controls' }),
                icon: Settings,
                capability: 'inventory_controls',
            },
        ],
    },
];
