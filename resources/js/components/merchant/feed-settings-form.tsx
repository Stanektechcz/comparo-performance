import { Form } from '@inertiajs/react';
import { useId, useState } from 'react';
import FeedSourceController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedSourceController';
import { buttonStyles } from '@/components/comparo/button-styles';
import {
    FieldError,
    SelectField,
    TextField,
} from '@/components/merchant/form-fields';
import type {
    FeedFormat,
    FeedFormDefaults,
    FeedFormOptions,
    FeedTransport,
} from '@/types/merchant';

type FeedSettingsFormProps = {
    options: FeedFormOptions;
    /** Absent for a new feed. */
    feedId?: number;
    defaults?: FeedFormDefaults;
};

const EMPTY_DEFAULTS: FeedFormDefaults = {
    name: '',
    format: 'csv',
    transport: 'url',
    maskedUrl: null,
    currency: 'EUR',
    country: null,
    encoding: 'UTF-8',
    delimiter: '',
    recordElement: '',
    intervalMinutes: 360,
    hasCredentials: false,
};

/**
 * Feed settings for create and edit. The stored URL is never sent to the
 * browser: on edit the field starts empty and "empty" keeps the stored URL.
 */
export function FeedSettingsForm({
    options,
    feedId,
    defaults = EMPTY_DEFAULTS,
}: FeedSettingsFormProps) {
    const id = useId();
    const [format, setFormat] = useState<FeedFormat>(defaults.format);
    const [transport, setTransport] = useState<FeedTransport>(
        defaults.transport === 'upload' ? 'upload' : 'url',
    );
    const isEdit = feedId !== undefined;
    const form = isEdit
        ? FeedSourceController.update.form(feedId)
        : FeedSourceController.store.form();
    const hasStoredUrl = isEdit && defaults.maskedUrl !== null;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <fieldset className="grid gap-4 rounded-card border border-line bg-surface p-4 sm:grid-cols-2 sm:p-5">
                        <legend className="px-1 text-[14px] font-extrabold text-text">
                            Feed
                        </legend>
                        <TextField
                            id={`${id}-name`}
                            name="name"
                            label="Name"
                            required
                            maxLength={96}
                            defaultValue={defaults.name}
                            hint="Only your team sees this name."
                            error={errors.name}
                            className="sm:col-span-2"
                        />
                        <fieldset className="flex flex-col gap-2 sm:col-span-2">
                            <legend className="text-[12px] font-extrabold text-text-2">
                                How does the feed reach Comparo?
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {options.transports.map((option) => (
                                    <label
                                        key={option.value}
                                        className="flex min-h-11 cursor-pointer items-center gap-2 rounded-field border border-line-2 px-3 text-[13px] font-bold text-text has-[:checked]:border-acc has-[:checked]:bg-acc-tint"
                                    >
                                        <input
                                            type="radio"
                                            name="transport"
                                            value={option.value}
                                            checked={transport === option.value}
                                            onChange={() =>
                                                setTransport(option.value)
                                            }
                                            className="accent-(--acc)"
                                        />
                                        {option.label}
                                    </label>
                                ))}
                            </div>
                            <FieldError
                                id={`${id}-transport-error`}
                                message={errors.transport}
                            />
                        </fieldset>
                        {transport === 'url' ? (
                            <>
                                <TextField
                                    id={`${id}-url`}
                                    name="url"
                                    type="url"
                                    label={
                                        hasStoredUrl
                                            ? 'New feed URL (optional)'
                                            : 'Feed URL'
                                    }
                                    required={!hasStoredUrl}
                                    maxLength={2048}
                                    placeholder="https://shop.example.com/feed.xml"
                                    hint={
                                        hasStoredUrl
                                            ? `Leave empty to keep the current URL (${defaults.maskedUrl}). The full address is never shown again because it may contain an access token.`
                                            : 'A public http:// or https:// address on port 80 or 443. Tokens in the address are stored but never shown again.'
                                    }
                                    error={errors.url}
                                    className="sm:col-span-2"
                                />
                                <SelectField
                                    id={`${id}-interval`}
                                    name="interval_minutes"
                                    label="Schedule"
                                    options={options.intervals}
                                    defaultValue={
                                        defaults.intervalMinutes ?? ''
                                    }
                                    emptyLabel="Manual runs only"
                                    hint="Scheduled runs start after the first successful run."
                                    error={errors.interval_minutes}
                                />
                            </>
                        ) : (
                            <p className="rounded-field bg-surface-2 px-3.5 py-3 text-[13px] text-text-2 sm:col-span-2">
                                Upload feeds run when you upload a file on the
                                feed page. They are never scheduled.
                            </p>
                        )}
                    </fieldset>

                    <fieldset className="grid gap-4 rounded-card border border-line bg-surface p-4 sm:grid-cols-2 sm:p-5">
                        <legend className="px-1 text-[14px] font-extrabold text-text">
                            File format
                        </legend>
                        <SelectField
                            id={`${id}-format`}
                            name="format"
                            label="Format"
                            options={options.formats}
                            value={format}
                            onChange={(value) => setFormat(value as FeedFormat)}
                            error={errors.format}
                        />
                        <SelectField
                            id={`${id}-encoding`}
                            name="encoding"
                            label="Text encoding"
                            options={options.encodings}
                            defaultValue={defaults.encoding}
                            error={errors.encoding}
                        />
                        {format === 'csv' ? (
                            <SelectField
                                id={`${id}-delimiter`}
                                name="delimiter"
                                label="Column separator"
                                options={options.delimiters}
                                defaultValue={defaults.delimiter}
                                emptyLabel="Detect automatically"
                                error={errors.delimiter}
                            />
                        ) : (
                            <TextField
                                id={`${id}-record`}
                                name="record_element"
                                label={
                                    format === 'xml'
                                        ? 'Product element (optional)'
                                        : 'Product list key (optional)'
                                }
                                maxLength={64}
                                defaultValue={defaults.recordElement}
                                placeholder={
                                    format === 'xml' ? 'SHOPITEM' : 'products'
                                }
                                hint="Leave empty to detect it."
                                error={errors.record_element}
                            />
                        )}
                    </fieldset>

                    <fieldset className="grid gap-4 rounded-card border border-line bg-surface p-4 sm:grid-cols-2 sm:p-5">
                        <legend className="px-1 text-[14px] font-extrabold text-text">
                            Market and currency
                        </legend>
                        <SelectField
                            id={`${id}-currency`}
                            name="currency"
                            label="Default currency"
                            options={options.currencies}
                            defaultValue={defaults.currency}
                            hint="Used for rows without a currency column."
                            error={errors.currency}
                        />
                        <SelectField
                            id={`${id}-country`}
                            name="country"
                            label="Market"
                            options={options.markets}
                            defaultValue={defaults.country ?? ''}
                            emptyLabel="Not set"
                            hint="Without a market, products cannot be cleared for compliance and matched offers stay unpublished."
                            error={errors.country}
                        />
                    </fieldset>

                    <FieldError id={`${id}-feed-error`} message={errors.feed} />

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className={buttonStyles.primary}
                        >
                            {processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save settings'
                                  : 'Create feed'}
                        </button>
                    </div>
                </>
            )}
        </Form>
    );
}
