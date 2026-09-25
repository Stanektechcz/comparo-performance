import type { ReactNode } from 'react';
import { Fact } from '@/components/admin/admin-table';
import { MatchScore } from '@/components/admin/matching-badges';
import { cn } from '@/lib/utils';
import type {
    LabelledValue,
    MatchLevel,
    MatchPart,
    MatchedProduct,
} from '@/types/admin';

/**
 * Shared matching primitives used by both the admin catalogue matching page
 * (`resources/js/pages/admin/catalogue/matching/show.tsx`) and the merchant
 * matching page (`resources/js/pages/merchant/matching/show.tsx`). Every
 * piece of copy that differs between the two audiences (bucket labels,
 * column headers, table caption, the per-candidate action) is passed in by
 * the caller so behaviour and wording stay exactly what they were before
 * this extraction.
 */

export type ComparisonRow = {
    field: string;
    listing: string | null;
    product: string | null;
};

/** The shape both `PreviewCandidate` (admin) and `MerchantCandidate` (merchant) already satisfy. */
export type MatchingCandidate = {
    product: MatchedProduct & { variants?: string[] };
    score: number;
    level: LabelledValue<MatchLevel>;
    bucket: 'auto' | 'confirm' | 'unmatched';
    parts: MatchPart[];
};

const cell = 'px-3 py-2 align-top text-[12.5px]';
const headCell =
    'px-3 py-2 text-[10px] font-extrabold tracking-[0.11em] text-text-4 uppercase';

/**
 * Per-signal evidence: the engine's English label and its points. Used on
 * both matching pages, and inside `MatchingCandidateCard` below.
 */
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

export type MatchingCandidateCardProps<TCandidate extends MatchingCandidate> = {
    candidate: TCandidate;
    rank: number;
    /** Field-by-field listing vs. product comparison, already resolved by the caller. */
    rows: ComparisonRow[];
    /** Line under the title, e.g. the bucket label (admin also includes `#id`). */
    meta: ReactNode;
    metaClassName?: string;
    tableCaption: string;
    listingColumnLabel: string;
    productColumnLabel: string;
    evidenceHeading: string;
    /** Wraps the comparison table; the merchant page adds a focusable `role="region"`, admin does not. */
    renderTable: (table: ReactNode, productTitle: string) => ReactNode;
    /** The candidate's own action (link/relink dialog); permission logic differs between admin and merchant. */
    action: ReactNode;
};

export function MatchingCandidateCard<TCandidate extends MatchingCandidate>({
    candidate,
    rank,
    rows,
    meta,
    metaClassName,
    tableCaption,
    listingColumnLabel,
    productColumnLabel,
    evidenceHeading,
    renderTable,
    action,
}: MatchingCandidateCardProps<TCandidate>) {
    const product = candidate.product;
    const productTitle = `${product.brand ? `${product.brand} ` : ''}${product.name}`;

    const table = (
        <table className="w-full border-collapse text-left">
            <caption className="sr-only">{tableCaption}</caption>
            <thead>
                <tr className="border-b border-line">
                    <th scope="col" className={headCell}>
                        Field
                    </th>
                    <th scope="col" className={headCell}>
                        {listingColumnLabel}
                    </th>
                    <th scope="col" className={headCell}>
                        {productColumnLabel}
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
                            className={cn(cell, 'font-bold text-text-3')}
                        >
                            {row.field}
                        </th>
                        <td className={cn(cell, 'break-words text-text-2')}>
                            <Fact value={row.listing} />
                        </td>
                        <td className={cn(cell, 'break-words text-text')}>
                            <Fact value={row.product} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );

    return (
        <li className="flex min-w-0 flex-col gap-4 rounded-card border border-line bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="eyebrow">Candidate {rank}</p>
                    <h3 className="mt-1 text-[16px] leading-tight font-extrabold break-words text-text">
                        {productTitle}
                    </h3>
                    <p
                        className={cn(
                            'mt-0.5 text-[11.5px] text-text-3',
                            metaClassName,
                        )}
                    >
                        {meta}
                    </p>
                </div>
                <MatchScore
                    score={candidate.score}
                    level={candidate.level}
                    size="lg"
                />
            </div>

            {renderTable(table, productTitle)}

            <div>
                <p className="mb-1.5 text-[12px] font-extrabold text-text-2">
                    {evidenceHeading}
                </p>
                <EvidenceParts
                    parts={candidate.parts}
                    label={`Score evidence for ${productTitle}`}
                />
            </div>

            {action}
        </li>
    );
}
