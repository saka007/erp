import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import InputError from "@/components/ui/input-error";
import { PhoneInputComponent } from "@/components/ui/phone-input";
import { MultiSelectEnhanced } from "@/components/ui/multi-select-enhanced";
import { EditUserProps, EditUserFormData } from './types';

export default function Edit({ user, onSuccess, roles = {}, branches = [] }: EditUserProps) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm<EditUserFormData>({
        name: user.name,
        email: user.email,
        mobile_no: user.mobile_no,
        is_enable_login: user.is_enable_login,
        branch_ids: user.branch_ids || [],
    });
    const [branchError, setBranchError] = useState('');

    const hasBranches = branches.length > 0;
    // Staff users must always be branch scoped: branch assignment is mandatory
    // for staff-type accounts when the tenant has branches. Company/superadmin
    // accounts are tenant roots and are not branch scoped.
    const isTenantRoot = user.type === 'company' || user.type === 'superadmin';
    const branchRequired = hasBranches && !isTenantRoot;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (branchRequired && data.branch_ids.length === 0) {
            setBranchError(t('At least one branch must be assigned.'));
            return;
        }
        setBranchError('');
        put(route('users.update', user.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Edit User')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="edit_name">{t('Name')}</Label>
                    <Input
                        id="edit_name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder={t('Enter full name')}
                        required
                    />
                    <InputError message={errors.name} />
                </div>
                <div>
                    <Label htmlFor="edit_email">{t('Email')}</Label>
                    <Input
                        id="edit_email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder={t('Enter email address')}
                        required
                    />
                    <InputError message={errors.email} />
                </div>
                <div>
                    <PhoneInputComponent
                        label={t('Mobile Number')}
                        value={data.mobile_no}
                        onChange={(value) => setData('mobile_no', value)}
                        placeholder="+1234567890"
                        error={errors.mobile_no}
                    />
                </div>

                <div>
                    <Label htmlFor="edit_is_enable_login">{t('Login Status')}</Label>
                    <Select value={data.is_enable_login ? "1" : "0"} onValueChange={(value) => setData('is_enable_login', value === "1")}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">{t('Enabled')}</SelectItem>
                            <SelectItem value="0">{t('Disabled')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.is_enable_login} />
                </div>
                {hasBranches && (
                    <div>
                        <Label>
                            {t('Branches')}
                            {branchRequired && <span className="text-destructive"> *</span>}
                        </Label>
                        <MultiSelectEnhanced
                            options={branches.map((branch) => ({ value: String(branch.id), label: branch.name }))}
                            value={data.branch_ids.map(String)}
                            onValueChange={(values) => setData('branch_ids', values.map(Number))}
                            placeholder={t('Select branches')}
                            searchable
                        />
                        <p className="text-xs text-muted-foreground mt-1">
                            {t('User with a single branch is auto-scoped. Multiple branches enables the branch switcher.')}
                        </p>
                        {branchError && (
                            <p className="text-sm font-medium text-destructive mt-1">{branchError}</p>
                        )}
                        <InputError message={errors.branch_ids as any} />
                    </div>
                )}
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