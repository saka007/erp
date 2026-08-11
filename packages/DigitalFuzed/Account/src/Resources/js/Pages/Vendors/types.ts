import { PaginatedData, ModalState, AuthContext } from '@/types/common';

export interface Address {
    name: string;
    address_line_1: string;
    address_line_2?: string;
    city: string;
    state: string;
    country: string;
    zip_code: string;
}

export interface Vendor {
    id: number;
    user_id?: number;
    vendor_code: string;
    supplier_type?: string;
    company_name: string;
    contact_person_name: string;
    contact_person_email?: string;
    contact_person_mobile?: string;
    primary_email?: string;
    primary_mobile?: string;
    tax_number?: string;
    payment_terms?: string;
    currency_code: string;
    credit_limit?: number;
    credit_days?: number;
    credit_enabled?: boolean;
    reminder_enabled?: boolean;
    billing_address: Address;
    shipping_address: Address;
    same_as_billing: boolean;
    is_active: boolean;
    notes?: string;
    created_at: string;
}

export interface VendorPriceRow {
    id?: number;
    product_service_item_id: number | string;
    unit_price: number | string;
    min_quantity?: number | string;
    currency_code?: string;
    notes?: string | null;
}

export interface CreateVendorFormData {
    user_id?: string;
    supplier_type: string;
    company_name: string;
    contact_person_name: string;
    contact_person_email: string;
    contact_person_mobile: string;
    tax_number: string;
    payment_terms: string;
    credit_limit?: number;
    credit_days?: number;
    credit_enabled?: boolean;
    reminder_enabled?: boolean;
    billing_address: Address;
    shipping_address: Address;
    same_as_billing: boolean;
    notes: string;
    price_lists?: VendorPriceRow[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    mobile_no?: string;
}

export interface VendorFilters {
    company_name: string;
    vendor_code: string;
    supplier_type: string;
    contact_person_name: string;
}

export type PaginatedVendors = PaginatedData<Vendor>;
export type VendorModalState = ModalState<Vendor>;

export interface VendorsIndexProps {
    vendors: PaginatedVendors;
    users: User[];
    items: VendorPriceItem[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface VendorPriceItem {
    id: number;
    name: string;
    sku: string;
    unit?: string | null;
    purchase_price?: string | number | null;
    sale_price?: string | number | null;
}

export interface CreateVendorProps {
    onSuccess: () => void;
    users?: User[];
    auth?: any;
}

export interface EditVendorProps {
    vendor: Vendor & { price_lists?: VendorPriceRow[] };
    items: VendorPriceItem[];
    onSuccess: () => void;
}