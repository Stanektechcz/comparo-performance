import { Form, Head, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import FeedMappingController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedMappingController';
import FeedSourceController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedSourceController';
import { PageHeader } from '@/components/catalog/page-header';
import { Badge } from '@/components/comparo/badge';
import { buttonStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import { Notice } from '@/components/comparo/notice';
import { FieldError, textFieldStyles } from '@/components/merchant/form-fields';
import { MerchantPage } from '@/components/merchant/page';
import type { Tone } from '@/lib/tones';
import { cn } from '@/lib/utils';
import type {
    FeedMappingProps,
    MappingField,
    PreviewRow,
} from '@/types/merchant';

const rowTones: Record<PreviewRow['status'], Tone> = {
    valid: 'ok',
    warning: 'warn',
    invalid: 'danger',
};

const rowLabels: Record<PreviewRow['status'], string> = {
    valid: 'Valid',
    warning: 'Imported with warnings',
    invalid: 'Would be skipped',
};

type MappingState = Record<string, string>;

function FieldRow({
    field,
    headers,
    value,
    onChange,
    error,
    readOnly,
}: {
    field: MappingField;
    headers: string[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
    readOnly: boolean;
}) {
    const id = useId();
    const describedBy =
        [field.hint ? `${id}-hint` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined;
    const knownValue = value === '' || headers.includes(value);

    return (
        <li className="grid gap-1.5 border-b border-line-soft py-3 last:border-b-0 sm:grid-cols-[minmax(10rem,14rem)_minmax(0,1fr)] sm:items-start sm:gap-4">
            <label htmlFor={`${id}-source`} className="pt-2.5 text-[13px]">
                <span className="font-bold text-text">{field.label}</span>
                {field.required ? (
                    <span className="ml-1.5 text-[11px] font-extrabold text-warn">
                        required
                    </span>
                ) : null}
            </label>
            <div className="flex min-w-0 flex-col gap-1">
                {headers.length > 0 ? (
                    <select
                        id={`${id}-source`}
                        name={`mapping[${field.key}]`}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        disabled={readOnly}
                        required={field.required}
                        aria-invalid={error ? true : undefined}
                        aria-describedby={describedBy}
                        className={cn(textFieldStyles, 'min-h-11 py-2.5')}
                    >
                        <option value="">
                            {field.required ? 'Choose a column…' : 'Not mapped'}
                        </option>
                        {knownValue ? null : (
                            <option value={value}>
                                {value} (not in the latest file)
                            </option>
                        )}
                        {headers.map((header) => (
                            <option key={header} value={header}>
                                {header}
                            </option>
                        ))}
                    </select>
                ) : (
                    <input
                        id={`${id}-source`}
                        name={`mapping[${field.key}]`}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        disabled={readOnly}
                        required={field.required}
                        maxLength={255}
                        placeholder="Column, element or key name"
                        aria-invalid={error ? true : undefined}
                        aria-describedby={describedBy}
                        className={textFieldStyles}
                    />
                )}
                {field.hint ? (
                    <p id={`${id}-hint`} className="text-xs text-text-3">
                        {field.hint}
                    </p>
                ) : null}
                <FieldError id={`${id}-error`} message={error} />
            </div>
        </li>
    );
}

function SampleRow({ row }: { row: PreviewRow }) {
    return (
        <li className="flex min-w-0 flex-col gap-3 rounded-card border border-line bg-surface p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span className="num text-[12px] font-bold text-text-3">
                    Line {row.lineNumber}
                </span>
                <Badge tone={rowTones[row.status]}>
                    {rowLabels[row.status]}
                </Badge>
                {row.sku ? (
                    <span className="num text-[12px] break-all text-text-2">
                        SKU {row.sku}
                    </span>
                ) : null}
            </div>
            {row.values.length > 0 ? (
                <dl className="grid grid-cols-[minmax(6rem,auto)_minmax(0,1fr)] gap-x-3 gap-y-1 text-[12.5px]">
                    {row.values.map((value) => (
                        <div key={value.key} className="contents">
                            <dt className="text-text-3">{value.label}</dt>
                            <dd className="num break-all text-text">
                                {value.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : null}
            {row.issues.length > 0 ? (
                <ul
                    className="flex flex-col gap-1.5"
                    aria-label={`Issues on line ${row.lineNumber}`}
                >
                    {row.issues.map((issue, index) => (
                        <li
                            key={`${issue.code}-${index}`}
                            className={cn(
                                'rounded-field px-3 py-2 text-[12.5px]',
                                issue.severity === 'warning'
                                    ? 'bg-warn-tint text-warn-2'
                                    : 'bg-danger-tint text-danger-2',
                            )}
                        >
                            {issue.message}
                        </li>
                    ))}
                </ul>
            ) : null}
        </li>
    );
}

export default function FeedMapping({
    feed,
    fields,
    preview,
    can,
}: FeedMappingProps) {
    const [mapping, setMapping] = useState<MappingState>(() => ({
        ...preview.mapping,
    }));
    const [previewing, setPreviewing] = useState(false);
    const readOnly = !can.update;

    function update(field: string, value: string): void {
        setMapping((current) => ({ ...current, [field]: value }));
    }

    function previewDraft(): void {
        const draft = Object.fromEntries(
            Object.entries(mapping).filter(([, value]) => value !== ''),
        );

        router.get(
            FeedMappingController.edit.url(feed.id),
            { draft },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['preview'],
                onStart: () => setPreviewing(true),
                onFinish: () => setPreviewing(false),
            },
        );
    }

    return (
        <>
            <Head title={`Field mapping · ${feed.name}`} />
            <MerchantPage
                back={{
                    href: FeedSourceController.show(feed.id),
                    label: 'Back to the feed',
                }}
            >
                <PageHeader eyebrow={feed.name} title="Field mapping">
                    Tell Comparo which column of your feed holds each product
                    fact. A saved mapping applies from the next run; earlier
                    versions are kept.
                </PageHeader>

                <div className="mt-4 flex flex-col gap-3">
                    {!preview.available ? (
                        <Notice tone="info" title="No feed file to preview yet">
                            {feed.transport.value === 'upload'
                                ? 'Upload a feed file on the feed page first. You can still type the column names below.'
                                : 'Run the feed once so Comparo has a copy of it. You can still type the column names below.'}
                        </Notice>
                    ) : null}
                    {preview.error ? (
                        <Notice
                            tone="danger"
                            title="The feed file could not be read"
                        >
                            {preview.error.message}
                            {preview.error.line !== null
                                ? ` (line ${preview.error.line})`
                                : ''}
                        </Notice>
                    ) : null}
                    {preview.mappingSuggested && preview.available ? (
                        <Notice tone="info" title="Suggested mapping">
                            No mapping is saved yet. The columns below were
                            suggested from your column names; check and save
                            them.
                        </Notice>
                    ) : null}
                    {preview.missingRequired.length > 0 ? (
                        <Notice
                            tone="warn"
                            title="Required fields are not mapped"
                        >
                            {preview.missingRequired
                                .map((field) => field.label)
                                .join(', ')}
                            . Rows without them are skipped.
                        </Notice>
                    ) : null}
                    {readOnly ? (
                        <Notice tone="info" title="Read-only access">
                            Your role can view the mapping but not change it.
                        </Notice>
                    ) : null}
                </div>

                <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <Form
                        {...FeedMappingController.update.form(feed.id)}
                        options={{ preserveScroll: true }}
                        className="flex min-w-0 flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <fieldset className="rounded-card border border-line bg-surface px-4 py-1 sm:px-5">
                                    <legend className="sr-only">
                                        Canonical fields
                                    </legend>
                                    <ul>
                                        {fields.map((field) => (
                                            <FieldRow
                                                key={field.key}
                                                field={field}
                                                headers={preview.headers}
                                                value={mapping[field.key] ?? ''}
                                                onChange={(value) =>
                                                    update(field.key, value)
                                                }
                                                error={
                                                    errors[
                                                        `mapping.${field.key}`
                                                    ]
                                                }
                                                readOnly={readOnly}
                                            />
                                        ))}
                                    </ul>
                                </fieldset>
                                <FieldError
                                    id="mapping-error"
                                    message={errors.mapping}
                                />
                                <div className="flex flex-wrap gap-3">
                                    {preview.available ? (
                                        <button
                                            type="button"
                                            onClick={previewDraft}
                                            disabled={previewing}
                                            className={buttonStyles.secondary}
                                        >
                                            {previewing
                                                ? 'Updating preview…'
                                                : 'Preview with these columns'}
                                        </button>
                                    ) : null}
                                    {readOnly ? null : (
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className={buttonStyles.primary}
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Save mapping'}
                                        </button>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>

                    <section
                        aria-labelledby="sample-heading"
                        aria-busy={previewing}
                        className="min-w-0"
                    >
                        <h2
                            id="sample-heading"
                            className="text-[18px] font-black text-text"
                        >
                            Sample rows
                        </h2>
                        <p
                            className="mt-1 text-[13px] text-text-3"
                            role="status"
                        >
                            {preview.isDraft
                                ? 'Showing the first rows with your unsaved columns.'
                                : 'Showing the first rows of the latest feed file with the current mapping.'}
                        </p>
                        {preview.rows.length === 0 ? (
                            <EmptyState title="No sample rows" className="mt-3">
                                Sample rows appear once a feed file has been
                                received.
                            </EmptyState>
                        ) : (
                            <ol className="mt-3 flex flex-col gap-3">
                                {preview.rows.map((row) => (
                                    <SampleRow key={row.lineNumber} row={row} />
                                ))}
                            </ol>
                        )}
                    </section>
                </div>
            </MerchantPage>
        </>
    );
}

FeedMapping.layout = (props: FeedMappingProps) => ({
    breadcrumbs: [
        { title: 'Feeds', href: FeedSourceController.index() },
        {
            title: props.feed.name,
            href: FeedSourceController.show(props.feed.id),
        },
        {
            title: 'Field mapping',
            href: FeedMappingController.edit(props.feed.id),
        },
    ],
});
