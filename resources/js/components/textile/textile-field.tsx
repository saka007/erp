import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/ui/input-error';

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
    disabled?: boolean;
    helperText?: ReactNode;
    error?: string;
}

export function TextileField({ label, value, onChange, type = 'text', required = false, placeholder, min, max, step, disabled = false, helperText, error }: TextileFieldProps) {
    return (
        <div>
            <Label>{label}</Label>
            <Input type={type} value={value} required={required} placeholder={placeholder} min={min} max={max} step={step} disabled={disabled} onChange={(event) => onChange(event.target.value)} />
            {helperText ? <p className="text-xs text-muted-foreground mt-1">{helperText}</p> : null}
            <InputError message={error} className="mt-1" />
        </div>
    );
}
