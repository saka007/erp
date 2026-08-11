import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import InputError from "@/components/ui/input-error";
import { PhoneInputComponent } from "@/components/ui/phone-input";
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { Plus, Trash2 } from "lucide-react";
import { Customer, CustomerCategoryOption, CustomerFormData, CustomerPriceRow, CustomerPriceItem } from './types';
import { BILLING_MODE_OPTIONS, MATERIAL_OWNERSHIP_OPTIONS, OPERATING_MODEL_OPTIONS } from './operating-profile-options';
import { useFormFields } from '@/hooks/useFormFields';
interface EditCustomerProps {
    customer: Customer & { price_lists?: CustomerPriceRow[] };
    customerCategories: CustomerCategoryOption[];
    items?: CustomerPriceItem[];
    onSuccess: () => void;
}

export default function Edit({ customer, customerCategories = [], items = [], onSuccess }: EditCustomerProps) {
    const { t } = useTranslation();
    const emptyAddress = {
        name: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        state: '',
        country: '',
        zip_code: ''
    };
    const initialPriceLists: CustomerPriceRow[] = (customer.price_lists ?? []).map((row) => ({
        id: row.id,
        product_service_item_id: row.product_service_item_id,
        unit_price: row.unit_price,
        min_quantity: row.min_quantity ?? 1,
        currency_code: row.currency_code ?? 'INR',
        notes: row.notes ?? null,
    }));
    const { data, setData, put, processing, errors } = useForm<CustomerFormData>({
        ...customer,
        billing_address: customer.billing_address || emptyAddress,
        shipping_address: customer.shipping_address || emptyAddress,
        price_lists: initialPriceLists,
    });

    const formFields = useFormFields('customerEditFields', data, setData, errors, 'edit');
    const updatePriceRow = (index: number, field: keyof CustomerPriceRow, value: string | number) => {
        const rows = [...(data.price_lists ?? [])];
        rows[index] = { ...rows[index], [field]: value };
        setData('price_lists', rows);
    };

    const addPriceRow = () => {
        setData('price_lists', [...(data.price_lists ?? []), {
            product_service_item_id: '',
            unit_price: '',
            min_quantity: 1,
            currency_code: 'INR',
        }]);
    };

    const removePriceRow = (index: number) => {
        const rows = [...(data.price_lists ?? [])];
        rows.splice(index, 1);
        setData('price_lists', rows);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('account.customers.update', customer.id), {
            transform: (formData) => formData.same_as_billing
                ? {...formData, shipping_address: {...formData.billing_address}}
                : formData,
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent className="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{t('Edit Customer')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="company_name">{t('Company Name')}</Label>
                    <Input
                        id="company_name"
                        value={data.company_name}
                        onChange={(e) => setData('company_name', e.target.value)}
                        placeholder={t('Enter company name')}
                        required
                    />
                    <InputError message={errors.company_name} />
                </div>
                <div>
                    <Label htmlFor="contact_person_name">{t('Contact Person')}</Label>
                    <Input
                        id="contact_person_name"
                        value={data.contact_person_name}
                        onChange={(e) => setData('contact_person_name', e.target.value)}
                        placeholder={t('Enter contact person name')}
                        required
                    />
                    <InputError message={errors.contact_person_name} />
                </div>
                <div>
                    <Label htmlFor="contact_person_email">{t('Email')}</Label>
                    <Input
                        id="contact_person_email"
                        type="email"
                        value={data.contact_person_email}
                        onChange={(e) => setData('contact_person_email', e.target.value)}
                        placeholder={t('Enter email address')}
                        required
                    />
                    <InputError message={errors.contact_person_email} />
                </div>
                <div>
                    <PhoneInputComponent
                        label={t('Mobile Number')}
                        value={data.contact_person_mobile}
                        onChange={(value) => setData('contact_person_mobile', value)}
                        placeholder="+1234567890"
                        error={errors.contact_person_mobile}
                    />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="tax_number">{t('Tax Number')}</Label>
                        <Input
                            id="tax_number"
                            value={data.tax_number}
                            onChange={(e) => setData('tax_number', e.target.value)}
                            placeholder={t('Enter tax number')}
                        />
                        <InputError message={errors.tax_number} />
                    </div>
                    <div>
                        <Label htmlFor="payment_terms">{t('Payment Terms')}</Label>
                        <Input
                            id="payment_terms"
                            value={data.payment_terms}
                            onChange={(e) => setData('payment_terms', e.target.value)}
                            placeholder={t('e.g., Net 30')}
                        />
                        <InputError message={errors.payment_terms} />
                    </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label htmlFor="credit_limit">{t('Credit Limit')}</Label>
                        <Input
                            id="credit_limit"
                            type="number"
                            value={data.credit_limit ?? ''}
                            onChange={(e) => setData('credit_limit', e.target.value ? Number(e.target.value) : undefined)}
                            placeholder={t('Enter credit limit')}
                        />
                        <InputError message={errors.credit_limit} />
                    </div>
                    <div>
                        <Label htmlFor="credit_days">{t('Credit Days')}</Label>
                        <Input
                            id="credit_days"
                            type="number"
                            value={data.credit_days ?? ''}
                            onChange={(e) => setData('credit_days', e.target.value ? Number(e.target.value) : undefined)}
                            placeholder={t('e.g., 30')}
                        />
                        <InputError message={errors.credit_days} />
                    </div>
                    <div className="flex items-end pb-2">
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="credit_enabled"
                                checked={data.credit_enabled}
                                onCheckedChange={(checked) => setData('credit_enabled', !!checked)}
                            />
                            <Label htmlFor="credit_enabled">{t('Credit Enabled')}</Label>
                        </div>
                    </div>
                    <SelectField
                        label={t('Credit Currency')}
                        value={data.currency_code || 'USD'}
                        onChange={(value) => setData('currency_code', value || 'USD')}
                        options={['USD', 'INR', 'EUR', 'GBP', 'AED']}
                    />
                </div>
                <div>
                    <Label htmlFor="default_rate">{t('Default Rate per Unit')}</Label>
                    <Input
                        id="default_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.default_rate ?? ''}
                        onChange={(e) => setData('default_rate', e.target.value ? Number(e.target.value) : undefined)}
                        placeholder={t('Enter default selling rate per unit')}
                    />
                    <p className="text-xs text-muted-foreground mt-1">{t('Auto-fills the rate in sales orders for this customer. You can still edit it per order.')}</p>
                    <InputError message={errors.default_rate} />
                </div>

                {/* Product Pricing */}
                <div className="border rounded-lg p-4 space-y-3">
                    <div>
                        <Label className="text-base font-medium">{t('Product Pricing')}</Label>
                        <p className="text-xs text-muted-foreground mt-1">
                            {t('Set default selling rates per product for this customer. These pre-fill when raising sales orders and stay editable per order.')}
                        </p>
                    </div>

                    {items.length === 0 && (
                        <p className="text-xs text-muted-foreground">
                            {t('No products available yet. Add products in the Product Master first.')}
                        </p>
                    )}

                    {(data.price_lists ?? []).length === 0 && items.length > 0 && (
                        <p className="text-xs text-muted-foreground">
                            {t('No rates set yet. Click "Add Product Rate" to set per-kg / per-unit rates for this customer.')}
                        </p>
                    )}

                    <div className="space-y-2">
                        {(data.price_lists ?? []).map((row, index) => (
                            <div key={index} className="grid grid-cols-[1fr_120px_90px_auto] gap-2 items-center">
                                <div>
                                    <SelectField
                                        label=""
                                        value={row.product_service_item_id ? String(row.product_service_item_id) : ''}
                                        onChange={(value) => updatePriceRow(index, 'product_service_item_id', value)}
                                        options={items.map((item) => ({ value: String(item.id), label: `${item.name}${item.unit ? ` (${item.unit})` : ''}` }))}
                                        includeEmpty
                                        emptyLabel={t('Select product')}
                                    />
                                </div>
                                <div>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={row.unit_price === '' ? '' : String(row.unit_price)}
                                        onChange={(e) => updatePriceRow(index, 'unit_price', e.target.value)}
                                        placeholder={t('Rate')}
                                    />
                                </div>
                                <div>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={row.min_quantity === undefined || row.min_quantity === '' ? '' : String(row.min_quantity)}
                                        onChange={(e) => updatePriceRow(index, 'min_quantity', e.target.value)}
                                        placeholder={t('Min Qty')}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removePriceRow(index)}
                                    className="text-red-500 hover:text-red-600 hover:bg-red-50"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                    </div>

                    {items.length > 0 && (
                        <Button type="button" variant="outline" size="sm" onClick={addPriceRow}>
                            <Plus className="h-4 w-4 mr-1" />
                            {t('Add Product Rate')}
                        </Button>
                    )}
                </div>

                <div>
                    <SelectField
                        label={t('Customer Category')}
                        value={data.customer_category_id ? String(data.customer_category_id) : ''}
                        onChange={(value) => setData('customer_category_id', value ? Number(value) : undefined)}
                        options={customerCategories.map((category) => ({ value: String(category.id), label: category.name }))}
                        includeEmpty
                        emptyLabel={t('Select category (optional)')}
                    />
                    <InputError message={errors.customer_category_id} />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <SelectField
                        label={t('Operating Model')}
                        value={data.operating_model || ''}
                        onChange={(value) => setData('operating_model', value)}
                        options={OPERATING_MODEL_OPTIONS}
                        helperText={t('Defines customer workflow path in textile operations.')}
                        includeEmpty
                        required
                    />
                    <SelectField
                        label={t('Material Ownership')}
                        value={data.material_ownership || ''}
                        onChange={(value) => setData('material_ownership', value)}
                        options={MATERIAL_OWNERSHIP_OPTIONS}
                        includeEmpty
                        required
                    />
                    <SelectField
                        label={t('Billing Mode')}
                        value={data.billing_mode || ''}
                        onChange={(value) => setData('billing_mode', value)}
                        options={BILLING_MODE_OPTIONS}
                        includeEmpty
                        required
                    />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <InputError message={errors.operating_model} />
                    <InputError message={errors.material_ownership} />
                    <InputError message={errors.billing_mode} />
                </div>
                <div>
                    <Label htmlFor="billing_name">{t('Billing Name')}</Label>
                    <Input
                        id="billing_name"
                        value={data.billing_address.name}
                        onChange={(e) => setData('billing_address', {...data.billing_address, name: e.target.value})}
                        placeholder={t('Enter billing name')}
                        required
                    />
                    <InputError message={errors['billing_address.name']} />
                </div>
                <div>
                    <Label htmlFor="billing_address">{t('Billing Address')}</Label>
                    <Input
                        id="billing_address"
                        value={data.billing_address.address_line_1}
                        onChange={(e) => setData('billing_address', {...data.billing_address, address_line_1: e.target.value})}
                        placeholder={t('Enter address')}
                        required
                    />
                    <InputError message={errors['billing_address.address_line_1']} />
                </div>
                <div>
                    <Label htmlFor="billing_address_2">{t('Address Line 2')}</Label>
                    <Input
                        id="billing_address_2"
                        value={data.billing_address.address_line_2}
                        onChange={(e) => setData('billing_address', {...data.billing_address, address_line_2: e.target.value})}
                        placeholder={t('Apartment, suite, etc. (optional)')}
                    />
                    <InputError message={errors['billing_address.address_line_2']} />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="billing_city">{t('City')}</Label>
                        <Input
                            id="billing_city"
                            value={data.billing_address.city}
                            onChange={(e) => setData('billing_address', {...data.billing_address, city: e.target.value})}
                            placeholder={t('Enter city')}
                            required
                        />
                        <InputError message={errors['billing_address.city']} />
                    </div>
                    <div>
                        <Label htmlFor="billing_state">{t('State')}</Label>
                        <Input
                            id="billing_state"
                            value={data.billing_address.state}
                            onChange={(e) => setData('billing_address', {...data.billing_address, state: e.target.value})}
                            placeholder={t('Enter state')}
                            required
                        />
                        <InputError message={errors['billing_address.state']} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="billing_country">{t('Country')}</Label>
                        <Input
                            id="billing_country"
                            value={data.billing_address.country}
                            onChange={(e) => setData('billing_address', {...data.billing_address, country: e.target.value})}
                            placeholder={t('Enter country')}
                            required
                        />
                        <InputError message={errors['billing_address.country']} />
                    </div>
                    <div>
                        <Label htmlFor="billing_zip">{t('Zip Code')}</Label>
                        <Input
                            id="billing_zip"
                            value={data.billing_address.zip_code}
                            onChange={(e) => setData('billing_address', {...data.billing_address, zip_code: e.target.value})}
                            placeholder={t('Enter zip code')}
                            required
                        />
                        <InputError message={errors['billing_address.zip_code']} />
                    </div>
                    {formFields.map((field) => (
                        field.component
                    ))}
                </div>
                <div className="flex items-center space-x-2">
                    <Checkbox
                        id="same_as_billing"
                        checked={data.same_as_billing}
                        onCheckedChange={(checked) => {
                            setData('same_as_billing', !!checked);
                            if (checked) {
                                setData('shipping_address', {...data.billing_address});
                            }
                        }}
                    />
                    <Label htmlFor="same_as_billing">{t('Shipping address same as billing')}</Label>
                </div>

                {!data.same_as_billing && (
                    <div className="space-y-4 border-t pt-4">
                        <h3 className="text-lg font-medium">{t('Shipping Address')}</h3>
                        <div>
                            <Label htmlFor="shipping_name">{t('Shipping Name')}</Label>
                            <Input
                                id="shipping_name"
                                value={data.shipping_address.name}
                                onChange={(e) => setData('shipping_address', {...data.shipping_address, name: e.target.value})}
                                placeholder={t('Enter shipping name')}
                                required
                            />
                            <InputError message={errors['shipping_address.name']} />
                        </div>
                        <div>
                            <Label htmlFor="shipping_address">{t('Shipping Address')}</Label>
                            <Input
                                id="shipping_address"
                                value={data.shipping_address.address_line_1}
                                onChange={(e) => setData('shipping_address', {...data.shipping_address, address_line_1: e.target.value})}
                                placeholder={t('Enter shipping address')}
                                required
                            />
                            <InputError message={errors['shipping_address.address_line_1']} />
                        </div>
                        <div>
                            <Label htmlFor="shipping_address_2">{t('Address Line 2')}</Label>
                            <Input
                                id="shipping_address_2"
                                value={data.shipping_address.address_line_2}
                                onChange={(e) => setData('shipping_address', {...data.shipping_address, address_line_2: e.target.value})}
                                placeholder={t('Apartment, suite, etc. (optional)')}
                            />
                            <InputError message={errors['shipping_address.address_line_2']} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="shipping_city">{t('City')}</Label>
                                <Input
                                    id="shipping_city"
                                    value={data.shipping_address.city}
                                    onChange={(e) => setData('shipping_address', {...data.shipping_address, city: e.target.value})}
                                    placeholder={t('Enter city')}
                                    required
                                />
                                <InputError message={errors['shipping_address.city']} />
                            </div>
                            <div>
                                <Label htmlFor="shipping_state">{t('State')}</Label>
                                <Input
                                    id="shipping_state"
                                    value={data.shipping_address.state}
                                    onChange={(e) => setData('shipping_address', {...data.shipping_address, state: e.target.value})}
                                    placeholder={t('Enter state')}
                                    required
                                />
                                <InputError message={errors['shipping_address.state']} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="shipping_country">{t('Country')}</Label>
                                <Input
                                    id="shipping_country"
                                    value={data.shipping_address.country}
                                    onChange={(e) => setData('shipping_address', {...data.shipping_address, country: e.target.value})}
                                    placeholder={t('Enter country')}
                                    required
                                />
                                <InputError message={errors['shipping_address.country']} />
                            </div>
                            <div>
                                <Label htmlFor="shipping_zip">{t('Zip Code')}</Label>
                                <Input
                                    id="shipping_zip"
                                    value={data.shipping_address.zip_code}
                                    onChange={(e) => setData('shipping_address', {...data.shipping_address, zip_code: e.target.value})}
                                    placeholder={t('Enter zip code')}
                                    required
                                />
                                <InputError message={errors['shipping_address.zip_code']} />
                            </div>
                        </div>
                    </div>
                )}

                <div>
                    <Label htmlFor="edit_notes">{t('Notes')}</Label>
                    <Textarea
                        id="edit_notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder={t('Enter notes')}
                        rows={3}
                    />
                    <InputError message={errors.notes} />
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Updating...') : t('Update')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}