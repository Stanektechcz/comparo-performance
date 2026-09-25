<?php

namespace App\Domain\Search;

/**
 * Entities that have search documents (docs/architecture/phase-3-search.md §2).
 * Used by `search_documents.entity_type`, `search_index_outbox.entity` and
 * `search_clicks.entity_type`. Coupons and articles are not searchable in
 * Phase 3 (OPEN-DECISIONS A-25).
 */
enum SearchEntityType: string
{
    case Product = 'product';
    case Brand = 'brand';
    case Category = 'category';
    case Ingredient = 'ingredient';
    case Merchant = 'merchant';
}
