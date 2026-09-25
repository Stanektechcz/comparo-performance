import { useState } from 'react';
import type { ReactNode } from 'react';
import { buttonStyles } from '@/components/comparo/button-styles';
import { ComparoDialogContent } from '@/components/comparo/comparo-dialog';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type ActionDialogProps = {
    triggerLabel: string;
    variant?: 'primary' | 'secondary';
    /** A reason renders the trigger disabled with the reason as visible text. */
    disabledReason?: string | null;
    title: ReactNode;
    description: ReactNode;
    children: (close: () => void) => ReactNode;
    className?: string;
};

/**
 * A staff action behind a confirmation dialog. Radix traps focus, closes on
 * Escape and returns focus to the trigger; the server still enforces every
 * permission.
 */
export function ActionDialog({
    triggerLabel,
    variant = 'secondary',
    disabledReason = null,
    title,
    description,
    children,
    className,
}: ActionDialogProps) {
    const [open, setOpen] = useState(false);

    if (disabledReason) {
        return (
            <div className={cn('flex flex-col gap-1.5', className)}>
                <button
                    type="button"
                    disabled
                    className={buttonStyles.disabled}
                >
                    {triggerLabel}
                </button>
                <p className="text-xs text-text-3">{disabledReason}</p>
            </div>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className={cn(buttonStyles[variant], className)}
                >
                    {triggerLabel}
                </button>
            </DialogTrigger>
            <ComparoDialogContent title={title} description={description}>
                {children(() => setOpen(false))}
            </ComparoDialogContent>
        </Dialog>
    );
}

export const fieldLabel = 'text-[12px] font-extrabold text-text-2';

export const textFieldStyles =
    'w-full rounded-field border border-line-2 bg-surface-2 px-3.5 py-3 text-[13.5px] text-text placeholder:text-text-4';

export function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} className="text-[12.5px] font-semibold text-danger-2">
            {message}
        </p>
    ) : null;
}

type NoteFieldProps = {
    id: string;
    required?: boolean;
    error?: string;
    label?: string;
};

/** Free-text note stored on the decision (max 1000 characters). */
export function NoteField({
    id,
    required = false,
    error,
    label,
}: NoteFieldProps) {
    const errorId = `${id}-error`;

    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className={fieldLabel}>
                {label ?? (required ? 'Note (required)' : 'Note (optional)')}
            </label>
            <textarea
                id={id}
                name="note"
                rows={3}
                maxLength={1000}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                className={textFieldStyles}
            />
            <FieldError id={errorId} message={error} />
        </div>
    );
}
