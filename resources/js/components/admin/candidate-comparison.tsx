import { Fact } from '@/components/admin/admin-table';
import { CandidateChoiceDialog } from '@/components/admin/listing-actions';
import { MatchScore } from '@/components/admin/matching-badges';
import { cn } from '@/lib/utils';
import type {
    ListingActions,
    ListingEvidence,
    MatchPart,
    PreviewCandidate,
} from '@/types/admin';

const bucketLabels: Record<PreviewCandidate['bucket'], string> = {
    auto: 'Would auto-link',
    confirm: 'Needs confirmation',
    unmatched: 'Below the match threshold',
};

/** Per-signal evidence: the engine's English label and its points. */
export function EvidenceParts({
    parts,
    label,
}: {
    parts: MatchPart[];
    label: string;
}) {
    if (parts.length === 0) {
        return (
            <p className="text-[13px] text-text-3">
                No evidence recorded for this decision.
            </p>
        );
    }

    return (
        <ul aria-label={label} className="flex flex-col gap-1">
            {parts.map((part, index) => (
                <li
                    key={`${part.signal}-${index}`}
                    className="flex items-baseline justify-between gap-3 border-b border-line-soft py-1 text-[12.5px] last:border-b-0"
                >
                    <span className="text-text-2">{part.label}</span>
                    <span
                        className={cn(
                            'num font-bold',
                            part.points > 0
                                ? 'text-ok'
                                : part.points < 0
                                  ? 'text-danger-2'
                                  : 'text-text-3',
                        )}
                    >
                        {part.points > 0 ? `+${part.points}` : part.points}
                        <span className="sr-only"> points</span>
                    </span>
                </li>
            ))}
        </ul>
    );
}

type ComparisonProps = {
    listing: ListingEvidence;
    candidate: PreviewCandidate;
    rank: number;
    actions: ListingActions;
    canRematch: boolean;
};

const cell = 'px-3 py-2 align-top text-[12.5px]';

function CandidateCard({
    listing,
    candidate,
    rank,
    actions,
    canRematch,
}: ComparisonProps) {
    const product = candidate.product;
    const productTitle = `${product.brand ? `${product.brand} ` : ''}${product.name}`;
    const rows: {
        field: string;
        listing: string | null;
        product: string | null;
    }[] = [
        { field: 'Brand', listing: listing.brandRaw, product: product.brand },
        {
            field: 'Title / name',
            listing: listing.title,
            product: product.name,
        },
        { field: 'Pack', listing: listing.packRaw, product: product.pack },
        {
            field: 'Variant',
            listing: listing.variantRaw,
            product:
                product.variants.length > 0
                    ? product.variants.join(', ')
                    : null,
        },
        { field: 'EAN', listing: listing.ean, product: product.ean },
    ];

    return (
        <li className="flex min-w-0 flex-col gap-4 rounded-card border border-line bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="eyebrow">Candidate {rank}</p>
                    <h3 className="mt-1 text-[16px] leading-tight font-extrabold text-text">
                        {productTitle}
                    </h3>
                    <p className="mt-0.5 num text-[11.5px] text-text-3">
                        #{product.id} · {bucketLabels[candidate.bucket]}
                    </p>
                </div>
                <MatchScore
                    score={candidate.score}
                    level={candidate.level}
                    size="lg"
                />
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-left">
                    <caption className="sr-only">
                        Listing source fields compared with {productTitle}
                    </caption>
                    <thead>
                        <tr className="border-b border-line">
                            <th
                                scope="col"
                                className="px-3 py-2 text-[10px] font-extrabold tracking-[0.11em] text-text-4 uppercase"
                            >
                                Field
                            </th>
                            <th
                                scope="col"
                                className="px-3 py-2 text-[10px] font-extrabold tracking-[0.11em] text-text-4 uppercase"
                            >
                                Listing
                            </th>
                            <th
                                scope="col"
                                className="px-3 py-2 text-[10px] font-extrabold tracking-[0.11em] text-text-4 uppercase"
                            >
                                Product
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
                    Why this score
                </p>
                <EvidenceParts
                    parts={candidate.parts}
                    label={`Score evidence for ${productTitle}`}
                />
            </div>

            <CandidateChoiceDialog
                listingId={listing.id}
                product={product}
                actions={actions}
                canRematch={canRematch}
                linkedProductId={listing.linkedProductId}
            />
        </li>
    );
}

export function CandidateComparison({
    listing,
    candidates,
    actions,
    canRematch,
}: {
    listing: ListingEvidence;
    candidates: PreviewCandidate[];
    actions: ListingActions;
    canRematch: boolean;
}) {
    return (
        <ol className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            {candidates.map((candidate, index) => (
                <CandidateCard
                    key={candidate.product.id}
                    listing={listing}
                    candidate={candidate}
                    rank={index + 1}
                    actions={actions}
                    canRematch={canRematch}
                />
            ))}
        </ol>
    );
}
