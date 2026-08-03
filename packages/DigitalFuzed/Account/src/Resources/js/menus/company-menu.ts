import { Calculator, Building2, CreditCard, FileText, Landmark, BarChart3 } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const accountCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Account Dashboard'),
        href: route('account.index'),
        permission: 'manage-account-dashboard',
        parent: 'dashboard',
        order: 20,
    },
    {
        title: t('Accounting'),
        icon: Calculator,
        permission: 'manage-account',
        order: 400,
        children: [
            {
                title: t('Customers'),
                href: route('account.customers.index'),
                permission: 'manage-customers',
            },
            {
                title: t('Customer Categories'),
                href: route('account.customer-categories.index'),
                permission: 'manage-customers',
            },
            {
                title: t('Customer Price List'),
                href: route('account.customer-price-lists.index'),
                permission: 'manage-customers',
            },
            {
                title: t('Customer Contacts'),
                href: route('account.customer-contacts.index'),
                permission: 'manage-customers',
            },
            {
                title: t('Customer Follow Ups'),
                href: route('account.customer-follow-ups.index'),
                permission: 'manage-customers',
            },
            {
                title: t('Customer Documents'),
                href: route('account.customer-documents.index'),
                permission: 'manage-customers',
            },
            {
                title: t('Vendors'),
                href: route('account.vendors.index'),
                permission: 'manage-vendors',
            },
            {
                title: t('Vendor Ratings'),
                href: route('account.vendor-ratings.index'),
                permission: 'manage-vendors',
            },
            {
                title: t('Vendor Performance'),
                href: route('account.vendor-performance.index'),
                permission: 'manage-vendors',
            },
            {
                title: t('Product Variants'),
                href: route('product-service.product-master.variants.index'),
                permission: 'manage-product-service-item',
            },
            {
                title: t('Product Images'),
                href: route('product-service.product-master.images.index'),
                permission: 'manage-product-service-item',
            },
            {
                title: t('Product Documents'),
                href: route('product-service.product-master.documents.index'),
                permission: 'manage-product-service-item',
            },
            {
                title: t('Banking'),
                permission: 'manage-bank-accounts',
                children: [
                    {
                        title: t('Bank Accounts'),
                        href: route('account.bank-accounts.index'),
                        permission: 'manage-bank-accounts',
                    },
                    {
                        title: t('Bank Transactions'),
                        href: route('account.bank-transactions.index'),
                        permission: 'manage-bank-transactions',
                    },
                    {
                        title: t('Bank Transfers'),
                        href: route('account.bank-transfers.index'),
                        permission: 'manage-bank-transfers',
                    },
                ],
            },
            {
                title: t('Chart Of Accounts'),
                href: route('account.chart-of-accounts.index'),
                permission: 'manage-chart-of-accounts',
            },
            {
                title: t('Vendor Payments'),
                href: route('account.vendor-payments.index'),
                permission: 'manage-vendor-payments',
            },
            {
                title: t('Customer Payments'),
                href: route('account.customer-payments.index'),
                permission: 'manage-customer-payments',
            },
            {
                title: t('Revenue'),
                href: route('account.revenues.index'),
                permission: 'manage-revenues',
            },
            {
                title: t('Expense'),
                href: route('account.expenses.index'),
                permission: 'manage-expenses',
            },
            {
                title: t('Debit Notes'),
                href: route('account.debit-notes.index'),
                permission: 'manage-debit-notes',
            },
            {
                title: t('Credit Notes'),
                href: route('account.credit-notes.index'),
                permission: 'manage-credit-notes',
            },
            {
                title: t('Reports'),
                href: route('account.reports.index'),
                permission: 'manage-account-reports',
            },
            {
                title: t('System Setup'),
                href: route('account.account-types.index'),
                permission: 'manage-account-types',
                activePaths: [route('account.revenue-categories.index'), route('account.expense-categories.index')],
            },
        ],
    },
];
