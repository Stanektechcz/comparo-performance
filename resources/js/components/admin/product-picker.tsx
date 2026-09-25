import ProductSearchController from '@/actions/App/Http/Controllers/Admin/Catalogue/ProductSearchController';
import { SearchableProductPicker } from '@/components/matching/searchable-product-picker';
import type { ProductSearchResult } from '@/types/admin';

type ProductPickerProps = {
    /** Product that may not be picked (e.g. the currently linked one). */
    excludeId?: number | null;
    initial?: ProductSearchResult | null;
    error?: string;
};

/**
 * Searchable picker for an active catalogue product, wired to the admin
 * product search endpoint. See `SearchableProductPicker` for the shared
 * search/selection behaviour.
 */
export function ProductPicker({
    excludeId = null,
    initial = null,
    error,
}: ProductPickerProps) {
    return (
        <SearchableProductPicker
            searchUrl={(term) =>
                ProductSearchController.index.url({ query: { q: term } })
            }
            initial={initial}
            excludeId={excludeId}
            error={error}
            resultsAriaLabel="Matching products"
            noResultsText="No active products match."
            foundText={(count) =>
                `${count} active ${count === 1 ? 'product' : 'products'} found.`
            }
            showResultId
        />
    );
}
