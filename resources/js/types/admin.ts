/**
 * Props of the staff matching console (/admin/catalogue/matching).
 * Mirrors app/Http/Presenters/Admin/* — every field is whitelisted there.
 */
import type { Paginated } from '@/types/catalog';

export type LabelledValue<T extends string = string> = {
    value: T;
    label: string;
};

export type ListingMatchStatus =
    | 'unmatched'
    | 'suggested'
    | 'auto'
    | 'manual'
    | 'compliance_hold'
    | 'rejected';

export type MatchLevel =
    | 'exact'
    | 'very_high'
    | 'high'
    | 'possible'
    | 'manual_review';

export type MatchDecisionKind =
    | 'auto'
    | 'suggested'
    | 'manual'
    | 'rejected'
    | 'rematch'
    | 'unlinked';

export type ConflictKind =
    | 'field_conflict'
    | 'compliance_hold'
    | 'merge_blocked';

export type MatchingTab = 'listings' | 'conflicts' | 'candidates' | 'history';

export type MerchantRef = { id: number; name: string };

export type MatchedProduct = {
    id: number;
    name: string;
    slug: string;
    brand: string | null;
    pack: string;
    ean: string | null;
    status: string;
};

export type MatchPart = {
    signal: string;
    label: string;
    points: number;
};

export type QueueListingRow = {
    id: number;
    merchant: MerchantRef;
    sku: string;
    title: string | null;
    ean: string | null;
    brandRaw: string | null;
    packRaw: string | null;
    variantRaw: string | null;
    status: LabelledValue<ListingMatchStatus>;
    score: number | null;
    level: LabelledValue<MatchLevel> | null;
    isLinked: boolean;
    product: MatchedProduct | null;
    decision: {
        id: number | null;
        kind: LabelledValue<MatchDecisionKind>;
        reason: string | null;
        decidedAt: string | null;
    } | null;
    updatedAt: string | null;
};

export type ConflictRow = {
    id: number;
    kind: LabelledValue<ConflictKind>;
    field: string | null;
    product: MatchedProduct | null;
    createdAt: string | null;
    canResolve: boolean;
    values: {
        merchant: MerchantRef | null;
        listingId: number | null;
        sourceType: string;
        value: string;
        observedCount: number;
    }[];
};

export type CandidateRow = {
    id: number;
    proposedName: string;
    brandRaw: string | null;
    ean: string | null;
    pack: string | null;
    sourceCount: number;
    titles: string[];
    createdAt: string | null;
};

export type DecisionEntry = {
    id: number;
    listingId: number;
    merchant: MerchantRef;
    sku: string | null;
    kind: LabelledValue<MatchDecisionKind>;
    product: MatchedProduct | null;
    previousProduct: MatchedProduct | null;
    score: number | null;
    reason: string | null;
    note: string | null;
    decidedBy: { id: number; name: string } | null;
    feedRunId: number | null;
    supersedesId: number | null;
    decidedAt: string | null;
    parts: MatchPart[];
};

export type MatchingFilters = {
    merchant: number | null;
    status: ListingMatchStatus | null;
    minScore: number | null;
    maxScore: number | null;
};

export type MatchingIndexProps = {
    tab: MatchingTab;
    tabs: { key: MatchingTab; label: string }[];
    filters: MatchingFilters;
    filterOptions: {
        merchants: MerchantRef[];
        statuses: LabelledValue<ListingMatchStatus>[];
    };
    listings: Paginated<QueueListingRow> | null;
    conflicts: Paginated<ConflictRow> | null;
    candidates: Paginated<CandidateRow> | null;
    history: Paginated<DecisionEntry> | null;
    can: { rematch: boolean; resolveComplianceHolds: boolean };
};

export type ListingEvidence = {
    id: number;
    merchant: MerchantRef;
    feedSource: { id: number; name: string } | null;
    market: { code: string; name: string } | null;
    sku: string;
    externalId: string | null;
    title: string | null;
    ean: string | null;
    brandRaw: string | null;
    packRaw: string | null;
    variantRaw: string | null;
    categoryRaw: string | null;
    url: string | null;
    listingStatus: string;
    status: LabelledValue<ListingMatchStatus>;
    score: number | null;
    level: LabelledValue<MatchLevel> | null;
    linkedProductId: number | null;
    firstSeenAt: string | null;
    lastSeenAt: string | null;
    missingRunCount: number;
    rawPayload: {
        fields: { key: string; value: string }[];
        truncated: boolean;
    } | null;
};

export type PreviewCandidate = {
    product: MatchedProduct & { variants: string[] };
    score: number;
    level: LabelledValue<MatchLevel>;
    bucket: 'auto' | 'confirm' | 'unmatched';
    parts: MatchPart[];
};

export type ListingActions = {
    confirm: boolean;
    choose: boolean;
    reject: boolean;
    rematch: boolean;
};

export type MatchingShowProps = {
    listing: ListingEvidence;
    currentDecision: DecisionEntry | null;
    candidates: PreviewCandidate[];
    previewUnavailable: string | null;
    history: DecisionEntry[];
    historyTotal: number;
    can: { decide: boolean; rematch: boolean };
    actions: ListingActions;
};

/** One row of GET /admin/catalogue/products/search. */
export type ProductSearchResult = {
    id: number;
    name: string;
    brand: string;
    pack: string;
    ean: string | null;
};
