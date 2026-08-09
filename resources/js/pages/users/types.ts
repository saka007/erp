import { PaginatedData, ModalState, AuthContext, CreateProps, EditProps } from '@/types/common';

export interface BranchOption {
    id: number;
    name: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    mobile_no: string;
    role: string;
    type: string;
    is_enable_login: boolean;
    is_disable?: number;
    avatar?: string;
    active_plan_name?: string | null;
    industry_type?: 'standard' | 'textile';
    branch_ids?: number[];
    created_at: string;
}

export interface CreateUserFormData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    mobile_no: string;
    type: string;
    is_enable_login: boolean;
    branch_ids: number[];
}

export interface EditUserFormData {
    name: string;
    email: string;
    mobile_no: string;
    is_enable_login: boolean;
    branch_ids: number[];
}

export interface ChangePasswordFormData {
    password: string;
    password_confirmation: string;
}

export interface CreateUserProps extends CreateProps {
    roles?: Record<string, string>;
    branches?: BranchOption[];
}

export interface EditUserProps {
    user: User;
    onSuccess: () => void;
    roles?: Record<string, string>;
    branches?: BranchOption[];
}

export interface ChangePasswordProps {
    user: User;
    onSuccess: () => void;
}

export interface UserFilters {
    name: string;
    email: string;
    role: string;
    is_enable_login: string;
}

export type PaginatedUsers = PaginatedData<User>;
export interface UserModalState {
    isOpen: boolean;
    mode: '' | 'add' | 'edit' | 'change-password' | 'industry';
    data: User | null;
}

export interface UsersIndexProps {
    users: PaginatedUsers;
    roles: Record<string, string>;
    branches?: BranchOption[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface UserFormErrors {
    name?: string;
    email?: string;
    password?: string;
    password_confirmation?: string;
    mobile_no?: string;
    type?: string;
    is_enable_login?: string;
}