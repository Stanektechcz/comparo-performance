import { Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import FeedRunController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedRunController';
import FeedRunErrorExportController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedRunErrorExportController';
import FeedSourceController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedSourceController';
import {
    AdminRow,
    AdminTable,
    bodyCell,
    DateTime,
    Fact,
} from '@/components/admin/admin-table';
import { PageHeader } from '@/components/catalog/page-header';
import { Pagination } from '@/components/catalog/pagination';
import { buttonStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import { Notice } from '@/components/comparo/notice';
import { Section } from '@/components/comparo/section';
import { CancelRunForm } from '@/components/merchant/feed-actions';
import { RunStatusChip, SeverityChip } from '@/components/merchant/feed-badges';
import { formatBytes, formatDuration } from '@/components/merchant/format';
import { MerchantPage, StatTile } from '@/components/merchant/page';
import { cn } from '@/lib/utils';
import type {
    ErrorGroup,
    FeedRunDetail,
    FeedRunShowProps,
} from '@/types/merchant';

const POLL_MS = 5000;

function StageTimeline({ run }: { run: FeedRunDetail }) {
    return (
        <ol className="flex flex-col gap-3 border-l border-line-2 pl-5">
            {run.stages.map((stage) => (
                <li key={stage.key} className="relative">
                    <span
                        aria-hidden="true"
                        className={cn(
                            'absolute top-1.5 -left-[25px] size-2.5 rounded-full border-2 border-surface',
                            stage.at ? 'bg-ok' : 'bg-text-5',
                        )}
                    />
                    <span className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-[13px]">
                        <span
                            className={cn(
                                'font-bold',
                                stage.at ? 'text-text' : 'text-text-4',
                            )}
                        >
                            {stage.label}
                        </span>
                        {stage.at ? (
                            <DateTime iso={stage.at} />
                        ) : (
                            <span className="text-[12px] text-text-4">
                                {run.isActive
                                    ? 'Not reached yet'
                                    : 'Not reached'}
                            </span>
                        )}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function ErrorGroupCard({
    group,
    feedId,
    runId,
}: {
    group: ErrorGroup;
    feedId: number;
    runId: number;
}) {
    return (
        <li className="flex min-w-0 flex-col gap-3 rounded-card border border-line bg-surface p-4">
            <div className="flex flex-wrap items-center gap-2">
                <SeverityChip severity={group.severity} />
                <span className="num text-[12px] font-bold text-text-2">
                    {group.code}
                </span>
                <span className="num text-[12px] text-text-3">
                    {group.count}
                    {group.capped ? '+' : ''}{' '}
                    {group.count === 1 ? 'row' : 'rows'}
                </span>
            </div>
            <p className="text-[13.5px] leading-relaxed text-text">
                {group.message}
            </p>
            {group.capped ? (
                <p className="text-xs text-text-3">
                    Only the first rows with this problem were stored; the run
                    metrics hold the full count.
                </p>
            ) : null}
            {group.samples.length > 0 ? (
                <ul
                    aria-label={`Example rows for ${group.code}`}
                    className="flex flex-col gap-1.5"
                >
                    {group.samples.map((sample, index) => (
                        <li
                            key={`${sample.rowNumber ?? 'run'}-${index}`}
                            className="rounded-field bg-surface-2 px-3 py-2 text-[12.5px] text-text-2"
                        >
                            <span className="num font-bold text-text">
                                {sample.rowNumber !== null
                                    ? `Line ${sample.rowNumber}`
                                    : 'Whole feed'}
                            </span>
                            {sample.sku ? (
                                <span className="num break-all">
                                    {' '}
                                    · SKU {sample.sku}
                                </span>
                            ) : null}
                            {sample.field ? ` · ${sample.field}` : ''}
                            <span className="mt-0.5 block text-text-3">
                                {sample.message}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : null}
            <Link
                href={FeedRunController.show.url([feedId, runId], {
                    query: { code: group.code },
                })}
                preserveScroll
                className={buttonStyles.tertiary}
            >
                Show all rows with {group.code}
            </Link>
        </li>
    );
}

export default function FeedRunShow({
    feed,
    run,
    errorGroups,
    errorRows,
    errorFilter,
    can,
}: FeedRunShowProps) {
    const { start, stop } = usePoll(
        POLL_MS,
        { only: ['run', 'errorGroups', 'errorRows', 'feed'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (run.isActive) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [run.isActive, start, stop]);

    return (
        <>
            <Head title={`Run #${run.id} · ${feed.name}`} />
            <MerchantPage
                back={{
                    href: FeedSourceController.show(feed.id),
                    label: 'Back to the feed',
                }}
            >
                <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
                    <PageHeader
                        eyebrow={feed.name}
                        title={<span className="num">Run #{run.id}</span>}
                    >
                        {run.trigger.label} · <DateTime iso={run.createdAt} />
                    </PageHeader>
                    <div className="flex flex-col items-start gap-3 pt-2">
                        <RunStatusChip
                            status={run.status}
                            outcome={run.outcome}
                        />
                        {run.isCancellable && can.cancel ? (
                            <CancelRunForm feedId={feed.id} runId={run.id} />
                        ) : null}
                    </div>
                </div>

                <div className="mt-4 flex flex-col gap-3" aria-live="polite">
                    {run.isActive ? (
                        <Notice tone="info" title="This run is in progress">
                            The page refreshes every few seconds until the run
                            finishes.
                        </Notice>
                    ) : null}
                    {run.failure ? (
                        <Notice tone="danger" title="The run failed">
                            {run.failure.message}
                        </Notice>
                    ) : null}
                    {run.outcome?.value === 'unchanged' ? (
                        <Notice tone="neutral" title="Nothing changed">
                            The feed file was identical to the previous run, so
                            it was not processed again.
                        </Notice>
                    ) : null}
                </div>

                <Section id="metrics" title="Results">
                    <div className="flex flex-col gap-5">
                        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <StatTile
                                label="Duration"
                                value={
                                    <Fact
                                        value={formatDuration(run.durationMs)}
                                    />
                                }
                            />
                            <StatTile
                                label="File size"
                                value={
                                    <Fact
                                        value={formatBytes(run.payloadBytes)}
                                    />
                                }
                            />
                            <StatTile label="Rows read" value={run.rowsRead} />
                            <StatTile
                                label="Rejected"
                                value={run.rowsInvalid}
                            />
                        </dl>
                        {run.metrics.map((group) => (
                            <div key={group.title}>
                                <h3 className="mb-2 text-[13px] font-extrabold text-text-2">
                                    {group.title}
                                </h3>
                                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                    {group.items.map((item) => (
                                        <StatTile
                                            key={item.key}
                                            label={item.label}
                                            value={item.value}
                                        />
                                    ))}
                                </dl>
                            </div>
                        ))}
                    </div>
                </Section>

                <Section id="stages" title="Stages">
                    <StageTimeline run={run} />
                </Section>

                <Section
                    id="errors"
                    title="Problems found"
                    description="Grouped by problem, with what to fix in your feed."
                    actions={
                        errorRows.meta.total > 0 ? (
                            <a
                                href={FeedRunErrorExportController.show.url([
                                    feed.id,
                                    run.id,
                                ])}
                                className={buttonStyles.secondary}
                                download
                            >
                                Download all as CSV
                            </a>
                        ) : null
                    }
                >
                    {errorGroups.length === 0 ? (
                        <EmptyState title="No problems recorded">
                            {run.isActive
                                ? 'Problems appear here as rows are checked.'
                                : 'Every row of this run passed validation.'}
                        </EmptyState>
                    ) : (
                        <ul className="grid gap-4 lg:grid-cols-2">
                            {errorGroups.map((group) => (
                                <ErrorGroupCard
                                    key={`${group.code}-${group.severity.value}`}
                                    group={group}
                                    feedId={feed.id}
                                    runId={run.id}
                                />
                            ))}
                        </ul>
                    )}
                </Section>

                {errorRows.meta.total > 0 ? (
                    <Section
                        id="error-rows"
                        title="All problem rows"
                        description={
                            errorFilter
                                ? `Filtered to ${errorFilter}.`
                                : 'Every stored problem, in file order.'
                        }
                        actions={
                            errorFilter ? (
                                <Link
                                    href={FeedRunController.show.url([
                                        feed.id,
                                        run.id,
                                    ])}
                                    preserveScroll
                                    className={buttonStyles.tertiary}
                                >
                                    Show all problems
                                </Link>
                            ) : null
                        }
                    >
                        <AdminTable
                            caption={`Problem rows of run ${run.id}`}
                            columns={[
                                'Line',
                                'SKU',
                                'Problem',
                                'Field',
                                'What to do',
                            ]}
                            minWidth="min-w-[760px]"
                        >
                            {errorRows.data.map((row) => (
                                <AdminRow key={row.id}>
                                    <td className={`${bodyCell} num`}>
                                        <Fact
                                            value={
                                                row.rowNumber !== null
                                                    ? String(row.rowNumber)
                                                    : null
                                            }
                                        />
                                    </td>
                                    <td className={`${bodyCell} num break-all`}>
                                        <Fact value={row.sku} />
                                    </td>
                                    <td className={bodyCell}>
                                        <SeverityChip severity={row.severity} />
                                        <span className="mt-1 block num text-[11.5px] text-text-3">
                                            {row.code}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>
                                        <Fact value={row.field} />
                                    </td>
                                    <td className={cn(bodyCell, 'max-w-md')}>
                                        {row.message}
                                    </td>
                                </AdminRow>
                            ))}
                        </AdminTable>
                        <Pagination
                            meta={errorRows.meta}
                            links={errorRows.links}
                        />
                    </Section>
                ) : null}
            </MerchantPage>
        </>
    );
}

FeedRunShow.layout = (props: FeedRunShowProps) => ({
    breadcrumbs: [
        { title: 'Feeds', href: FeedSourceController.index() },
        {
            title: props.feed.name,
            href: FeedSourceController.show(props.feed.id),
        },
        {
            title: `Run #${props.run.id}`,
            href: FeedRunController.show([props.feed.id, props.run.id]),
        },
    ],
});
