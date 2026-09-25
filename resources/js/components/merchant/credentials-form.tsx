import { Form, Link } from '@inertiajs/react';
import { useId, useState } from 'react';
import FeedCredentialsController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedCredentialsController';
import { buttonStyles } from '@/components/comparo/button-styles';
import { Notice } from '@/components/comparo/notice';
import {
    FieldError,
    SelectField,
    TextField,
} from '@/components/merchant/form-fields';

type CredentialType = 'none' | 'basic' | 'bearer' | 'header';

const TYPES: { value: CredentialType; label: string }[] = [
    { value: 'basic', label: 'User name and password (HTTP Basic)' },
    { value: 'bearer', label: 'Bearer token' },
    { value: 'header', label: 'Custom header' },
    { value: 'none', label: 'No credentials (remove them)' },
];

type CredentialsFormProps = {
    feedId: number;
    hasCredentials: boolean;
    confirmed: boolean;
    canManage: boolean;
};

/**
 * Write-only feed credentials. The form is never pre-filled: stored secrets
 * never reach the browser, only whether credentials are set.
 */
export function CredentialsForm({
    feedId,
    hasCredentials,
    confirmed,
    canManage,
}: CredentialsFormProps) {
    const id = useId();
    const [type, setType] = useState<CredentialType>('basic');
    const state = hasCredentials
        ? 'Credentials are set. They are never shown again; enter new ones to replace them.'
        : 'No credentials are set. Add them if your feed server requires a login.';

    if (!canManage) {
        return (
            <p className="text-[13px] text-text-3">
                {state} Only the owner of your merchant account can change them.
            </p>
        );
    }

    if (!confirmed) {
        return (
            <div className="flex flex-col items-start gap-3">
                <p className="text-[13px] text-text-2">{state}</p>
                <Link
                    href={FeedCredentialsController.confirm.url(feedId)}
                    className={buttonStyles.secondary}
                >
                    Confirm your password to change credentials
                </Link>
            </div>
        );
    }

    return (
        <Form
            {...FeedCredentialsController.update.form(feedId)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            resetOnError={['password', 'token', 'header_value']}
            className="flex flex-col gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <Notice
                        tone={hasCredentials ? 'ok' : 'neutral'}
                        title={
                            hasCredentials
                                ? 'Credentials are set'
                                : 'No credentials set'
                        }
                    >
                        {state}
                    </Notice>
                    <SelectField
                        id={`${id}-type`}
                        name="type"
                        label="Access method"
                        options={TYPES}
                        value={type}
                        onChange={(value) => setType(value as CredentialType)}
                        error={errors.type}
                    />
                    {type === 'basic' ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id={`${id}-username`}
                                name="username"
                                label="User name"
                                required
                                maxLength={255}
                                autoComplete="off"
                                error={errors.username}
                            />
                            <TextField
                                id={`${id}-password`}
                                name="password"
                                type="password"
                                label="Password"
                                required
                                maxLength={1024}
                                autoComplete="new-password"
                                error={errors.password}
                            />
                        </div>
                    ) : null}
                    {type === 'bearer' ? (
                        <TextField
                            id={`${id}-token`}
                            name="token"
                            type="password"
                            label="Token"
                            required
                            maxLength={1024}
                            autoComplete="new-password"
                            error={errors.token}
                        />
                    ) : null}
                    {type === 'header' ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id={`${id}-header-name`}
                                name="header_name"
                                label="Header name"
                                required
                                maxLength={64}
                                placeholder="X-Api-Key"
                                error={errors.header_name}
                            />
                            <TextField
                                id={`${id}-header-value`}
                                name="header_value"
                                type="password"
                                label="Header value"
                                required
                                maxLength={1024}
                                autoComplete="new-password"
                                error={errors.header_value}
                            />
                        </div>
                    ) : null}
                    <FieldError
                        id={`${id}-credentials-error`}
                        message={errors.credentials}
                    />
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.primary}
                    >
                        {processing
                            ? 'Saving…'
                            : type === 'none'
                              ? 'Remove credentials'
                              : 'Save credentials'}
                    </button>
                </>
            )}
        </Form>
    );
}
