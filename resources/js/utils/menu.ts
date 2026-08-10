import { NavItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { getSuperAdminMenu } from './menus/superadmin-menu';
import { getCompanyMenu } from './menus/company-menu';
import * as LucideIcons from 'lucide-react';

const TEXTILE_PACKAGE_KEYWORD = 'textile';

const isTextileIndustryCompany = (userType: string | undefined, userRoles: string[], industryType: string | undefined, activatedPackages: string[]): boolean => {
    if (userType === 'superadmin' || userRoles.includes('superadmin')) {
        return false;
    }

    if (industryType === 'textile') {
        return true;
    }

    if (!Array.isArray(activatedPackages)) {
        return false;
    }

    return activatedPackages.some((moduleName) => moduleName.toLowerCase().includes(TEXTILE_PACKAGE_KEYWORD));
};

const filterPackagesForIndustry = (userType: string | undefined, userRoles: string[], industryType: string | undefined, activatedPackages: string[]): string[] => {
    if (!isTextileIndustryCompany(userType, userRoles, industryType, activatedPackages)) {
        return activatedPackages;
    }

    return activatedPackages.filter((moduleName) => moduleName.toLowerCase().includes(TEXTILE_PACKAGE_KEYWORD));
};

const filterCoreMenusForIndustry = (coreMenus: NavItem[], userType: string | undefined, userRoles: string[], industryType: string | undefined, activatedPackages: string[], t: (key: string) => string): NavItem[] => {
    if (!isTextileIndustryCompany(userType, userRoles, industryType, activatedPackages)) {
        return coreMenus;
    }

    const allowedPermissions = new Set(['manage-users', 'manage-settings']);

    return coreMenus
        .filter((item) => {
            if (!item.permission) {
                return false;
            }

            return allowedPermissions.has(item.permission);
        })
        .map((item) => item);
};

// Get role-based core menu items
const getCoreMenuItems = (userRoles: string[], t: (key: string) => string): NavItem[] => {
    if (userRoles.includes('superadmin')) {
        return getSuperAdminMenu(t);
    }
    return getCompanyMenu(t);
};

// Auto-load package menus based on activated packages
const getPackageMenuItems = (userRoles: string[], activatedPackages: string[], t: (key: string) => string): NavItem[] => {
    const menuItems: NavItem[] = [];
    const menuType = userRoles.includes('superadmin') ? 'superadmin-menu' : 'company-menu';

    const allModules = import.meta.glob('../../../packages/DigitalFuzed/*/src/Resources/js/menus/*.ts', { eager: true });

    // Ensure activatedPackages is an array before iterating
    if (!Array.isArray(activatedPackages)) {
        return menuItems;
    }

    activatedPackages.forEach(packageName => {
        const directPath = `../../../packages/DigitalFuzed/${packageName}/src/Resources/js/menus/${menuType}.ts`;
        const prefixedPath = `../../../packages/DigitalFuzed/DigitalFuzed${packageName}/src/Resources/js/menus/${menuType}.ts`;

        const resolvedPath = allModules[directPath]
            ? directPath
            : (allModules[prefixedPath] ? prefixedPath : null);

        if (!resolvedPath) {
            return;
        }

        const module = allModules[resolvedPath] as any;

        if (module) {
            Object.values(module).forEach((item: any) => {
                const result = typeof item === 'function' ? item(t) : item;
                const items = Array.isArray(result) ? result : [result];
                menuItems.push(...items);
            });
        }
    });

    return menuItems;
};

// Get custom menu items from database
const getCustomMenuItems = (userRoles: string[], t: (key: string) => string): NavItem[] => {
    const { auth } = usePage().props as any;
    const customMenus = auth?.customMenus || [];
    
    return customMenus.map((menu: any) => {
        // Convert string icon to Lucide icon component
        let iconComponent = null;
        if (menu.icon && typeof menu.icon === 'string') {
            const IconComponent = (LucideIcons as any)[menu.icon];
            if (IconComponent) {
                iconComponent = IconComponent;
            }
        }
        
        return {
            ...menu,
            icon: iconComponent,
        };
    });
};

// Group menu items by parent
const groupMenusByParent = (menuItems: NavItem[], packageMenuItems: NavItem[]): NavItem[] => {
    const groupedItems = [...menuItems];

    packageMenuItems.forEach(packageItem => {
        if (packageItem.parent) {
            const parentMenu = groupedItems.find(item =>
                item.name === packageItem.parent
            );

            if (parentMenu) {
                if (!parentMenu.children) {
                    parentMenu.children = [];
                }
                parentMenu.children.push({
                    ...packageItem,
                    parent: undefined
                });

                // Sort children by order
                if (parentMenu.children) {
                    parentMenu.children.sort((a, b) => (a.order || 999) - (b.order || 999));
                }
            } else {
                groupedItems.push(packageItem);
            }
        } else {
            groupedItems.push(packageItem);
        }
    });

    return groupedItems;
};

// Filter menu items based on permissions
const filterByPermission = (items: NavItem[], userPermissions: string[]): NavItem[] => {
    return items.filter(item => {
        if (!item.permission) {
            if (item.children) {
                item.children = filterByPermission(item.children, userPermissions);
            }
            return true;
        }

        if (!userPermissions.includes(item.permission)) {
            return false;
        }

        if (item.children) {
            item.children = filterByPermission(item.children, userPermissions);
            return item.children.length > 0;
        }

        return true;
    });
};

const filterByCapabilities = (items: NavItem[], capabilities: Record<string, boolean>): NavItem[] => {
    return items
        .map((item) => {
            const nextItem: NavItem = {
                ...item,
                children: item.children ? filterByCapabilities(item.children, capabilities) : undefined,
            };

            const capabilityAllowed = !nextItem.capability || capabilities[nextItem.capability] !== false;
            if (!capabilityAllowed) {
                return null;
            }

            if (nextItem.children && nextItem.children.length === 0 && !nextItem.href) {
                return null;
            }

            return nextItem;
        })
        .filter((item): item is NavItem => item !== null);
};

const isAdminUser = (userType: string | undefined, userRoles: string[]): boolean => {
    return userType === 'company' || userType === 'superadmin' || userRoles.includes('superadmin');
};

const filterByAdminOnly = (items: NavItem[], isAdmin: boolean): NavItem[] => {
    return items
        .map((item) => {
            const nextItem: NavItem = {
                ...item,
                children: item.children ? filterByAdminOnly(item.children, isAdmin) : undefined,
            };

            // Items flagged adminOnly are only visible to admin (company/superadmin) users.
            if (nextItem.adminOnly && !isAdmin) {
                return null;
            }

            // Drop parents that ended up empty (all children filtered out) and have no direct href.
            if (nextItem.children && nextItem.children.length === 0 && !nextItem.href) {
                return null;
            }

            return nextItem;
        })
        .filter((item): item is NavItem => item !== null);
};

// Main function to get filtered menu items
export const allMenuItems = (): NavItem[] => {
    const { auth } = usePage().props as any;
    const { t } = useTranslation();
    const userPermissions = auth?.user?.permissions || [];
    const userRoles = auth?.user?.roles || [];
    const userType = auth?.user?.type;
    const industryType = auth?.user?.industry_type;
    const textileCapabilities = auth?.user?.textile_capabilities || {};
    const activatedPackages = auth?.user?.activatedPackages || [];

    const isAdmin = isAdminUser(userType, userRoles);

    const coreMenuItems = filterCoreMenusForIndustry(getCoreMenuItems(userRoles, t), userType, userRoles, industryType, activatedPackages, t);

    const industryPackages = filterPackagesForIndustry(userType, userRoles, industryType, activatedPackages);
    const packageMenuItems = getPackageMenuItems(userRoles, industryPackages, t);
    
    const customMenuItems = getCustomMenuItems(userRoles, t);
    
    // Separate custom menus into parents and children
    const customParentMenus = customMenuItems.filter(menu => !menu.parent);
    const customChildMenus = customMenuItems.filter(menu => menu.parent);
    
    // First add custom parent menus to core menus
    const coreWithCustomParents = [...coreMenuItems, ...customParentMenus];
    
    // Then group all children (package + custom children) with their parents
    const allChildMenus = [...packageMenuItems, ...customChildMenus];
    const finalGroupedMenuItems = groupMenusByParent(coreWithCustomParents, allChildMenus);

    const sortedMenuItems = finalGroupedMenuItems.sort((a, b) => (a.order || 999) - (b.order || 999));
    const capabilityFilteredMenuItems = filterByCapabilities(sortedMenuItems, textileCapabilities);
    const adminFilteredMenuItems = filterByAdminOnly(capabilityFilteredMenuItems, isAdmin);

    const finalMenuItems = filterByPermission(adminFilteredMenuItems, userPermissions);

    return finalMenuItems;
};