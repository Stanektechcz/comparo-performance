import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import MatchingQueueController from '@/actions/App/Http/Controllers/Admin/Catalogue/MatchingQueueController';
import { fieldLabel } from '@/components/admin/action-dialog';
import {
    CandidatesTable,
    ConflictsTable,
    HistoryTable,
    ListingsTable,
} from '@/components/admin/queue-tables';
import { PageHeader } from '@/components/catalog/page-header';
import { Pagination } from '@/components/catalog/pagination';
import { buttonStyles, selectStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import { cn } from '@/lib/utils';
import type {
    MatchingFilters,
    MatchingIndexProps,
    MatchingTab,
} from '@/types/admin';
import type { Paginated } from '@/types/catalog';

function tabUrl(tab: MatchingTab): string {
    return MatchingQueueController.index.url({ query: { tab } });
}

function hasFilters(filters: MatchingFilters): boolean {
    return Object.values(filters).some((value) => value !== null);
}

function TabNav({
    tabs,
    active,
}: Pick<MatchingIndexProps, 'tabs'> & {
    active: MatchingTab;
}) {
    return (
        <nav aria-label="Matching queues" className="mt-6">
            <ul className="flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const isActive = tab.key === active;

                    return (
                        <li key={tab.key}>
                            <Link
                                href={tabUrl(tab.key)}
                                aria-current={isActive ? 'page' : undefined}
                                className={cn(
                                    'inline-flex min-h-10 items-center rounded-pill border px-3.5 text-[12.5px] font-bold transition-colors',
                                    isActive
                                        ? 'border-acc bg-acc text-acc-ink'
                                        : 'border-line-2 text-text-2 hover:bg-surface-3',
                                )}
                            >
                                {tab.label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

const inputStyles = cn(selectStyles, 'w-full');

function ListingFilters({
    filters,
    filterOptions,
}: Pick<MatchingIndexProps, 'filters' | 'filterOptions'>) {
    return (
        <Form
            {...MatchingQueueController.index.form()}
            options={{ preserveScroll: true }}
            className="mt-6 grid gap-3 rounded-card border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_7rem_7rem_auto]"
            aria-label="Filter listings"
        >
            {({ errors, processing }) => (
                <>
                    <input type="hidden" name="tab" value="listings" />
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <label htmlFor="filter-merchant" className={fieldLabel}>
                            Merchant
                        </label>
                        <select
                            id="filter-merchant"
                            name="merchant"
                            defaultValue={filters.merchant ?? ''}
                            className={inputStyles}
                        >
                            <option value="">All merchants</option>
                            {filterOptions.merchants.map((merchant) => (
                                <option key={merchant.id} value={merchant.id}>
                                    {merchant.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <label htmlFor="filter-status" className={fieldLabel}>
                            Status
                        </label>
                        <select
                            id="filter-status"
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className={inputStyles}
                        >
                            <option value="">Needs review (default)</option>
                            {filterOptions.statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <label htmlFor="filter-min" className={fieldLabel}>
                            Min score
                        </label>
                        <input
                            id="filter-min"
                            name="minScore"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={100}
                            defaultValue={filters.minScore ?? ''}
                            className={cn(inputStyles, 'num')}
                        />
                    </div>
                    <div className="flex min-w-0 flex-col gap-1.5">
                        <label htmlFor="filter-max" className={fieldLabel}>
                            Max score
                        </label>
                        <input
                            id="filter-max"
                            name="maxScore"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={100}
                            aria-invalid={errors.maxScore ? true : undefined}
                            aria-describedby={
                                errors.maxScore ? 'filter-max-error' : undefined
                            }
                            defaultValue={filters.maxScore ?? ''}
                            className={cn(inputStyles, 'num')}
                        />
                    </div>
                    <div className="flex flex-wrap items-end gap-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className={buttonStyles.primary}
                        >
                            Apply
                        </button>
                        {hasFilters(filters) ? (
                            <Link
                                href={tabUrl('listings')}
                                className={buttonStyles.secondary}
                            >
                                Clear
                            </Link>
                        ) : null}
                    </div>
                    {errors.maxScore || errors.minScore || errors.merchant ? (
                        <p
                            id="filter-max-error"
                            className="text-[12.5px] font-semibold text-danger-2 sm:col-span-2 lg:col-span-5"
                        >
                            {errors.maxScore ??
                                errors.minScore ??
                                errors.merchant}
                        </p>
                    ) : null}
                </>
            )}
        </Form>
    );
}

function QueueSection<T>({
    id,
    title,
    page,
    empty,
    children,
}: {
    id: string;
    title: string;
    page: Paginated<T> | null;
    empty: { title: string; body: string };
    children: (rows: T[]) => ReactNode;
}) {
    if (!page) {
        return null;
    }

    return (
        <section aria-labelledby={`${id}-heading`} className="mt-6">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2
                    id={`${id}-heading`}
                    className="text-[18px] font-black tracking-[-0.01em] text-text"
                >
                    {title}
                </h2>
                <p className="num text-[13px] text-text-3">
                    {page.meta.total} {page.meta.total === 1 ? 'item' : 'items'}
                </p>
            </div>
            {page.data.length === 0 ? (
                <EmptyState title={empty.title}>{empty.body}</EmptyState>
            ) : (
                <>
                    {children(page.data)}
                    <Pagination meta={page.meta} links={page.links} />
                </>
            )}
        </section>
    );
}

export default function MatchingIndex({
    tab,
    tabs,
    filters,
    filterOptions,
    listings,
    conflicts,
    candidates,
    history,
}: MatchingIndexProps) {
    const activeLabel = tabs.find((item) => item.key === tab)?.label ?? '';
    const filtered = hasFilters(filters);

    return (
        <>
            <Head title={`Catalogue matching · ${activeLabel}`} />
            <div className="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6">
                <PageHeader
                    eyebrow="Staff · Catalogue"
                    title="Catalogue matching"
                >
                    Review how merchant listings map to canonical products.
                    Every decision is recorded and audited; nothing is published
                    for a product that is not cleared for the listing’s market.
                </PageHeader>

                <TabNav tabs={tabs} active={tab} />

                {tab === 'listings' ? (
                    <ListingFilters
                        filters={filters}
                        filterOptions={filterOptions}
                    />
                ) : null}

                <QueueSection
                    id="listings"
                    title="Listings to review"
                    page={listings}
                    empty={
                        filtered
                            ? {
                                  title: 'No listings match these filters',
                                  body: 'Clear the filters to see the whole review queue.',
                              }
                            : {
                                  title: 'No listings are waiting for review',
                                  body: 'Suggested, unmatched and compliance-held listings appear here after each feed run.',
                              }
                    }
                >
                    {(rows) => <ListingsTable rows={rows} />}
                </QueueSection>

                <QueueSection
                    id="conflicts"
                    title="Open conflicts"
                    page={conflicts}
                    empty={{
                        title: 'No open conflicts',
                        body: 'Field disagreements and compliance holds appear here when feeds report them.',
                    }}
                >
                    {(rows) => <ConflictsTable rows={rows} />}
                </QueueSection>

                <QueueSection
                    id="candidates"
                    title="New-product proposals"
                    page={candidates}
                    empty={{
                        title: 'No open new-product proposals',
                        body: 'Merchants propose products here when a listing has no catalogue match.',
                    }}
                >
                    {(rows) => <CandidatesTable rows={rows} />}
                </QueueSection>

                <QueueSection
                    id="history"
                    title="Decision history"
                    page={history}
                    empty={{
                        title: 'No matching decisions yet',
                        body: 'Automatic and manual decisions are listed here once feeds have been matched.',
                    }}
                >
                    {(rows) => <HistoryTable rows={rows} />}
                </QueueSection>
            </div>
        </>
    );
}

MatchingIndex.layout = {
    breadcrumbs: [
        {
            title: 'Catalogue matching',
            href: MatchingQueueController.index(),
        },
    ],
};
