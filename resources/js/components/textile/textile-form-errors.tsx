import { AlertCircle } from 'lucide-react';

interface TextileFormErrorsProps {
    /** Record of field => error message (e.g. Inertia useForm().errors). */
    errors: Record<string, string>;
}

/**
 * Renders all form validation errors in a single dismissible-looking banner.
 * The textile Field/SelectField components do not render per-field errors, so
 * forms should include this at the top to surface backend validation failures.
 */
export function TextileFormErrors({ errors }: TextileFormErrorsProps) {
    const messages = Object.values(errors).filter(Boolean);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
            <div className="space-y-1">
                {messages.map((message) => (
                    <p key={message}>{message}</p>
                ))}
            </div>
        </div>
    );
}
