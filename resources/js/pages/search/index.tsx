import { Link, router } from '@inertiajs/react';
import { Search as SearchIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    useEffect,
    useId,
    useMemo,
    useState,
    useSyncExternalStore,
} from 'react';
import { PageHeader } from '@/components/catalog/page-header';
import { Pagination } from '@/components/catalog/pagination';
import { buttonStyles, selectStyles } from '@/components/comparo/button-styles';
import { EmptyState } from '@/components/comparo/empty-state';
import { CatalogBreadcrumbs } from '@/components/navigation/catalog-breadcrumbs';
import {
    clearRecentSearches,
    parseRecentSearches,
    recentSearchesSnapshot,
    rememberSearch,
    subscribeRecentSearches,
} from '@/components/search/recent-searches';
import { SearchFilters } from '@/components/search/search-filters';
import { SearchResultList } from '@/components/search/search-results';
import { searchUrl, textSearchUrl } from '@/components/search/search-url';
import { SeoHead } from '@/components/seo/seo-head';
import { useMarket } from '@/hooks/use-shared-props';
import { catalogUrls } from '@/lib/catalog-urls';
import { formatNumber, pluralize } from '@/lib/format';
import { cn } from '@/lib/utils';
import { search } from '@/routes';
import type {
    BrowseCategory,
    SearchCriteria,
    SearchPageProps,
    SearchSort,
    SearchTabCount,
} from '@/types/search';

/**
 * Remount key for SearchFilters: its price and rating inputs are local
 * state, so they must restart from the server-confirmed values whenever a
 * visit changes them (e.g. "Clear all filters", back/forward).
 */
function filterStateKey(criteria: SearchCriteria): string {
    return [criteria.price_min, criteria.price_max, criteria.min_rating]
        .map((value) => value ?? '')
        .join(':');
}

function SearchForm({ criteria }: { criteria: SearchCriteria }) {
    const id = useId();
    const [text, setText] = useState(criteria.q);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            searchUrl(criteria, {
                q: text.trim(),
                brand: [],
                category: [],
                ingredient: [],
                price_min: null,
                price_max: null,
                in_stock: false,
                min_rating: null,
            }),
        );
    };

    return (
        <form
            role="search"
            onSubmit={submit}
            action={search.url()}
            method="get"
            className="mt-4 flex max-w-2xl gap-2"
        >
            <label htmlFor={id} className="sr-only">
                Search text
            </label>
            <div className="relative min-w-0 flex-1">
                <SearchIcon
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-text-4"
                />
                <input
                    id={id}
                    name="q"
                    type="search"
                    value={text}
                    maxLength={200}
                    autoComplete="off"
                    onChange={(event) => setText(event.target.value)}
                    placeholder="Product, brand, shop, ingredient or EAN"
                    className="min-h-11 w-full rounded-field border border-line-2 bg-field pr-3 pl-9 text-[15px] text-text placeholder:text-text-4"
                />
            </div>
            <button type="submit" className={buttonStyles.primary}>
                Search
            </button>
        </form>
    );
}

