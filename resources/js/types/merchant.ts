/**
 * Props of the merchant portal (/merchant/feeds, /merchant/matching).
 * Mirrors app/Http/Presenters/Merchant/* — every field is whitelisted
 * there. Feed URLs are masked and credentials are reduced to
 * `hasCredentials`: secrets never reach the browser.
 */
import type {
    LabelledValue,
    ListingMatchStatus,
    MatchDecisionKind,
    MatchedProduct,
    MatchLevel,
    MatchPart,
    ProductSearchResult,
} from '@/types/admin';
import type { Paginated } from '@/types/catalog';

export type { LabelledValue, MatchedProduct, MatchPart, ProductSearchResult };

export type FeedFormat = 'csv' | 'xml' | 'json';
export type FeedTransport = 'url' | 'upload' | 'api_push' | 'manual_upload';
export type FeedSourceStatus =
    | 'draft'
    | 'active'
    | 'paused'
    | 'error'
    | 'disabled';
export type FeedRunStatus =
    | 'queued'
    | 'fetching'
    | 'parsing'
    | 'normalizing'
    | 'matching'
    | 'publishing'
    | 'completed'
    | 'failed'
    | 'cancelled';
export type FeedRunOutcome =
    | 'published'
    | 'published_with_warnings'
    | 'unchanged';
export type FeedErrorSeverity = 'info' | 'warning' | 'error' | 'fatal';

export type FeedRunSummary = {
    id: number;
    status: LabelledValue<FeedRunStatus>;
    outcome: LabelledValue<FeedRunOutcome> | null;
    trigger: LabelledValue;
    isActive: boolean;
    isCancellable: boolean;
    createdAt: string | null;
    finishedAt: string | null;
    durationMs: number | null;
    rowsRead: number;
    rowsValid: number;
    rowsInvalid: number;
    warnings: number;
    errors: number;
    failure: { code: string; message: string } | null;
};

export type FeedSummary = {
    id: number;
    name: string;
    format: LabelledValue<FeedFormat>;
    transport: LabelledValue<FeedTransport>;
    status: LabelledValue<FeedSourceStatus>;
    statusReason: string | null;
    maskedUrl: string | null;
    hasCredentials: boolean;
    market: { code: string; name: string } | null;
    currency: string;
    intervalMinutes: number | null;
    lastRunAt: string | null;
    lastSuccessAt: string | null;
    nextRunAt: string | null;
    consecutiveFailures: number;
    mapping: {
        version: number;
        activatedAt: string | null;
        mappedFields: number;
    } | null;
};

export type FeedListItem = FeedSummary & { latestRun: FeedRunSummary | null };

export type FeedAbilities = {
    can: { update: boolean; run: boolean; manageCredentials: boolean };
    actions: {
        pause: boolean;
        resume: boolean;
        runNow: boolean;
        upload: boolean;
    };
};

export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type FeedFormOptions = {
    formats: Option<FeedFormat>[];
    transports: Option<FeedTransport>[];
    encodings: Option[];
    delimiters: Option[];
    intervals: Option<number>[];
    currencies: Option[];
    markets: Option[];
};

export type FeedFormDefaults = {
    name: string;
    format: FeedFormat;
    transport: FeedTransport;
    maskedUrl: string | null;
    currency: string;
    country: string | null;
    encoding: string;
    delimiter: string;
    recordElement: string;
    intervalMinutes: number | null;
    hasCredentials: boolean;
};

export type FeedIndexProps = {
    feeds: Paginated<FeedListItem>;
    can: { create: boolean };
};

export type FeedCreateProps = {
    options: FeedFormOptions;
    can: { create: boolean };
};

export type FeedShowProps = FeedAbilities & {
    feed: FeedSummary;
    runs: Paginated<FeedRunSummary>;
};

export type FeedEditProps = FeedAbilities & {
    feed: FeedSummary;
    defaults: FeedFormDefaults;
    options: FeedFormOptions;
    credentialsConfirmed: boolean;
};

