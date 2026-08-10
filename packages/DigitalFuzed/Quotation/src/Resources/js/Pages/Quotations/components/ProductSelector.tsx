import React from 'react';
import { useTranslation } from 'react-i18next';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatCurrency } from '@/utils/helpers';

interface Product {
    id: number;
    name: string;
    sale_price: number;
    unit?: string;
    product_type?: 'product' | 'lot';
    lot_reference?: string;
    stock_quantity?: number;
    grade?: string | null;
    taxes?: Array<{id: number; tax_name: string; rate: number}>;
}

interface Props {
    products: Product[];
    value: number;
    onChange: (productId: number, product?: Product) => void;
    /** Lot references already chosen in another item row — these options are disabled. */
    disabledLotReferences?: Set<string>;
}

export default function ProductSelector({ products, value, onChange, disabledLotReferences }: Props) {
    const { t } = useTranslation();

    const handleChange = (productId: string) => {
        const id = parseInt(productId);
        const product = products.find(p => p.id === id);
        onChange(id, product);
    };

    return (
        <Select value={value.toString()} onValueChange={handleChange}>
            <SelectTrigger className="w-full">
                <SelectValue placeholder={t('Select Product')} />
            </SelectTrigger>
            <SelectContent searchable>
                {products.map((product) => {
                    const isDisabled = product.product_type === 'lot' &&
                        product.lot_reference != null &&
                        Boolean(disabledLotReferences?.has(product.lot_reference));

                    return (
                    <SelectItem key={product.id} value={product.id.toString()} disabled={isDisabled}>
                        {product.product_type === 'lot' ? (
                            <>
                                {product.lot_reference || product.name}
                                {product.stock_quantity != null && (
                                    <> - {product.stock_quantity} {product.unit || ''} {t('available')}</>
                                )}
                                {product.grade ? ` (${product.grade})` : ''}
                            </>
                        ) : (
                            <>
                                {product.name} - {formatCurrency(product.sale_price || 0)}
                            </>
                        )}
                    </SelectItem>
                    );
                })}
            </SelectContent>
        </Select>
    );
}