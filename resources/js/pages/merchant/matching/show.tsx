import { Head } from '@inertiajs/react';
import MatchingListingController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingListingController';
import MatchingOverviewController from '@/actions/App/Http/Controllers/Merchant/Matching/MatchingOverviewController';
import { DateTime, Fact } from '@/components/admin/admin-table';
import { EvidenceParts } from '@/components/admin/candidate-comparison';
import {
    DecisionKindChip,
    MatchScore,
    StatusChip,
} from '@/components/admin/matching-badges';
import { ProductLine } from '@/components/admin/queue-tables';
import { PageHeader } from '@/components/catalog/page-header';
import { EmptyState } from '@/components/comparo/empty-state';
import { Notice } from '@/components/comparo/notice';
import { Section } from '@/components/comparo/section';
import {
    CandidateChoice,
    MerchantDecisionPanel,
} from '@/components/merchant/listing-decisions';
import { MerchantPage } from '@/components/merchant/page';
import { cn } from '@/lib/utils';
import type {
    MatchingShowProps,
    MerchantCandidate,
    MerchantDecision,
    MerchantListingFacts,
} from '@/types/merchant';

const bucketLabels: Record<MerchantCandidate['bucket'], string> = {
    auto: 'Strong match',
    confirm: 'Needs your confirmation',
    unmatched: 'Below the match threshold',
};

const cell = 'px-3 py-2 align-top text-[12.5px]';
const headCell =
    'px-3 py-2 text-[10px] font-extrabold tracking-[0.11em] text-text-4 uppercase';

function CandidateCard({
    listing,
    candidate,
    rank,
    canChoose,
}: {
    listing: MerchantListingFacts;
    candidate: MerchantCandidate;
    rank: number;
    canChoose: boolean;
}) {
    const product = candidate.product;
    const productTitle = `${product.brand ? `${product.brand} ` : ''}${product.name}`;
    const rows = [
        { field: 'Brand', listing: listing.brandRaw, product: product.brand },
        {
            field: 'Title / name',
            listing: listing.title,
            product: product.name,
        },
        { field: 'Pack', listing: listing.packRaw, product: product.pack },
        { field: 'EAN', listing: listing.ean, product: product.ean },
    ];

    return (
        <li className="flex min-w-0 flex-col gap-4 rounded-card border border-line bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="eyebrow">Candidate {rank}</p>
                    <h3 className="mt-1 text-[16px] leading-tight font-extrabold break-words text-text">
                        {productTitle}
                    </h3>
                    <p className="mt-0.5 text-[11.5px] text-text-3">
                        {bucketLabels[candidate.bucket]}
                    </p>
                </div>
                <MatchScore
                    score={candidate.score}
                    level={candidate.level}
                    size="lg"
                />
            </div>
            <div
                role="region"
                aria-label={`Your listing compared with ${productTitle}`}
                tabIndex={0}
                className="max-w-full overflow-x-auto"
            >
                <table className="w-full border-collapse text-left">
                    <caption className="sr-only">
                        Your listing compared with {productTitle}
                    </caption>
                    <thead>
                        <tr className="border-b border-line">
                            <th scope="col" className={headCell}>
                                Field
                            </th>
                            <th scope="col" className={headCell}>
                                Your listing
                            </th>
                            <th scope="col" className={headCell}>
                                Catalogue product
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.field}
                                className="border-b border-line-soft last:border-b-0"
                            >
                                <th
                                    scope="row"
                                    className={cn(
                                        cell,
                                        'font-bold text-text-3',
                                    )}
                                >
                                    {row.field}
                                </th>
                                <td
                                    className={cn(
                                        cell,
                                        'break-words text-text-2',
                                    )}
                                >
                                    <Fact value={row.listing} />
                                </td>
                                <td
                                    className={cn(
                                        cell,
                                        'break-words text-text',
                                    )}
                                >
                                    <Fact value={row.product} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div>
                <p className="mb-1.5 text-[12px] font-extrabold text-text-2">
                    Points per signal
                </p>
                <EvidenceParts
                    parts={candidate.parts}
                    label={`Score evidence for ${productTitle}`}
                />
            </div>
            <CandidateChoice
                listingId={listing.id}
                product={product}
                linkedProductId={listing.linkedProductId}
                canChoose={canChoose}
            />
        </li>
    );
}

