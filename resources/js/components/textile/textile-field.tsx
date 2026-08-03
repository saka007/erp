import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface TextileFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    placeholder?: string;
    min?: string | number;
    max?: string | number;
    step?: string | number;
}

export function TextileField({ label, value, onChange, type = 'text', required = false, placeholder, min, max, step }: TextileFieldProps) {
    return (
        <div>
            <Label>{label}</Label>
            <Input type={type} value={value} required={required} placeholder={placeholder} min={min} max={max} step={step} onChange={(event) => onChange(event.target.value)} />
        </div>
    );
}
