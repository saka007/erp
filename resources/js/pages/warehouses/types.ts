import { PaginatedData, ModalState, AuthContext, CreateProps, EditProps } from '@/types/common';

export interface Warehouse {
    id: number;
    name: string;
    address: string;
    city: string;
    zip_code: string;
    phone?: string;
    email?: string;
    branch_id?: number | null;
    branch_name?: string | null;
    is_active: boolean;
    created_at: string;
}

export interface BranchOption {
    id: number;
    name: string;
}

export interface CreateWarehouseFormData {
    name: string;
    address: string;
    city: string;
    zip_code: string;
    phone: string;
    email: string;
    branch_id: number | null;
    is_active: boolean;
    [key: string]: any;
}

export interface EditWarehouseFormData {
    name: string;
    address: string;
    city: string;
    zip_code: string;
    phone?: string;
    email?: string;
    branch_id?: number | null;
    is_active: boolean;
    [key: string]: any;
}

export interface CreateWarehouseProps extends CreateProps {
    branches: BranchOption[];
    canManageAllBranches: boolean;
}

export interface EditWarehouseProps extends EditProps<Warehouse> {
    warehouse: Warehouse;
    branches: BranchOption[];
    canManageAllBranches: boolean;
}

export interface WarehouseFilters {
    name: string;
    city: string;
    is_active: string;
    branch_id?: string;
}

export type PaginatedWarehouses = PaginatedData<Warehouse>;
export type WarehouseModalState = ModalState<Warehouse>;

export interface WarehousesIndexProps {
    warehouses: PaginatedWarehouses;
    branches: BranchOption[];
    canManageAllBranches: boolean;
    currentBranchId: number | null;
    auth: AuthContext;
    [key: string]: unknown;
}

export interface WarehouseFormErrors {
    name?: string;
    address?: string;
    city?: string;
    zip_code?: string;
    phone?: string;
    email?: string;
    branch_id?: string;
    is_active?: string;
}