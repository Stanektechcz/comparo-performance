import ProductSearchController from '@/actions/App/Http/Controllers/Merchant/Matching/ProductSearchController';
import { SearchableProductPicker } from '@/components/matching/searchable-product-picker';
import type { ProductSearchResult } from '@/types/merchant';

/**
 * Search the public catalogue (≤ 20 active products) and pick one; submits
 * `product_id`, wired to the merchant product search endpoint. See
 * `SearchableProductPicker` for the shared search/selection behaviour.
 */
export function MerchantProductPicker({
    initial = null,
    excludeId = null,
    error,
}: {
    initial?: ProductSearchResult | null;
    excludeId?: number | null;
    error?: string;
}) {
    return (
        <SearchableProductPicker
            searchUrl={(term) =>
                ProductSearchController.index.url({ query: { q: term } })
            }
            initial={initial}
            excludeId={excludeId}
            error={error}
            resultsAriaLabel="Catalogue products"
            noResultsText="No catalogue products match."
            foundText={(count) =>
                `${count} ${count === 1 ? 'product' : 'products'} found.`
            }
        />
    );
}