function ListingFacts({ listing }: { listing: MerchantListingFacts }) {
    const facts: { label: string; value: string | null; mono?: boolean }[] = [
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
    ];

    return (
        <dl className="grid grid-cols-[minmax(6rem,auto)_minmax(0,1fr)] gap-x-4 gap-y-2 rounded-card border border-line bg-surface p-4 text-[13px]">
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
                        Open your product page
                        <span className="sr-only"> (opens in a new tab)</span>
                    </a>
                ) : (
                    <Fact value={null} />
                )}
            </dd>
        </dl>
    );
}

function Timeline({ entries }: { entries: MerchantDecision[] }) {
    return (
        <ol className="flex flex-col gap-4 border-l border-line-2 pl-5">
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
                            by {entry.decidedBy}
                        </span>
                    </div>
                    <div className="mt-1.5 flex flex-col gap-1 text-[13px] text-text-2">
                        {entry.product ? (
                            <ProductLine product={entry.product} />
                        ) : null}
                        {entry.previousProduct ? (
                            <span className="text-[12px] text-text-3">
                                Previously: {entry.previousProduct.name}
                            </span>
                        ) : null}
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

export default function MatchingListingShow({
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
            <Head title={`Match · ${title}`} />
            <MerchantPage
                back={{
                    href: MatchingOverviewController.index(),
                    label: 'Back to matching',
                }}
            >
                <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
                    <PageHeader
                        eyebrow={`SKU ${listing.sku}`}
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
                        <Notice tone="warn" title="No market set on the feed">
                            Without a market this listing cannot be cleared for
                            compliance: a link is held and the offer is not
                            published. Set the market in the feed settings.
                        </Notice>
                    ) : null}
                    {listing.status.value === 'compliance_hold' ? (
                        <Notice tone="danger" title="Held for compliance">
                            The linked product is not cleared for{' '}
                            {listing.market?.name ?? 'this market'}, so the
                            offer is not published.
                        </Notice>
                    ) : null}
                </div>

                <Section
                    id="decision"
                    title="Decision"
                    description="Each action is saved as a new decision under your name and recorded in the audit log."
                >
                    {currentDecision ? (
                        <div className="mb-4 grid gap-4 rounded-card border border-line bg-surface p-4 md:grid-cols-2">
                            <div className="flex min-w-0 flex-col gap-2">
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
                                    · by {currentDecision.decidedBy}
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
                    <MerchantDecisionPanel
                        listing={listing}
                        currentDecision={currentDecision}
                        can={can}
                        actions={actions}
                    />
                </Section>

                <Section
                    id="candidates"
                    title="Compare with catalogue products"
                    description="Live scores; nothing changes until you decide."
                >
                    {previewUnavailable ? (
                        <Notice tone="warn" title="Candidates unavailable">
                            {previewUnavailable}
                        </Notice>
                    ) : candidates.length === 0 ? (
                        <EmptyState title="No candidate products found">
                            No catalogue product shares this listing’s EAN or
                            brand. Choose a product by searching, or propose a
                            new product.
                        </EmptyState>
                    ) : (
                        <ol className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                            {candidates.map((candidate, index) => (
                                <CandidateCard
                                    key={candidate.product.id}
                                    listing={listing}
                                    candidate={candidate}
                                    rank={index + 1}
                                    canChoose={can.decide && actions.choose}
                                />
                            ))}
                        </ol>
                    )}
                </Section>

                <Section
                    id="facts"
                    title="Your listing"
                    description="What your feed sent for this listing."
                >
                    <ListingFacts listing={listing} />
                </Section>

                <Section
                    id="history"
                    title="Decision history"
                    description={
                        historyTotal > history.length
                            ? `Showing the latest ${history.length} of ${historyTotal} decisions.`
                            : 'Decisions are never overwritten; corrections are new entries.'
                    }
                >
                    {history.length === 0 ? (
                        <EmptyState title="No decisions recorded yet" />
                    ) : (
                        <Timeline entries={history} />
                    )}
                </Section>
            </MerchantPage>
        </>
    );
}

MatchingListingShow.layout = (props: MatchingShowProps) => ({
    breadcrumbs: [
        { title: 'Matching', href: MatchingOverviewController.index() },
        {
            title: props.listing.title ?? `SKU ${props.listing.sku}`,
            href: MatchingListingController.show(props.listing.id),
        },
    ],
});
