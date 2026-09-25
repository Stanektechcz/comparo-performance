import type { ReactNode } from 'react';
import {
    FieldError,
    fieldLabel,
    textFieldStyles,
} from '@/components/admin/action-dialog';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/merchant';

export { FieldError, fieldLabel, textFieldStyles };

type FieldShellProps = {
    id: string;
    label: ReactNode;
    hint?: ReactNode;
    error?: string;
    children: (describedBy: string | undefined) => ReactNode;
    className?: string;
};

/** Label + control + hint + error, wired with aria-describedby. */
export function FieldShell({
    id,
    label,
    hint,
    error,
    children,
    className,
}: FieldShellProps) {
    const hintId = hint ? `${id}-hint` : null;
    const errorId = error ? `${id}-error` : null;
    const describedBy =
        [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('flex min-w-0 flex-col gap-1.5', className)}>
            <label htmlFor={id} className={fieldLabel}>
                {label}
            </label>
            {children(describedBy)}
            {hint ? (
                <p id={hintId ?? undefined} className="text-xs text-text-3">
                    {hint}
                </p>
            ) : null}
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}

type TextFieldProps = {
    id: string;
    name: string;
    label: ReactNode;
    hint?: ReactNode;
    error?: string;
    defaultValue?: string;
    type?: 'text' | 'url' | 'password';
    required?: boolean;
    placeholder?: string;
    autoComplete?: string;
    maxLength?: number;
    disabled?: boolean;
    className?: string;
};

export function TextField({
    id,
    name,
    label,
    hint,
    error,
    defaultValue,
    type = 'text',
    required = false,
    placeholder,
    autoComplete = 'off',
    maxLength,
    disabled = false,
    className,
}: TextFieldProps) {
    return (
        <FieldShell
            id={id}
            label={label}
            hint={hint}
            error={error}
            className={className}
        >
            {(describedBy) => (
                <input
                    id={id}
                    name={name}
                    type={type}
                    defaultValue={defaultValue}
                    required={required}
                    placeholder={placeholder}
                    autoComplete={autoComplete}
                    maxLength={maxLength}
                    disabled={disabled}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    className={textFieldStyles}
                />
            )}
        </FieldShell>
    );
}

type SelectFieldProps<T extends string | number> = {
    id: string;
    name: string;
    label: ReactNode;
    options: Option<T>[];
    hint?: ReactNode;
    error?: string;
    value?: T | '';
    defaultValue?: T | '';
    onChange?: (value: string) => void;
    emptyLabel?: string;
    disabled?: boolean;
    className?: string;
};

export function SelectField<T extends string | number>({
    id,
    name,
    label,
    options,
    hint,
    error,
    value,
    defaultValue,
    onChange,
    emptyLabel,
    disabled = false,
    className,
}: SelectFieldProps<T>) {
    return (
        <FieldShell
            id={id}
            label={label}
            hint={hint}
            error={error}
            className={className}
        >
            {(describedBy) => (
                <select
                    id={id}
                    name={name}
                    value={value}
                    defaultValue={
                        value === undefined ? defaultValue : undefined
                    }
                    onChange={
                        onChange
                            ? (event) => onChange(event.target.value)
                            : undefined
                    }
                    disabled={disabled}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    className={cn(textFieldStyles, 'min-h-11 py-2.5')}
                >
                    {emptyLabel !== undefined ? (
                        <option value="">{emptyLabel}</option>
                    ) : null}
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            )}
        </FieldShell>
    );
}
