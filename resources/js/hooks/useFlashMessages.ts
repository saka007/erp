import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';

interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
}

const flattenError = (value: unknown): string => {
    if (typeof value === 'string') {
        return value;
    }
    if (Array.isArray(value)) {
        return value.map((item) => flattenError(item)).join(' ');
    }
    if (value && typeof value === 'object') {
        return Object.values(value as Record<string, unknown>).map((item) => flattenError(item)).join(' ');
    }
    return String(value ?? '');
};

export const useFlashMessages = () => {
    const { flash, errors } = usePage().props as {
        flash?: FlashMessages;
        errors?: Record<string, unknown>;
    };

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.warning) {
            toast.warning(flash.warning);
        }
        // Validation / business-rule errors returned via withErrors() are
        // passed through page.props.errors. Surface them as a toast popup so
        // the user sees why the action failed even when the form has no inline
        // error display.
        if (errors && Object.keys(errors).length > 0) {
            const messages = Object.values(errors).map(flattenError).filter(Boolean);
            if (messages.length > 0) {
                toast.error(messages.join(' '));
            }
        }
    }, [flash, errors]);
};