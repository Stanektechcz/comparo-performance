import { Form } from '@inertiajs/react';
import { useId } from 'react';
import FeedRunController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedRunController';
import FeedStatusController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedStatusController';
import FeedUploadController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedUploadController';
import { buttonStyles } from '@/components/comparo/button-styles';
import { FieldError, fieldLabel } from '@/components/merchant/form-fields';
import { cn } from '@/lib/utils';
import type { FeedAbilities } from '@/types/merchant';

const ACCEPTED_FILES = '.csv,.tsv,.txt,.xml,.json,.gz';

function RunNowForm({ feedId }: { feedId: number }) {
    const id = useId();

    return (
        <Form
            {...FeedRunController.store.form(feedId)}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2"
        >
            {({ errors, processing }) => (
                <>
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.primary}
                    >
                        {processing ? 'Starting…' : 'Run now'}
                    </button>
                    <FieldError id={`${id}-run`} message={errors.run} />
                </>
            )}
        </Form>
    );
}

function UploadForm({ feedId }: { feedId: number }) {
    const id = useId();

    return (
        <Form
            {...FeedUploadController.store.form(feedId)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-col gap-2"
        >
            {({ errors, processing, progress }) => (
                <>
                    <label htmlFor={`${id}-file`} className={fieldLabel}>
                        Feed file (CSV, TSV, TXT, XML or JSON, optionally .gz)
                    </label>
                    <input
                        id={`${id}-file`}
                        name="file"
                        type="file"
                        accept={ACCEPTED_FILES}
                        required
                        aria-invalid={errors.file ? true : undefined}
                        aria-describedby={
                            errors.file ? `${id}-file-error` : undefined
                        }
                        className="w-full min-w-0 rounded-field border border-line-2 bg-surface-2 p-2 text-[13px] text-text file:mr-3 file:rounded-btn file:border-0 file:bg-surface-3 file:px-3 file:py-2 file:font-bold file:text-text"
                    />
                    <FieldError id={`${id}-file-error`} message={errors.file} />
                    {progress ? (
                        <progress
                            value={progress.percentage}
                            max={100}
                            className="w-full"
                        >
                            {progress.percentage}%
                        </progress>
                    ) : null}
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.primary}
                    >
                        {processing ? 'Uploading…' : 'Upload and run'}
                    </button>
                </>
            )}
        </Form>
    );
}

function StatusForm({
    feedId,
    action,
}: {
    feedId: number;
    action: 'pause' | 'resume';
}) {
    const id = useId();

    return (
        <Form
            {...FeedStatusController.store.form(feedId)}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2"
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="action" value={action} />
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.secondary}
                    >
                        {action === 'pause'
                            ? 'Pause schedule'
                            : 'Resume schedule'}
                    </button>
                    <FieldError id={`${id}-status`} message={errors.status} />
                </>
            )}
        </Form>
    );
}

/**
 * The actions of one feed. Every control is rendered only when the role and
 * the feed state allow it; otherwise a sentence says why (never a dead button).
 */
export function FeedActions({
    feedId,
    can,
    actions,
    className,
}: FeedAbilities & { feedId: number; className?: string }) {
    if (!can.run) {
        return (
            <p className={cn('text-[13px] text-text-3', className)}>
                Your role can view this feed but not run or change it. Ask an
                owner or manager of your team.
            </p>
        );
    }

    const nothing =
        !actions.runNow && !actions.upload && !actions.pause && !actions.resume;

    return (
        <div className={cn('grid gap-4 sm:grid-cols-2', className)}>
            {actions.runNow ? <RunNowForm feedId={feedId} /> : null}
            {actions.upload ? <UploadForm feedId={feedId} /> : null}
            {actions.pause ? (
                <StatusForm feedId={feedId} action="pause" />
            ) : null}
            {actions.resume ? (
                <StatusForm feedId={feedId} action="resume" />
            ) : null}
            {nothing ? (
                <p className="text-[13px] text-text-3 sm:col-span-2">
                    No action is available right now: a run may be in progress,
                    or the feed is disabled.
                </p>
            ) : null}
        </div>
    );
}

export function CancelRunForm({
    feedId,
    runId,
}: {
    feedId: number;
    runId: number;
}) {
    const id = useId();

    return (
        <Form
            {...FeedRunController.cancel.form([feedId, runId])}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2"
        >
            {({ errors, processing }) => (
                <>
                    <button
                        type="submit"
                        disabled={processing}
                        className={buttonStyles.secondary}
                    >
                        {processing ? 'Cancelling…' : 'Cancel this run'}
                    </button>
                    <FieldError id={`${id}-run`} message={errors.run} />
                </>
            )}
        </Form>
    );
}
