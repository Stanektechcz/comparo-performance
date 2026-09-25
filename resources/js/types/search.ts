/**
 * Inertia page-prop and API contracts of public search
 * (App\Http\Presenters\SearchPresenter, SearchSuggestPresenter).
 * The server re-checks compliance for every hit: blocked products never
 * arrive here, and no result carries a purchase or outbound link.
 */
import type {
    ComplianceStatus,
    ProductSummary,
    SeoHead,
} from '@/types/catalog';

export type SearchTab =
    | 'all'
    | 'product'
    | 'brand'
    | 'shop'
    | 'category'
    | 'ingredient';

export type SearchResultType = Exclude<SearchTab, 'all'>;

export type SearchSort = 'relevance' | 'price_asc' | 'rating' | 'name';

/** The validated query string, echoed back (whole currency units for prices). */
export type SearchCriteria = {
    q: string;
    type: SearchTab;
    sort: SearchSort;
    page: number;
    brand: string[];
    category: string[];
    ingredient: string[];
    price_min: number | null;
    price_max: number | null;
    in_stock: boolean;
    min_rating: number | null;
};

export type SearchTabCount = { key: SearchTab; label: string; count: number };

export type FacetOption = {
    value: string;
    label: string;
    count: number;
    selected: boolean;
};

export type SearchFacets = {
    brands: FacetOption[];
    categories: FacetOption[];
    ingredients: FacetOption[];
};

type ResultBase = {
    id: number;
    /** 1-based position within the displayed results (click attribution). */
    position: number;
    /** Site-relative link. */
    href: string;
};

export type ProductResult = ResultBase & {
    type: 'product';
    product: ProductSummary;
    compliance: {
        status: ComplianceStatus;
        label: string;
        /** False for `unknown`: the total is informational only. */
        purchasable: boolean;
    };
};

export type BrandResult = ResultBase & {
    type: 'brand';
    name: string;
    slug: string;
    productCount: number;
};

export type ShopResult = ResultBase & {
    type: 'shop';
    name: string;
    slug: string;
    verified: boolean;
};

export type CategoryResult = ResultBase & {
    type: 'category';
    name: string;
    slug: string;
};

export type IngredientResult = ResultBase & {
    type: 'ingredient';
    name: string;
    slug: string;
};

export type SearchResult =
    | ProductResult
    | BrandResult
    | ShopResult
    | CategoryResult
    | IngredientResult;

export type SearchPagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    links: { prev: string | null; next: string | null };
};

export type SearchLink = { label: string; href: string };

export type BrowseCategory = { slug: string; name: string; href: string };

export type SearchPageProps = {
    seo: SeoHead;
    query: SearchCriteria;
    /** False when the text is shorter than 2 characters (empty state). */
    searchable: boolean;
    /** Recorded search id for click attribution; null when not recorded. */
    searchId: string | null;
    priceCurrency: string;
    sortOptions: { value: SearchSort; label: string }[];
    tabs: SearchTabCount[];
    results: SearchResult[];
    facets: SearchFacets;
    pagination: SearchPagination;
    didYouMean: SearchLink[];
    browseCategories: BrowseCategory[];
};

type SuggestLink = { slug: string; name: string; url: string };

/** GET /api/public/v1/search/suggest — names and site links only, never prices. */
export type SuggestResponse = {
    data: {
        query: string;
        products: (SuggestLink & { brand: string; packLabel: string })[];
        brands: SuggestLink[];
        categories: SuggestLink[];
        ingredients: SuggestLink[];
        merchants: SuggestLink[];
    };
    meta: { market: string; took_ms: number };
};
