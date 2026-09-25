import { Head, Link } from '@inertiajs/react';
import FeedRunController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedRunController';
import FeedSourceController from '@/actions/App/Http/Controllers/Merchant/Feeds/FeedSourceController';
import { DateTime } from '@/components/admin/admin-table';
import { PageHeader } from '@/components/catalog/page-header';
import { Pagination } from '@/components/catalog/pagination';
import { buttonStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import {
    FeedStatusChip,
    RunStatusChip,
} from '@/components/merchant/feed-badges';
import { formatSchedule } from '@/components/merchant/format';
import { MerchantPage } from '@/components/merchant/page';
import type { FeedIndexProps, FeedListItem } from '@/types/merchant';

function FeedCard({ feed }: { feed: FeedListItem }) {
    const run = feed.latestRun;

    return (
        <li className="flex min-w-0 flex-col gap-3 rounded-card border border-line bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <Link
                        href={FeedSourceController.show.url(feed.id)}
                        className="text-[16px] font-extrabold break-words text-text underline-offset-4 hover:underline"
                    >
                        {feed.name}
                    </Link>
                    <p className="mt-0.5 text-xs break-all text-text-3">
                        {feed.format.label} · {feed.transport.label}
                        {feed.maskedUrl ? ` · ${feed.maskedUrl}` : ''}
                    </p>
                </div>
                <FeedStatusChip status={feed.status} />
            </div>
            {feed.statusReason ? (
                <p className="text-[12.5px] text-danger-2">
                    {feed.statusReason}
                </p>
            ) : null}
            <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-[12.5px]">
                <dt className="text-text-3">Schedule</dt>
                <dd className="text-text-2">
                    {feed.transport.value === 'url'
                        ? formatSchedule(feed.intervalMinutes)
                        : 'On upload'}
                </dd>
                <dt className="text-text-3">Mapping</dt>
                <dd className="text-text-2">
                    {feed.mapping
                        ? `Version ${feed.mapping.version}`
                        : 'Not saved yet'}
                </dd>
                <dt className="text-text-3">Last success</dt>
                <dd className="text-text-2">
                    {feed.lastSuccessAt ? (
                        <DateTime iso={feed.lastSuccessAt} />
                    ) : (
                        'Never'
                    )}
                </dd>
            </dl>
            <div className="flex flex-wrap items-center gap-2 border-t border-line-soft pt-3 text-[12.5px]">
                {run ? (
                    <>
                        <span className="text-text-3">Latest run</span>
                        <Link
                            href={FeedRunController.show.url([feed.id, run.id])}
                            className="num font-bold text-acc-text underline-offset-4 hover:underline"
                        >
                            #{run.id}
                        </Link>
                        <RunStatusChip
                            status={run.status}
                            outcome={run.outcome}
                        />
                        <span className="num text-text-3">
                            {run.rowsRead} rows · {run.rowsInvalid} rejected
                        </span>
                    </>
                ) : (
                    <span className="text-text-3">
                        No run yet. Open the feed to start the first one.
                    </span>
                )}
            </div>
        </li>
    );
}

export default function FeedsIndex({ feeds, can }: FeedIndexProps) {
    return (
        <>
            <Head title="Feeds" />
            <MerchantPage>
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <PageHeader eyebrow="Merchant" title="Feeds">
                        Your product feeds, their schedule and the result of
                        their latest run.
                    </PageHeader>
                    {can.create ? (
                        <Link
                            href={FeedSourceController.create.url()}
                            className={buttonStyles.primary}
                        >
                            Add a feed
                        </Link>
                    ) : null}
                </div>

                <div className="mt-6">
                    {feeds.data.length === 0 ? (
                        <EmptyState title="No feeds yet">
                            {can.create
                                ? 'Add your first feed: a URL Comparo fetches on a schedule, or a file you upload.'
                                : 'An owner or manager of your team can add the first feed.'}
                        </EmptyState>
                    ) : (
                        <ul className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                            {feeds.data.map((feed) => (
                                <FeedCard key={feed.id} feed={feed} />
                            ))}
                        </ul>
                    )}
                    <Pagination meta={feeds.meta} links={feeds.links} />
                </div>
            </MerchantPage>
        </>
    );
}

FeedsIndex.layout = {
    breadcrumbs: [{ title: 'Feeds', href: FeedSourceController.index() }],
};
