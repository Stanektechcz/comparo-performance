import { search } from '@/routes';
import type { SearchCriteria } from '@/types/search';

/**
 * A /search URL for the criteria with overrides. Defaults are left out
 * (mirrors SearchPresenter::url) so URLs stay short. Changing anything but
 * the page resets to page 1 unless a page is given.
 */
export function searchUrl(
    criteria: SearchCriteria,
    overrides: Partial<SearchCriteria> = {},
): string {
    const values: SearchCriteria = {
        ...criteria,
        page: 1,
        ...overrides,
    };

    return search.url({
        query: {
            q: values.q === '' ? undefined : values.q,
            type: values.type === 'all' ? undefined : values.type,
            brand: values.brand.length > 0 ? values.brand : undefined,
            category: values.category.length > 0 ? values.category : undefined,
            ingredient:
                values.ingredient.length > 0 ? values.ingredient : undefined,
            price_min: values.price_min ?? undefined,
            price_max: values.price_max ?? undefined,
            in_stock: values.in_stock ? 1 : undefined,
            min_rating: values.min_rating ?? undefined,
            sort: values.sort === 'relevance' ? undefined : values.sort,
            page: values.page > 1 ? values.page : undefined,
        },
    });
}

/** A plain text search (header box, recent searches, did-you-mean). */
export function textSearchUrl(q: string): string {
    return search.url({ query: { q } });
}
