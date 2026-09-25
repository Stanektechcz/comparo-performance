import { Head, Link } from '@inertiajs/react';
import MatchingListingController from '@/actions/App/Http/Controllers/Admin/Catalogue/MatchingListingController';
import MatchingQueueController from '@/actions/App/Http/Controllers/Admin/Catalogue/MatchingQueueController';
import { DateTime, Fact } from '@/components/admin/admin-table';
import {
    CandidateComparison,
    EvidenceParts,
} from '@/components/admin/candidate-comparison';
import { DecisionPanel } from '@/components/admin/listing-actions';
import {
    DecisionKindChip,
    MatchScore,
    StatusChip,
} from '@/components/admin/matching-badges';
import { DecidedBy, ProductLine } from '@/components/admin/queue-tables';
import { PageHeader } from '@/components/catalog/page-header';
import { buttonStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import { Notice } from '@/components/comparo/notice';
import { Section } from '@/components/comparo/section';
import type {
    DecisionEntry,
    ListingEvidence,
    MatchingShowProps,
} from '@/types/admin';

function SourceFacts({ listing }: { listing: ListingEvidence }) {
    const facts: { label: string; value: string | null; mono?: boolean }[] = [
        { label: 'Merchant', value: listing.merchant.name },
        { label: 'Feed', value: listing.feedSource?.name ?? null },
        {
            label: 'Market',
            value: listing.market
                ? `${listing.market.name} (${listing.market.code})`
                : null,
        },
        { label: 'SKU', value: listing.sku, mono: true },
        { label: 'External id', value: listing.externalId, mono: true },
        { label: 'Title', value: listing.title },
        { label: 'Brand', value: listing.brandRaw },
        { label: 'Pack', value: listing.packRaw },
        { label: 'Variant', value: listing.variantRaw },
        { label: 'Category', value: listing.categoryRaw },
        { label: 'EAN', value: listing.ean, mono: true },
        { label: 'Listing status', value: listing.listingStatus },
        {
            label: 'Missed feed runs',
            value: String(listing.missingRunCount),
            mono: true,
        },
    ];

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <dl className="grid grid-cols-[minmax(7rem,auto)_minmax(0,1fr)] gap-x-4 gap-y-2 rounded-card border border-line bg-surface p-4 text-[13px]">
                {facts.map((fact) => (
                    <div key={fact.label} className="contents">
                        <dt className="font-bold text-text-3">{fact.label}</dt>
                        <dd
                            className={
                                fact.mono
                                    ? 'num break-all text-text'
                                    : 'break-words text-text'
                            }
                        >
                            <Fact value={fact.value} />
                        </dd>
                    </div>
                ))}
                <dt className="font-bold text-text-3">First seen</dt>
                <dd>
                    <DateTime iso={listing.firstSeenAt} />
                </dd>
                <dt className="font-bold text-text-3">Last seen</dt>
                <dd>
                    <DateTime iso={listing.lastSeenAt} />
                </dd>
                <dt className="font-bold text-text-3">Shop page</dt>
                <dd className="break-all">
                    {listing.url ? (
                        <a
                            href={listing.url}
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            className="font-bold text-acc-text underline-offset-4 hover:underline"
                        >
                            Open the merchant page
                            <span className="sr-only">
                                {' '}
                                (opens in a new tab)
                            </span>
                        </a>
                    ) : (
                        <Fact value={null} />
                    )}
                </dd>
            </dl>
            <div className="min-w-0 rounded-card border border-line bg-surface p-4">
                <h3 className="text-[14px] font-extrabold text-text">
                    Raw feed fields
                </h3>
                {listing.rawPayload ? (
                    <>
                        <dl className="mt-3 grid max-h-96 grid-cols-[minmax(6rem,auto)_minmax(0,1fr)] gap-x-4 gap-y-1.5 overflow-y-auto text-[12.5px]">
                            {listing.rawPayload.fields.map((field) => (
                                <div key={field.key} className="contents">
                                    <dt className="num font-bold break-all text-text-3">
                                        {field.key}
                                    </dt>
                                    <dd className="num break-all text-text-2">
                                        {field.value === '' ? (
                                            <Fact value={null} />
                                        ) : (
                                            field.value
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        {listing.rawPayload.truncated ? (
                            <p className="mt-3 text-xs text-text-3">
                                Long or numerous fields are shortened here.
                            </p>
                        ) : null}
                    </>
                ) : (
                    <p className="mt-2 text-[13px] text-text-3">
                        This listing has no stored raw feed row.
                    </p>
                )}
            </div>
        </div>
    );
}

function DecisionTimeline({ entries }: { entries: DecisionEntry[] }) {
    return (
        <ol className="relative flex flex-col gap-4 border-l border-line-2 pl-5">
            {entries.map((entry) => (
                <li key={entry.id} className="relative">
                    <span
                        aria-hidden="true"
                        className="absolute top-1.5 -left-[25px] size-2.5 rounded-full border-2 border-surface bg-text-4"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <DecisionKindChip kind={entry.kind} />
                        <DateTime iso={entry.decidedAt} />
                        <span className="text-[12.5px] text-text-3">
                            by <DecidedBy entry={entry} />
                        </span>
                    </div>
                    <div className="mt-1.5 flex flex-col gap-1 text-[13px] text-text-2">
                        {entry.product ? (
                            <ProductLine product={entry.product} />
                        ) : null}
                        {entry.previousProduct ? (
                            <span className="text-[12px] text-text-3">
                                Previously: {entry.previousProduct.name} · #
                                {entry.previousProduct.id}
                            </span>
                        ) : null}
                        <span className="num text-[11.5px] text-text-3">
                            Decision #{entry.id}
                            {entry.score !== null
                                ? ` · score ${entry.score}`
                                : ''}
                            {entry.reason ? ` · ${entry.reason}` : ''}
                            {entry.supersedesId
                                ? ` · supersedes #${entry.supersedesId}`
                                : ''}
                        </span>
                        {entry.note ? (
                            <p className="rounded-field bg-surface-2 px-3 py-2 text-[12.5px] text-text">
                                {entry.note}
                            </p>
                        ) : null}
                    </div>
                </li>
            ))}
        </ol>
    );
}

export default function MatchingShow({
    listing,
    currentDecision,
    candidates,
    previewUnavailable,
    history,
    historyTotal,
    can,
    actions,
}: MatchingShowProps) {
    const title = listing.title ?? `SKU ${listing.sku}`;

    return (
        <>
            <Head title={`Match review · ${title}`} />
            <div className="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6">
                <Link
                    href={MatchingQueueController.index.url()}
                    className={buttonStyles.tertiary}
                >
                    <span aria-hidden="true">←</span> Back to the matching queue
                </Link>

                <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
                    <PageHeader
                        eyebrow={`${listing.merchant.name} · SKU ${listing.sku}`}
                        title={<span className="break-words">{title}</span>}
                    />
                    <div className="flex flex-col items-start gap-2 pt-2">
                        <StatusChip status={listing.status} />
                        <MatchScore
                            score={listing.score}
                            level={listing.level}
                            size="lg"
                        />
                    </div>
                </div>

                <div className="mt-4 flex flex-col gap-3">
                    {listing.market === null ? (
                        <Notice tone="warn" title="No known market">
                            This listing’s feed has no country, so compliance
                            cannot be cleared: any link will be held and nothing
                            is published.
                        </Notice>
                    ) : null}
                    {listing.status.value === 'compliance_hold' ? (
                        <Notice tone="danger" title="Held for compliance">
                            The linked product is blocked or not yet reviewed in{' '}
                            {listing.market?.name ?? 'this market'}. The offer
                            stays unpublished until compliance clears it or the
                            listing is relinked.
                        </Notice>
                    ) : null}
                </div>

                <Section
                    id="decision"
                    title="Decision"
                    description="Every action is recorded as a new decision and audited with your account."
                >
                    {currentDecision ? (
                        <div className="mb-4 grid gap-4 rounded-card border border-line bg-surface p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            <div className="flex flex-col gap-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-[12px] font-extrabold text-text-2">
                                        Current decision
                                    </span>
                                    <DecisionKindChip
                                        kind={currentDecision.kind}
                                    />
                                </div>
                                <ProductLine
                                    product={currentDecision.product}
                                />
                                <span className="text-[12.5px] text-text-3">
                                    <DateTime iso={currentDecision.decidedAt} />{' '}
                                    · by <DecidedBy entry={currentDecision} />
                                </span>
                            </div>
                            <EvidenceParts
                                parts={currentDecision.parts}
                                label="Evidence stored with the current decision"
                            />
                        </div>
                    ) : (
                        <p className="mb-4 text-[13px] text-text-3">
                            No decision has been recorded for this listing yet.
                        </p>
                    )}
                    <DecisionPanel
                        listingId={listing.id}
                        linkedProductId={listing.linkedProductId}
                        actions={actions}
                        can={can}
                        currentDecision={currentDecision}
                    />
                </Section>

                <Section
                    id="candidates"
                    title="Compare with candidates"
                    description="Live scores under the active matching policy. Nothing is written until you decide."
                >
                    {previewUnavailable ? (
                        <Notice tone="warn" title="Candidates unavailable">
                            {previewUnavailable}
                        </Notice>
                    ) : candidates.length === 0 ? (
                        <EmptyState title="No candidate products found">
                            No active product shares this listing’s EAN or
                            brand. Search the catalogue with “Choose another
                            product”, or leave it for a new-product proposal.
                        </EmptyState>
                    ) : (
                        <CandidateComparison
                            listing={listing}
                            candidates={candidates}
                            actions={actions}
                            canRematch={can.rematch}
                        />
                    )}
                </Section>

                <Section
                    id="facts"
                    title="Source facts"
                    description="What the merchant feed sent for this listing."
                >
                    <SourceFacts listing={listing} />
                </Section>

                <Section
                    id="history"
                    title="Decision history"
                    description={
                        historyTotal > history.length
                            ? `Showing the latest ${history.length} of ${historyTotal} decisions.`
                            : 'Decisions are append-only; corrections are new entries.'
                    }
                    className="pb-10"
                >
                    {history.length === 0 ? (
                        <EmptyState title="No decisions recorded yet" />
                    ) : (
                        <DecisionTimeline entries={history} />
                    )}
                </Section>
            </div>
        </>
    );
}

MatchingShow.layout = (props: MatchingShowProps) => ({
    breadcrumbs: [
        {
            title: 'Catalogue matching',
            href: MatchingQueueController.index(),
        },
        {
            title: props.listing.title ?? `SKU ${props.listing.sku}`,
            href: MatchingListingController.show(props.listing.id),
        },
    ],
});
