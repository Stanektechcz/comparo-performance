import { CandidateChoiceDialog } from '@/components/admin/listing-actions';
import {
    EvidenceParts,
    MatchingCandidateCard,
} from '@/components/matching/candidate-card';
import type { ComparisonRow } from '@/components/matching/candidate-card';
import type {
    ListingActions,
    ListingEvidence,
    PreviewCandidate,
} from '@/types/admin';

export { EvidenceParts };

const bucketLabels: Record<PreviewCandidate['bucket'], string> = {
    auto: 'Would auto-link',
    confirm: 'Needs confirmation',
    unmatched: 'Below the match threshold',
};

type ComparisonProps = {
    listing: ListingEvidence;
    candidate: PreviewCandidate;
    rank: number;
    actions: ListingActions;
    canRematch: boolean;
};

function CandidateCard({
    listing,
    candidate,
    rank,
    actions,
    canRematch,
}: ComparisonProps) {
    const product = candidate.product;
    const rows: ComparisonRow[] = [
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
    const productTitle = `${product.brand ? `${product.brand} ` : ''}${product.name}`;

    return (
        <MatchingCandidateCard
            candidate={candidate}
            rank={rank}
            rows={rows}
            meta={
                <>
                    #{product.id} · {bucketLabels[candidate.bucket]}
                </>
            }
            metaClassName="num"
            tableCaption={`Listing source fields compared with ${productTitle}`}
            listingColumnLabel="Listing"
            productColumnLabel="Product"
            evidenceHeading="Why this score"
            renderTable={(table) => (
                <div className="overflow-x-auto">{table}</div>
            )}
            action={
                <CandidateChoiceDialog
                    listingId={listing.id}
                    product={product}
                    actions={actions}
                    canRematch={canRematch}
                    linkedProductId={listing.linkedProductId}
                />
            }
        />
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
