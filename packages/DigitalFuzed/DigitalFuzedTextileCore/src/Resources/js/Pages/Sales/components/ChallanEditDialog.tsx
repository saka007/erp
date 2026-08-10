import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Save, X } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { TextileField as Field } from '@/components/textile/textile-field';
import { TextileSelectField as SelectField } from '@/components/textile/textile-select-field';
import { InputError } from '@/components/ui/input-error';

export interface ChallanEditRecord {
    id: number;
    document_number: string;
    party_name?: string | null;
    lot_reference?: string | null;
    quantity: string | number;
    unit?: string | null;
    status: string;
}

interface ChallanEditDialogProps {
    challan: ChallanEditRecord;
    unitOptions: string[];
    onClose: () => void;
}

export function ChallanEditDialog({ challan, unitOptions, onClose }: ChallanEditDialogProps) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm({
        challan_id: challan.id,
        party_name: challan.party_name ?? '',
        lot_reference: challan.lot_reference ?? '',
        quantity: challan.quantity?.toString() ?? '',
        unit: challan.unit ?? '',
    });

    return (
        <Dialog open={true} onOpenChange={(open) => { if (!open) onClose(); }}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Edit Challan')} — {challan.document_number}</DialogTitle>
                    <DialogDescription>
                        {t('Only draft challans can be edited.')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(route('textile.sales.challans.update'), {
                            onSuccess: onClose,
                        });
                    }}
                >
                    <Field
                        label={t('Party')}
                        value={data.party_name}
                        onChange={(value) => setData('party_name', value)}
                        required
                    />
                    <Field
                        label={t('Lot Reference')}
                        value={data.lot_reference}
                        onChange={(value) => setData('lot_reference', value)}
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <Field
                            label={t('Quantity')}
                            value={data.quantity}
                            onChange={(value) => setData('quantity', value)}
                            type="number"
                            min="0"
                            step="any"
                            required
                        />
                        <SelectField
                            label={t('Unit')}
                            value={data.unit}
                            onChange={(value) => setData('unit', value)}
                            options={unitOptions}
                            includeEmpty
                            emptyLabel={t('Select unit')}
                            required
                        />
                    </div>
                    <InputError message={errors.challan_id} className="mt-1" />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            <X className="mr-2 h-4 w-4" />
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {t('Save Challan')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