export type MappingField = {
    key: string;
    label: string;
    required: boolean;
    hint: string | null;
};

export type PreviewIssue = {
    code: string;
    severity: string;
    field: string | null;
    message: string;
};

export type PreviewRow = {
    lineNumber: number;
    status: 'valid' | 'warning' | 'invalid';
    sku: string | null;
    values: { key: string; label: string; value: string }[];
    issues: PreviewIssue[];
};

export type FeedMappingProps = FeedAbilities & {
    feed: FeedSummary;
    fields: MappingField[];
    preview: {
        available: boolean;
        headers: string[];
        mapping: Record<string, string>;
        mappingSuggested: boolean;
        isDraft: boolean;
        missingRequired: { key: string; label: string }[];
        rows: PreviewRow[];
        error: { code: string; message: string; line: number | null } | null;
    };
};

export type RunStage = { key: string; label: string; at: string | null };

export type RunMetricGroup = {
    title: string;
    items: { key: string; label: string; value: number }[];
};

export type FeedRunDetail = FeedRunSummary & {
    payloadBytes: number | null;
    stages: RunStage[];
    metrics: RunMetricGroup[];
};

export type ErrorGroup = {
    code: string;
    severity: LabelledValue<FeedErrorSeverity>;
    count: number;
    capped: boolean;
    message: string;
    samples: {
        rowNumber: number | null;
        sku: string | null;
        field: string | null;
        message: string;
    }[];
};

export type ErrorRow = {
    id: number;
    rowNumber: number | null;
    sku: string | null;
    code: string;
    severity: LabelledValue<FeedErrorSeverity>;
    field: string | null;
    message: string;
};

export type FeedRunShowProps = {
    feed: FeedSummary;
    run: FeedRunDetail;
    errorGroups: ErrorGroup[];
    errorRows: Paginated<ErrorRow>;
    errorFilter: string | null;
    can: { cancel: boolean };
};

export type MerchantQueueRow = {
    id: number;
    sku: string;
    title: string | null;
    ean: string | null;
    brandRaw: string | null;
    packRaw: string | null;
    variantRaw: string | null;
    status: LabelledValue<ListingMatchStatus>;
    score: number | null;
    level: LabelledValue<MatchLevel> | null;
    product: MatchedProduct | null;
    decidedAt: string | null;
    updatedAt: string | null;
};

export type MerchantDecision = {
    id: number;
    listingId: number;
    sku: string | null;
    kind: LabelledValue<MatchDecisionKind>;
    product: MatchedProduct | null;
    previousProduct: MatchedProduct | null;
    score: number | null;
    note: string | null;
    decidedBy: string;
    decidedAt: string | null;
    parts: MatchPart[];
};

export type MatchingOverviewProps = {
    counts: { suggested: number; unmatched: number; decisions: number };
};

export type MatchingQueueProps = { listings: Paginated<MerchantQueueRow> };

export type MatchingHistoryProps = { decisions: Paginated<MerchantDecision> };

export type MerchantListingFacts = {
    id: number;
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
    status: LabelledValue<ListingMatchStatus>;
    score: number | null;
    level: LabelledValue<MatchLevel> | null;
    linkedProductId: number | null;
    firstSeenAt: string | null;
    lastSeenAt: string | null;
};

export type MerchantCandidate = {
    product: MatchedProduct;
    score: number;
    level: LabelledValue<MatchLevel>;
    bucket: 'auto' | 'confirm' | 'unmatched';
    parts: MatchPart[];
};

export type MerchantListingActions = {
    confirm: boolean;
    choose: boolean;
    reject: boolean;
    propose: boolean;
};

export type MatchingShowProps = {
    listing: MerchantListingFacts;
    currentDecision: MerchantDecision | null;
    candidates: MerchantCandidate[];
    previewUnavailable: string | null;
    history: MerchantDecision[];
    historyTotal: number;
    can: { decide: boolean; propose: boolean };
    actions: MerchantListingActions;
};
