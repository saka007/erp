import { Factory } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const textileCoreCompanyMenu = (t: (key: string) => string) => [
    {
        name: 'textile',
        title: t('Textile'),
        icon: Factory,
        parent: 'dashboard',
        order: 260,
        children: [
            {
                title: t('Specifications'),
                href: route('textile.specifications.index'),
            },
            {
                title: t('Quality Profiles'),
                href: route('textile.quality-profiles.index'),
            },
            {
                title: t('Route Recipes'),
                href: route('textile.route-recipes.index'),
            },
            {
                title: t('Unit Conversions'),
                href: route('textile.unit-conversions.index'),
            },
            {
                title: t('Procurement'),
                href: route('textile.procurement.index'),
            },
            {
                title: t('Sales'),
                href: route('textile.sales.index'),
            },
            {
                title: t('Manufacturing'),
                href: route('textile.manufacturing.index'),
            },
            {
                title: t('Quality'),
                href: route('textile.quality.index'),
            },
            {
                title: t('Processing'),
                href: route('textile.processing.index'),
            },
            {
                title: t('Costing'),
                href: route('textile.costing.index'),
            },
            {
                title: t('Dashboards'),
                href: route('textile.dashboard.index'),
            },
        ],
    },
];