function TypeTabs({
    tabs,
    criteria,
}: {
    tabs: SearchTabCount[];
    criteria: SearchCriteria;
}) {
    return (
        <nav
            aria-label="Result types"
            className="-mx-4 mt-5 overflow-x-auto px-4 sm:mx-0 sm:px-0"
        >
            <ul className="flex w-max gap-1 border-b border-line">
                {tabs.map((tab) => {
                    const current = tab.key === criteria.type;

                    return (
                        <li key={tab.key}>
                            <Link
                                href={searchUrl(criteria, { type: tab.key })}
                                preserveScroll
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-11 items-center gap-1.5 px-3 text-[14px] font-bold whitespace-nowrap',
                                    current
                                        ? 'text-text shadow-[inset_0_-3px_0_0_var(--acc)]'
                                        : 'text-text-3 hover:text-text',
                                )}
                            >
                                {tab.label}
                                <span className="num text-[11px] text-text-4">
                                    {tab.count}
                                </span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

function BrowseCategories({ categories }: { categories: BrowseCategory[] }) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="browse-categories" className="mt-6">
            <h2
                id="browse-categories"
                className="text-base font-extrabold text-text"
            >
                Browse categories
            </h2>
            <ul className="mt-3 flex flex-wrap gap-2">
                {categories.map((category) => (
                    <li key={category.slug}>
                        <Link
                            href={category.href}
                            className={buttonStyles.secondary}
                        >
                            {category.name}
                        </Link>
                    </li>
                ))}
            </ul>
            <p className="mt-3">
                <Link
                    href={catalogUrls.categories()}
                    className={buttonStyles.tertiary}
                >
                    All categories
                </Link>
            </p>
        </section>
    );
}

/** Recent searches live in this browser only (localStorage). */
function RecentSearches() {
    const raw = useSyncExternalStore(
        subscribeRecentSearches,
        recentSearchesSnapshot,
        () => '',
    );
    const recent = useMemo(() => parseRecentSearches(raw), [raw]);

    if (recent.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="recent-searches" className="mt-6">
            <div className="flex flex-wrap items-center gap-3">
                <h2
                    id="recent-searches"
                    className="text-base font-extrabold text-text"
                >
                    Your recent searches
                </h2>
                <button
                    type="button"
                    onClick={clearRecentSearches}
                    className={buttonStyles.tertiary}
                >
                    Clear
                </button>
            </div>
            <p className="mt-1 text-xs text-text-3">
                Stored only in this browser.
            </p>
            <ul className="mt-3 flex flex-wrap gap-2">
                {recent.map((query) => (
                    <li key={query}>
                        <Link
                            href={textSearchUrl(query)}
                            className={cn(buttonStyles.secondary, 'max-w-full')}
                        >
                            <span className="truncate">{query}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function ZeroResults({
    props,
}: {
    props: Pick<SearchPageProps, 'query' | 'didYouMean' | 'browseCategories'>;
}) {
    const market = useMarket();

    return (
        <EmptyState
            title={`Nothing found for “${props.query.q}” in ${market.name}`}
        >
            {props.didYouMean.length > 0 ? (
                <p>
                    Did you mean{' '}
                    {props.didYouMean.map((suggestion, index) => (
                        <span key={suggestion.href}>
                            {index > 0 ? ', ' : ''}
                            <Link
                                href={suggestion.href}
                                className="font-bold text-acc-text underline-offset-4 hover:underline"
                            >
                                {suggestion.label}
                            </Link>
                        </span>
                    ))}
                    ?
                </p>
            ) : (
                <p>
                    Check the spelling, try a more general term or remove
                    filters.
                </p>
            )}
        </EmptyState>
    );
}

export default function SearchPage(props: SearchPageProps) {
    const {
        query,
        searchable,
        searchId,
        tabs,
        results,
        facets,
        pagination,
        browseCategories,
        sortOptions,
        priceCurrency,
    } = props;
    const market = useMarket();
    const sortId = useId();

    useEffect(() => {
        if (searchable) {
            rememberSearch(query.q);
        }
    }, [searchable, query.q]);

    return (
        <>
            <SeoHead seo={props.seo} />
            <CatalogBreadcrumbs
                items={[
                    { label: 'Home', href: catalogUrls.home() },
                    { label: 'Search' },
                ]}
            />
            <PageHeader
                title={searchable ? `Results for “${query.q}”` : 'Search'}
            >
                Products, brands, shops, categories and ingredients available in{' '}
                {market.name}. Prices are total landed prices including
                shipping.
            </PageHeader>
            <SearchForm key={query.q} criteria={query} />

            {!searchable ? (
                <>
                    <p className="mt-4 text-sm text-text-3">
                        Type at least 2 characters to search.
                    </p>
                    <RecentSearches />
                    <BrowseCategories categories={browseCategories} />
                </>
            ) : (
                <>
                    <TypeTabs tabs={tabs} criteria={query} />
                    <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-[260px_minmax(0,1fr)]">
                        <aside aria-label="Filters">
                            <SearchFilters
                                key={filterStateKey(query)}
                                criteria={query}
                                facets={facets}
                                priceCurrency={priceCurrency}
                            />
                        </aside>
                        <section
                            aria-labelledby="search-results-heading"
                            className="min-w-0"
                        >
                            <h2 id="search-results-heading" className="sr-only">
                                Search results, page {pagination.currentPage}
                            </h2>
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <p
                                    aria-live="polite"
                                    className="text-sm text-text-3"
                                >
                                    <span className="num font-bold text-text">
                                        {formatNumber(
                                            pagination.total,
                                            market.locale,
                                        )}
                                    </span>{' '}
                                    {pluralize(pagination.total, 'result')}
                                </p>
                                <div className="flex items-center gap-2">
                                    <label
                                        htmlFor={sortId}
                                        className="text-xs font-bold text-text-3"
                                    >
                                        Sort by
                                    </label>
                                    <select
                                        id={sortId}
                                        value={query.sort}
                                        onChange={(event) =>
                                            router.get(
                                                searchUrl(query, {
                                                    sort: event.target
                                                        .value as SearchSort,
                                                }),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                        className={selectStyles}
                                    >
                                        {sortOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            {results.length > 0 ? (
                                <SearchResultList
                                    results={results}
                                    searchId={searchId}
                                    startIndex={
                                        (pagination.currentPage - 1) *
                                            pagination.perPage +
                                        1
                                    }
                                />
                            ) : (
                                <>
                                    <ZeroResults props={props} />
                                    <BrowseCategories
                                        categories={browseCategories}
                                    />
                                </>
                            )}
                            <Pagination
                                meta={pagination}
                                links={pagination.links}
                            />
                        </section>
                    </div>
                </>
            )}
        </>
    );
}
