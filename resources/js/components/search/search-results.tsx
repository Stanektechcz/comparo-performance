import { Link } from '@inertiajs/react';
import { RatingLine } from '@/components/catalog/rating-line';
import { Badge } from '@/components/comparo/badge';
import { Money } from '@/components/money';
import { reportResultClick } from '@/components/search/click-beacon';
import { useMarket } from '@/hooks/use-shared-props';
import { pluralize } from '@/lib/format';
import type { ProductResult, SearchResult } from '@/types/search';

const TYPE_LABELS: Record<Exclude<SearchResult['type'], 'product'>, string> = {
    brand: 'Brand',
    shop: 'Shop',
    category: 'Category',
    ingredient: 'Ingredient',
};

type ResultLinkProps = {
    result: SearchResult;
    searchId: string | null;
    className: string;
    children: string;
};

/** A result title link that reports the click without delaying navigation. */
function ResultLink({
    result,
    searchId,
    className,
    children,
}: ResultLinkProps) {
    return (
        <Link
            href={result.href}
            className={className}
            onClick={() =>
                reportResultClick(
                    searchId,
                    result.type,
                    result.id,
                    result.position,
                )
            }
        >
            {children}
        </Link>
    );
}

/**
 * Product result: the lowest landed total for the market from the live
 * comparison. `unknown` compliance shows the total as information only.
 * There is never a buy button in search results.
 */
function ProductRow({
    result,
    searchId,
}: {
    result: ProductResult;
    searchId: string | null;
}) {
    const market = useMarket();
    const { product, compliance } = result;

    return (
        <article className="flex flex-col gap-3 rounded-card border border-line bg-surface p-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
                <p className="num text-[10px] font-semibold tracking-[0.08em] text-text-3 uppercase">
                    {product.brand.name}
                </p>
                <h3 className="mt-1 text-[15px] leading-snug font-bold break-words">
                    <ResultLink
                        result={result}
                        searchId={searchId}
                        className="text-text hover:text-acc-text"
                    >
                        {product.name}
                    </ResultLink>
                </h3>
                <p className="mt-1 text-xs text-text-3">
                    {product.category.name} · {product.packLabel}
                </p>
                <RatingLine rating={product.rating} className="mt-2" />
            </div>
            <div className="shrink-0 sm:text-right">
                {product.lowestTotal ? (
                    <p className="text-xs text-text-3">
                        From{' '}
                        <Money
                            value={product.lowestTotal}
                            className="text-base font-bold text-acc-text"
                        />
                        <span className="block">
                            incl. shipping to {market.name}
                        </span>
                    </p>
                ) : (
                    <p className="text-xs text-text-3">
                        No offers for {market.name} yet
                    </p>
                )}
                {compliance.purchasable ? (
                    <p className="mt-1 num text-[11px] text-text-4">
                        {product.offerCount}{' '}
                        {pluralize(product.offerCount, 'offer')}
                    </p>
                ) : (
                    <Badge
                        tone="warn"
                        className="mt-1.5"
                        title={`${compliance.label} for ${market.name}`}
                    >
                        Price information only
                    </Badge>
                )}
            </div>
        </article>
    );
}

function EntityRow({
    result,
    searchId,
}: {
    result: Exclude<SearchResult, ProductResult>;
    searchId: string | null;
}) {
    return (
        <article className="flex items-center justify-between gap-3 rounded-card border border-line bg-surface px-4 py-3">
            <div className="min-w-0">
                <p className="text-[10px] font-extrabold tracking-[0.08em] text-text-3 uppercase">
                    {TYPE_LABELS[result.type]}
                </p>
                <h3 className="text-[15px] font-bold break-words">
                    <ResultLink
                        result={result}
                        searchId={searchId}
                        className="text-text hover:text-acc-text"
                    >
                        {result.name}
                    </ResultLink>
                </h3>
            </div>
            {result.type === 'brand' ? (
                <p className="shrink-0 num text-xs text-text-3">
                    {result.productCount}{' '}
                    {pluralize(result.productCount, 'product')}
                </p>
            ) : null}
            {result.type === 'shop' && result.verified ? (
                <Badge tone="ok">Verified</Badge>
            ) : null}
            {result.type === 'ingredient' ? (
                <p className="shrink-0 text-xs text-text-3">Products with it</p>
            ) : null}
        </article>
    );
}

export function SearchResultList({
    results,
    searchId,
    startIndex,
}: {
    results: SearchResult[];
    searchId: string | null;
    startIndex: number;
}) {
    return (
        <ol className="flex flex-col gap-2.5" start={startIndex}>
            {results.map((result) => (
                <li key={`${result.type}-${result.id}`}>
                    {result.type === 'product' ? (
                        <ProductRow result={result} searchId={searchId} />
                    ) : (
                        <EntityRow result={result} searchId={searchId} />
                    )}
                </li>
            ))}
        </ol>
    );
}
