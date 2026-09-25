import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import FeedMappingController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedMappingController';
import FeedRunController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedRunController';
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
import { FeedActions } from '@/components/merchant/feed-actions';
import {
    FeedStatusChip,
    RunStatusChip,
} from '@/components/merchant/feed-badges';
import { formatDuration, formatSchedule } from '@/components/merchant/format';
import { MerchantPage } from '@/components/merchant/page';
import type { FeedShowProps, FeedSummary } from '@/types/merchant';

function FeedFacts({ feed }: { feed: FeedSummary }) {
    const facts: { label: string; value: ReactNode }[] = [
        { label: 'Format', value: feed.format.label },
        { label: 'Delivery', value: feed.transport.label },
        {
            label: 'Feed URL',
            value: feed.maskedUrl ? (
                <span className="num break-all">{feed.maskedUrl}</span>
            ) : null,
        },
        {
            label: 'Schedule',
            value:
                feed.transport.value === 'url'
                    ? formatSchedule(feed.intervalMinutes)
                    : 'Runs when you upload a file',
        },
        {
            label: 'Market',
            value: feed.market
                ? `${feed.market.name} (${feed.market.code})`
                : 'Not set: offers cannot be cleared for compliance',
        },
        { label: 'Default currency', value: feed.currency },
        {
            label: 'Credentials',
            value: feed.hasCredentials ? 'Set (never shown)' : 'None',
        },
        {
            label: 'Field mapping',
            value: feed.mapping
                ? `Version ${feed.mapping.version} · ${feed.mapping.mappedFields} fields`
                : 'Not saved yet: the next run uses a suggested mapping',
        },
        { label: 'Last run', value: <DateTime iso={feed.lastRunAt} /> },
        { label: 'Last success', value: <DateTime iso={feed.lastSuccessAt} /> },
        {
            label: 'Next scheduled run',
            value: feed.nextRunAt ? <DateTime iso={feed.nextRunAt} /> : null,
        },
        {
            label: 'Failures in a row',
            value: <span className="num">{feed.consecutiveFailures}</span>,
        },
    ];

    return (
        <dl className="grid grid-cols-[minmax(7rem,auto)_minmax(0,1fr)] gap-x-4 gap-y-2 rounded-card border border-line bg-surface p-4 text-[13px]">
            {facts.map((fact) => (
                <div key={fact.label} className="contents">
                    <dt className="font-bold text-text-3">{fact.label}</dt>
                    <dd className="min-w-0 break-words text-text">
                        {fact.value ?? <Fact value={null} />}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function FeedShow({ feed, runs, can, actions }: FeedShowProps) {
    return (
        <>
            <Head title={feed.name} />
            <MerchantPage
                back={{
                    href: FeedSourceController.index(),
                    label: 'Back to feeds',
                }}
            >
                <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
                    <PageHeader
                        eyebrow="Feed"
                        title={<span className="break-words">{feed.name}</span>}
                    />
                    <div className="flex flex-wrap items-center gap-2 pt-2">
                        <FeedStatusChip status={feed.status} />
                        <Link
                            href={FeedMappingController.edit.url(feed.id)}
                            className={buttonStyles.secondary}
                        >
                            Field mapping
                        </Link>
                        <Link
                            href={FeedSourceController.edit.url(feed.id)}
                            className={buttonStyles.secondary}
                        >
                            Settings
                        </Link>
                    </div>
                </div>

                {feed.statusReason ? (
                    <Notice
                        tone={feed.status.value === 'error' ? 'danger' : 'warn'}
                        title={
                            feed.status.value === 'error'
                                ? 'This feed needs attention'
                                : 'Status note'
                        }
                        className="mt-4"
                    >
                        {feed.statusReason}
                    </Notice>
                ) : null}

                <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <FeedFacts feed={feed} />
                    <section
                        aria-labelledby="feed-actions-heading"
                        className="flex flex-col gap-3 rounded-card border border-line bg-surface p-4"
                    >
                        <h2
                            id="feed-actions-heading"
                            className="text-[16px] font-extrabold text-text"
                        >
                            Run the feed
                        </h2>
                        <FeedActions
                            feedId={feed.id}
                            can={can}
                            actions={actions}
                        />
                    </section>
                </div>

                <Section
                    id="runs"
                    title="Run history"
                    description="Newest first. Open a run for its metrics and row errors."
                >
                    {runs.data.length === 0 ? (
                        <EmptyState title="No runs yet">
                            {feed.transport.value === 'upload'
                                ? 'Upload a feed file to start the first run.'
                                : 'Start the first run with “Run now”.'}
                        </EmptyState>
                    ) : (
                        <AdminTable
                            caption={`Runs of ${feed.name}`}
                            columns={[
                                'Run',
                                'Result',
                                'Started',
                                'Duration',
                                'Rows',
                                'Rejected',
                                'Warnings',
                            ]}
                            minWidth="min-w-[720px]"
                        >
                            {runs.data.map((run) => (
                                <AdminRow key={run.id}>
                                    <td className={bodyCell}>
                                        <Link
                                            href={FeedRunController.show.url([
                                                feed.id,
                                                run.id,
                                            ])}
                                            className="num font-bold text-acc-text underline-offset-4 hover:underline"
                                        >
                                            #{run.id}
                                        </Link>
                                        <span className="block text-[11.5px] text-text-3">
                                            {run.trigger.label}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>
                                        <RunStatusChip
                                            status={run.status}
                                            outcome={run.outcome}
                                        />
                                        {run.failure ? (
                                            <span className="mt-1 block max-w-64 text-[12px] text-danger-2">
                                                {run.failure.message}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className={bodyCell}>
                                        <DateTime iso={run.createdAt} />
                                    </td>
                                    <td className={bodyCell}>
                                        <Fact
                                            value={formatDuration(
                                                run.durationMs,
                                            )}
                                        />
                                    </td>
                                    <td className={`${bodyCell} num`}>
                                        {run.rowsRead}
                                    </td>
                                    <td className={`${bodyCell} num`}>
                                        {run.rowsInvalid}
                                    </td>
                                    <td className={`${bodyCell} num`}>
                                        {run.warnings}
                                    </td>
                                </AdminRow>
                            ))}
                        </AdminTable>
                    )}
                    <Pagination meta={runs.meta} links={runs.links} />
                </Section>
            </MerchantPage>
        </>
    );
}

FeedShow.layout = (props: FeedShowProps) => ({
    breadcrumbs: [
        { title: 'Feeds', href: FeedSourceController.index() },
        {
            title: props.feed.name,
            href: FeedSourceController.show(props.feed.id),
        },
    ],
});
