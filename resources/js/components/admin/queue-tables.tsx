import { Link } from '@inertiajs/react';
import MatchingListingController from '@/actions/App/Http/Controllers/Admin/Catalogue/MatchingListingController';
import {
    AdminRow,
    AdminTable,
    bodyCell,
    DateTime,
    Fact,
    Missing,
} from '@/components/admin/admin-table';
import {
    ConflictKindChip,
    DecisionKindChip,
    MatchScore,
    StatusChip,
} from '@/components/admin/matching-badges';
import {
    CandidateResolveDialog,
    ConflictResolveDialog,
} from '@/components/admin/queue-actions';
import { buttonStyles } from '@/components/comparo/button-styles';
import { cn } from '@/lib/utils';
import type {
    CandidateRow,
    ConflictRow,
    DecisionEntry,
    MatchedProduct,
    QueueListingRow,
} from '@/types/admin';

export function ProductLine({ product }: { product: MatchedProduct | null }) {
    if (!product) {
        return <Missing />;
    }

    return (
        <span className="flex flex-col gap-0.5">
            <span className="font-bold text-text">
                {product.brand ? `${product.brand} ` : ''}
                {product.name}
            </span>
            <span className="num text-[11.5px] text-text-3">
                {product.pack}
                {product.ean ? ` · EAN ${product.ean}` : ''} · #{product.id}
            </span>
        </span>
    );
}

function listingUrl(id: number): string {
    return MatchingListingController.show.url(id);
}

export function ListingsTable({ rows }: { rows: QueueListingRow[] }) {
    return (
        <AdminTable
            caption="Merchant listings waiting for a matching decision"
            columns={[
                'Listing',
                'Source facts',
                'Status',
                'Score',
                'Suggested or linked product',
                'Updated',
                <span key="action" className="sr-only">
                    Action
                </span>,
            ]}
            minWidth="min-w-[1080px]"
        >
            {rows.map((row) => (
                <AdminRow key={row.id}>
                    <td className={cn(bodyCell, 'max-w-72')}>
                        <span className="flex flex-col gap-0.5">
                            <Link
                                href={listingUrl(row.id)}
                                className="font-bold text-text underline-offset-4 hover:underline"
                            >
                                {row.title ?? `SKU ${row.sku}`}
                            </Link>
                            <span className="num text-[11.5px] text-text-3">
                                {row.merchant.name} · SKU {row.sku}
                            </span>
                        </span>
                    </td>
                    <td className={bodyCell}>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-[12px]">
                            <dt className="text-text-4">Brand</dt>
                            <dd>
                                <Fact value={row.brandRaw} />
                            </dd>
                            <dt className="text-text-4">Pack</dt>
                            <dd>
                                <Fact value={row.packRaw} />
                            </dd>
                            <dt className="text-text-4">EAN</dt>
                            <dd className="num">
                                <Fact value={row.ean} />
                            </dd>
                        </dl>
                    </td>
                    <td className={bodyCell}>
                        <StatusChip status={row.status} />
                    </td>
                    <td className={bodyCell}>
                        <MatchScore score={row.score} level={row.level} />
                    </td>
                    <td className={bodyCell}>
                        <ProductLine product={row.product} />
                    </td>
                    <td className={bodyCell}>
                        <DateTime iso={row.updatedAt} />
                    </td>
                    <td className={bodyCell}>
                        <Link
                            href={listingUrl(row.id)}
                            className={buttonStyles.secondary}
                            aria-label={`Review ${row.title ?? `SKU ${row.sku}`}`}
                        >
                            Review
                        </Link>
                    </td>
                </AdminRow>
            ))}
        </AdminTable>
    );
}

