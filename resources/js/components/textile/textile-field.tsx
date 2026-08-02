import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface TextileFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    placeholder?: string;
}

export function TextileField({ label, value, onChange, type = 'text', required = false, placeholder }: TextileFieldProps) {
    return (
        <div>
            <Label>{label}</Label>
            <Input type={type} value={value} required={required} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} />
        </div>
    );
}
