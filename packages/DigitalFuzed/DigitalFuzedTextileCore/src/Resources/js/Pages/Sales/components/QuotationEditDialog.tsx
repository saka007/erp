import { useCallback, useEffect, useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Plus, Save, Trash2 } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/ui/input-error';
import { formatCurrency } from '@/utils/helpers';

export interface QuotationEditItem {
    id?: number;
    product_id: number;
    product_type?: string;
    lot_reference?: string | null;
    quantity: number;
    unit_price: number;
    discount_percentage: number;
    tax_percentage: number;
    taxes?: Array<{ tax_name: string; tax_rate: number }>;
    discount_amount?: number;
    tax_amount?: number;
    total_amount?: number;
}

export interface QuotationEditRecord {
    id: number;
    quotation_number: string;
    customer_id: number;
    warehouse_id?: number | null;
    quotation_date: string;
    due_date: string;
    quotation_type?: string;
    payment_terms?: string;
    notes?: string;
    status: string;
    items: QuotationEditItem[];
}

interface QuotationEditDialogProps {
    quotation: QuotationEditRecord | null;
    customers: Array<{ id: number; name: string; email: string }>;
    types: Array<{ value: string; label: string }>;
    warehouses: Array<{ id: number; name: string; address: string }>;
    onClose: () => void;
}

interface ProductOption {
    id: number;
    name: string;
    sale_price: number;
    unit?: string;
    product_type?: 'product' | 'lot';
    lot_reference?: string | null;
    stock_quantity?: number;
    taxes?: Array<{ id: number; tax_name: string; rate: number }>;
}

function calculateLineAmounts(
    quantity: number,
    unitPrice: number,
    discountPercentage: number = 0,
    taxPercentage: number = 0
) {
    const lineTotal = quantity * unitPrice;
    const discountAmount = (lineTotal * discountPercentage) / 100;
    const afterDiscount = lineTotal - discountAmount;
    const taxAmount = (afterDiscount * taxPercentage) / 100;
    const totalAmount = afterDiscount + taxAmount;

    return { discountAmount, taxAmount, totalAmount };
}

const emptyItem = (): QuotationEditItem => ({
    product_id: 0,
    product_type: 'product',
    lot_reference: null,
    quantity: 1,
    unit_price: 0,
    discount_percentage: 0,
    tax_percentage: 0,
    taxes: [],
    discount_amount: 0,
    tax_amount: 0,
    total_amount: 0,
});