export function ConflictsTable({ rows }: { rows: ConflictRow[] }) {
    return (
        <AdminTable
            caption="Open matching conflicts, oldest first"
            columns={[
                'Conflict',
                'Product',
                'Competing values',
                'Opened',
                <span key="action" className="sr-only">
                    Action
                </span>,
            ]}
        >
            {rows.map((row) => (
                <AdminRow key={row.id}>
                    <td className={bodyCell}>
                        <span className="flex flex-col items-start gap-1">
                            <ConflictKindChip kind={row.kind} />
                            <span className="num text-[11.5px] text-text-3">
                                #{row.id}
                                {row.field ? ` · field ${row.field}` : ''}
                            </span>
                        </span>
                    </td>
                    <td className={bodyCell}>
                        <ProductLine product={row.product} />
                    </td>
                    <td className={bodyCell}>
                        {row.values.length === 0 ? (
                            <Missing />
                        ) : (
                            <ul className="flex flex-col gap-1">
                                {row.values.map((value, index) => (
                                    <li key={index} className="text-[12px]">
                                        <span className="num font-bold text-text">
                                            {value.value}
                                        </span>{' '}
                                        <span className="text-text-3">
                                            {value.merchant
                                                ? `· ${value.merchant.name}`
                                                : `· ${value.sourceType}`}
                                            {value.observedCount > 1
                                                ? ` · seen ${value.observedCount}×`
                                                : ''}
                                        </span>
                                        {value.listingId ? (
                                            <>
                                                {' '}
                                                <Link
                                                    href={listingUrl(
                                                        value.listingId,
                                                    )}
                                                    className="font-bold text-acc-text underline-offset-4 hover:underline"
                                                >
                                                    listing #{value.listingId}
                                                </Link>
                                            </>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </td>
                    <td className={bodyCell}>
                        <DateTime iso={row.createdAt} />
                    </td>
                    <td className={cn(bodyCell, 'w-48')}>
                        <ConflictResolveDialog conflict={row} />
                    </td>
                </AdminRow>
            ))}
        </AdminTable>
    );
}

export function CandidatesTable({ rows }: { rows: CandidateRow[] }) {
    return (
        <AdminTable
            caption="Open new-product proposals, most supported first"
            columns={[
                'Proposal',
                'Listings',
                'Titles seen',
                'Opened',
                <span key="action" className="sr-only">
                    Action
                </span>,
            ]}
        >
            {rows.map((row) => (
                <AdminRow key={row.id}>
                    <td className={cn(bodyCell, 'max-w-72')}>
                        <span className="flex flex-col gap-0.5">
                            <span className="font-bold text-text">
                                {row.proposedName}
                            </span>
                            <span className="num text-[11.5px] text-text-3">
                                {row.brandRaw ?? 'No brand'} ·{' '}
                                {row.pack ?? 'no pack'}
                                {row.ean ? ` · EAN ${row.ean}` : ''}
                            </span>
                        </span>
                    </td>
                    <td className={cn(bodyCell, 'num')}>{row.sourceCount}</td>
                    <td className={bodyCell}>
                        {row.titles.length === 0 ? (
                            <Missing />
                        ) : (
                            <ul className="flex flex-col gap-0.5 text-[12px]">
                                {row.titles.map((title, index) => (
                                    <li key={index}>{title}</li>
                                ))}
                            </ul>
                        )}
                    </td>
                    <td className={bodyCell}>
                        <DateTime iso={row.createdAt} />
                    </td>
                    <td className={bodyCell}>
                        <CandidateResolveDialog candidate={row} />
                    </td>
                </AdminRow>
            ))}
        </AdminTable>
    );
}

export function DecidedBy({ entry }: { entry: DecisionEntry }) {
    if (entry.decidedBy) {
        return <>{entry.decidedBy.name}</>;
    }

    return (
        <span className="text-text-3">
            System{entry.feedRunId ? ` · feed run #${entry.feedRunId}` : ''}
        </span>
    );
}

export function HistoryTable({ rows }: { rows: DecisionEntry[] }) {
    return (
        <AdminTable
            caption="Matching decisions, newest first"
            columns={[
                'Decided',
                'Listing',
                'Decision',
                'Product',
                'Score',
                'By',
                'Reason / note',
            ]}
            minWidth="min-w-[1080px]"
        >
            {rows.map((row) => (
                <AdminRow key={row.id}>
                    <td className={bodyCell}>
                        <DateTime iso={row.decidedAt} />
                    </td>
                    <td className={bodyCell}>
                        <span className="flex flex-col gap-0.5">
                            <Link
                                href={listingUrl(row.listingId)}
                                className="font-bold text-text underline-offset-4 hover:underline"
                            >
                                Listing #{row.listingId}
                            </Link>
                            <span className="num text-[11.5px] text-text-3">
                                {row.merchant.name}
                                {row.sku ? ` · SKU ${row.sku}` : ''}
                            </span>
                        </span>
                    </td>
                    <td className={bodyCell}>
                        <DecisionKindChip kind={row.kind} />
                    </td>
                    <td className={bodyCell}>
                        <ProductLine
                            product={row.product ?? row.previousProduct}
                        />
                        {row.product && row.previousProduct ? (
                            <span className="mt-1 block text-[11.5px] text-text-3">
                                was {row.previousProduct.name} · #
                                {row.previousProduct.id}
                            </span>
                        ) : null}
                        {!row.product && row.previousProduct ? (
                            <span className="mt-1 block text-[11.5px] text-text-3">
                                (removed)
                            </span>
                        ) : null}
                    </td>
                    <td className={cn(bodyCell, 'num')}>
                        {row.score ?? <Missing />}
                    </td>
                    <td className={bodyCell}>
                        <DecidedBy entry={row} />
                    </td>
                    <td className={cn(bodyCell, 'max-w-64')}>
                        <span className="flex flex-col gap-0.5">
                            <span className="num text-[11.5px] text-text-3">
                                {row.reason ?? '—'}
                            </span>
                            {row.note ? <span>{row.note}</span> : null}
                        </span>
                    </td>
                </AdminRow>
            ))}
        </AdminTable>
    );
}
