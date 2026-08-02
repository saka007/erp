import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { User } from './types';

interface IndustryAccessProps {
    user: User;
    onSuccess: () => void;
}

export default function IndustryAccess({ user, onSuccess }: IndustryAccessProps) {
    const { t } = useTranslation();
    const { data, setData, put, processing } = useForm({
        industry_type: user.industry_type || 'standard',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        put(route('users.industry.update', user.id), {
            onSuccess,
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Industry Access')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-5">
                <div>
                    <p className="text-sm font-medium text-gray-900">{user.name}</p>
                    <p className="text-sm text-gray-500">{user.email}</p>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="industry-type">{t('Industry')}</Label>
                    <Select value={data.industry_type} onValueChange={(value) => setData('industry_type', value as 'standard' | 'textile')}>
                        <SelectTrigger id="industry-type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="standard">{t('Standard')}</SelectItem>
                            <SelectItem value="textile">{t('Textile')}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </div>
            </form>
        </DialogContent>
    );
}