export function QuotationEditDialog({
    quotation,
    customers,
    types,
    warehouses,
    onClose,
}: QuotationEditDialogProps) {
    const { t } = useTranslation();
    const [availableProducts, setAvailableProducts] = useState<ProductOption[]>([]);
    const [productsLoading, setProductsLoading] = useState(false);

    const defaultWarehouseId = warehouses.length === 1 ? String(warehouses[0].id) : '';

    const { data, setData, post, processing, errors } = useForm({
        invoice_date: quotation ? (quotation.quotation_date.split(' ')[0] ?? '') : '',
        due_date: quotation ? (quotation.due_date.split(' ')[0] ?? '') : '',
        customer_id: quotation ? String(quotation.customer_id) : '',
        warehouse_id: quotation?.warehouse_id ? String(quotation.warehouse_id) : defaultWarehouseId,
        quotation_type: quotation?.quotation_type || 'general',
        payment_terms: quotation?.payment_terms || '',
        notes: quotation?.notes || '',
        items: quotation
            ? quotation.items.map((item) => ({
                  ...item,
                  product_id: item.product_id,
                  product_type: item.product_type || 'product',
                  lot_reference: item.lot_reference || null,
                  quantity: item.quantity,
                  unit_price: item.unit_price,
                  discount_percentage: item.discount_percentage || 0,
                  tax_percentage: item.tax_percentage || 0,
                  taxes: item.taxes || [],
              }))
            : [emptyItem()],
    });

    const fetchProducts = useCallback(async (type: string, warehouseId: string) => {
        setProductsLoading(true);
        try {
            const params = new URLSearchParams();
            if (type) params.set('type', type);
            if (type === 'general' && warehouseId) params.set('warehouse_id', warehouseId);

            const response = await fetch(route('quotations.warehouse.products') + '?' + params.toString());
            const products = await response.json();
            setAvailableProducts(Array.isArray(products) ? products : []);
        } catch (error) {
            console.error('Failed to fetch quotation items:', error);
            setAvailableProducts([]);
        } finally {
            setProductsLoading(false);
        }
    }, []);

    // Load item sources when the dialog opens and when type/warehouse changes.
    useEffect(() => {
        fetchProducts(data.quotation_type, data.warehouse_id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.quotation_type, data.warehouse_id]);

    const updateItem = (index: number, field: keyof QuotationEditItem, value: unknown) => {
        const next = data.items.map((item, i) => {
            if (i !== index) {
                return item;
            }

            const updated = { ...item, [field]: value };

            const calculations = calculateLineAmounts(
                updated.quantity,
                updated.unit_price,
                updated.discount_percentage,
                updated.tax_percentage
            );

            return {
                ...updated,
                discount_amount: calculations.discountAmount,
                tax_amount: calculations.taxAmount,
                total_amount: calculations.totalAmount,
            };
        });

        setData('items', next);
    };

    const handleProductSelect = (index: number, productId: number) => {
        const product = availableProducts.find((p) => p.id === productId);

        setData('items', data.items.map((item, i) => {
            if (i !== index) {
                return item;
            }

            const isLot = product?.product_type === 'lot';
            const totalTaxRate = !isLot
                ? (product?.taxes?.reduce((sum, tax) => sum + Number(tax.rate), 0) || 0)
                : 0;
            const taxes = !isLot
                ? (product?.taxes?.map((tax) => ({ tax_name: tax.tax_name, tax_rate: tax.rate })) || [])
                : [];

            const updated: QuotationEditItem = {
                ...item,
                product_id: productId,
                product_type: isLot ? 'lot' : 'product',
                lot_reference: isLot ? (product?.lot_reference ?? null) : null,
                quantity: isLot && product?.stock_quantity ? Number(product.stock_quantity) : (Number(item.quantity) || 1),
                unit_price: Number(product?.sale_price) || 0,
                tax_percentage: Number(totalTaxRate) || 0,
                taxes,
            };

            const calculations = calculateLineAmounts(
                updated.quantity,
                updated.unit_price,
                updated.discount_percentage,
                updated.tax_percentage
            );

            return {
                ...updated,
                discount_amount: Number(calculations.discountAmount) || 0,
                tax_amount: Number(calculations.taxAmount) || 0,
                total_amount: Number(calculations.totalAmount) || 0,
            };
        }));
    };

    const addItem = () => {
        setData('items', [...data.items, emptyItem()]);
    };

    const removeItem = (index: number) => {
        setData('items', data.items.filter((_, i) => i !== index));
    };

    const totals = useMemo(() => {
        const subtotal = data.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
        const discountAmount = data.items.reduce((sum, item) => sum + (item.discount_amount || 0), 0);
        const taxAmount = data.items.reduce((sum, item) => sum + (item.tax_amount || 0), 0);
        const total = data.items.reduce((sum, item) => sum + (item.total_amount || 0), 0);

        return { subtotal, discountAmount, taxAmount, total };
    }, [data.items]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (quotation) {
            post(route('textile.sales.quotations.update', quotation.id), {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });
        } else {
            post(route('textile.sales.quotations.store'), {
                preserveScroll: true,
                onSuccess: () => onClose(),
            });
        }
    };

    const selectedLotRefs = data.items
        .map((item) => item.lot_reference)
        .filter((ref): ref is string => Boolean(ref));
    const productsById = new Map(availableProducts.map((p) => [p.id, p]));

    return (
        <Dialog open={true} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{quotation ? `${t('Update Quotation')} — ${quotation.quotation_number}` : t('Create Quotation')}</DialogTitle>
                    <DialogDescription>
                        {quotation
                            ? t('Edit the sauda details and save. Only draft quotations can be updated.')
                            : t('Create a new sauda (quotation) for a customer.')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <SelectField
                            label={t('Customer')}
                            value={data.customer_id}
                            onChange={(value) => setData('customer_id', value)}
                            options={customers.map((customer) => ({ value: String(customer.id), label: customer.name }))}
                            required
                            includeEmpty
                            error={errors.customer_id}
                        />
                        <SelectField
                            label={t('Quotation Type')}
                            value={data.quotation_type}
                            onChange={(value) => setData('quotation_type', value)}
                            options={types.map((type) => ({ value: type.value, label: type.label }))}
                            required
                            includeEmpty
                            error={errors.quotation_type}
                        />
                        <Field
                            label={t('Quotation Date')}
                            type="date"
                            value={data.invoice_date}
                            onChange={(value: string) => setData('invoice_date', value)}
                            required
                            error={errors.invoice_date}
                        />
                        <Field
                            label={t('Due Date')}
                            type="date"
                            value={data.due_date}
                            onChange={(value: string) => setData('due_date', value)}
                            required
                            error={errors.due_date}
                        />
                        <SelectField
                            label={t('Warehouse')}
                            value={data.warehouse_id}
                            onChange={(value) => setData('warehouse_id', value)}
                            options={warehouses.map((warehouse) => ({ value: String(warehouse.id), label: warehouse.name }))}
                            required
                            includeEmpty
                            error={errors.warehouse_id}
                        />
                        <Field
                            label={t('Payment Terms')}
                            type="text"
                            value={data.payment_terms}
                            onChange={(value: string) => setData('payment_terms', value)}
                            error={errors.payment_terms}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">{t('Notes')}</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={2}
                            placeholder={t('Additional details...')}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold text-foreground">{t('Items')}</h4>
                            <Button type="button" variant="outline" size="sm" onClick={addItem}>
                                <Plus className="mr-1 h-3.5 w-3.5" />
                                {t('Add Item')}
                            </Button>
                        </div>

                        {productsLoading && (
                            <p className="text-xs text-muted-foreground">{t('Loading items...')}</p>
                        )}

                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-muted/50">
                                        <th className="px-3 py-2 text-left font-semibold text-foreground">{t('Item')}</th>
                                        <th className="px-3 py-2 text-left font-semibold text-foreground">{t('Qty')}</th>
                                        <th className="px-3 py-2 text-left font-semibold text-foreground">{t('Unit Price')}</th>
                                        <th className="px-3 py-2 text-left font-semibold text-foreground">{t('Discount %')}</th>
                                        <th className="px-3 py-2 text-left font-semibold text-foreground">{t('Tax %')}</th>
                                        <th className="px-3 py-2 text-right font-semibold text-foreground">{t('Total')}</th>
                                        <th className="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.items.map((item, index) => {
                                        const currentProduct = productsById.get(item.product_id);
                                        const disabledLotRefs = new Set(
                                            selectedLotRefs.filter((ref) => ref !== item.lot_reference)
                                        );
                                        const productName = currentProduct?.name
                                            ?? (item.product_type === 'lot' ? item.lot_reference : '');

                                        return (
                                            <tr key={item.id ?? index}>
                                                <td className="px-3 py-2">
                                                    <SelectField
                                                        label=""
                                                        value={String(item.product_id)}
                                                        onChange={(value) => handleProductSelect(index, Number(value))}
                                                        options={availableProducts.map((product) => ({
                                                            value: String(product.id),
                                                            label: product.product_type === 'lot'
                                                                ? `${product.lot_reference || product.name}${product.stock_quantity != null ? ` - ${product.stock_quantity} ${product.unit || ''} available` : ''}`
                                                                : `${product.name} - ${formatCurrency(product.sale_price || 0)}`,
                                                            disabled: product.product_type === 'lot'
                                                                && product.lot_reference != null
                                                                && disabledLotRefs.has(product.lot_reference),
                                                        }))}
                                                        includeEmpty
                                                        required
                                                        error={(errors as Record<string, string>)[`items.${index}.product_id`]}
                                                    />
                                                    {item.product_id > 0 && !currentProduct && productName && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {t('Current')}: {productName}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        value={item.quantity}
                                                        min="1"
                                                        step="1"
                                                        onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value) || 0)}
                                                        className="h-8 w-20 rounded-md border border-input bg-background px-2 text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        value={item.unit_price}
                                                        min="0"
                                                        step="0.01"
                                                        onChange={(e) => updateItem(index, 'unit_price', parseFloat(e.target.value) || 0)}
                                                        className="h-8 w-24 rounded-md border border-input bg-background px-2 text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        value={item.discount_percentage}
                                                        min="0"
                                                        max="100"
                                                        step="0.01"
                                                        onChange={(e) => updateItem(index, 'discount_percentage', parseFloat(e.target.value) || 0)}
                                                        className="h-8 w-20 rounded-md border border-input bg-background px-2 text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        value={item.tax_percentage}
                                                        min="0"
                                                        step="0.01"
                                                        onChange={(e) => updateItem(index, 'tax_percentage', parseFloat(e.target.value) || 0)}
                                                        className="h-8 w-20 rounded-md border border-input bg-background px-2 text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-right font-medium">
                                                    {formatCurrency(item.total_amount || 0)}
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => removeItem(index)}
                                                        className="h-8 w-8 p-0 text-red-600 hover:text-red-800"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <InputError message={errors.items} />

                        <div className="flex flex-col items-end gap-1 rounded-lg bg-muted/40 p-3 text-sm">
                            <div className="flex w-full max-w-xs justify-between">
                                <span className="text-muted-foreground">{t('Subtotal')}</span>
                                <span className="font-medium">{formatCurrency(totals.subtotal)}</span>
                            </div>
                            <div className="flex w-full max-w-xs justify-between">
                                <span className="text-muted-foreground">{t('Discount')}</span>
                                <span className="font-medium">- {formatCurrency(totals.discountAmount)}</span>
                            </div>
                            <div className="flex w-full max-w-xs justify-between">
                                <span className="text-muted-foreground">{t('Tax')}</span>
                                <span className="font-medium">+ {formatCurrency(totals.taxAmount)}</span>
                            </div>
                            <div className="flex w-full max-w-xs justify-between border-t border-border pt-1">
                                <span className="font-semibold">{t('Total')}</span>
                                <span className="font-semibold">{formatCurrency(totals.total)}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-1 h-4 w-4" />
                            {processing ? t('Saving...') : quotation ? t('Save Changes') : t('Create Quotation')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
