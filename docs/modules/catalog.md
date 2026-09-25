# Module: Catalog

Namespace `App\Domain\Catalog`. ADR: 0002. Entity detail: [entity-inventory.md](../architecture/entity-inventory.md) §2.3–2.6.

## Responsibilities

- Canonical products, brands, categories (tree), ingredients and doses, variants.
- Product lifecycle: active, merged (kept, redirected), retired.
- Catalogue completeness score (feeds ComparoRank's offer-quality bonus).

Owned tables: `brands`, `categories`, `ingredients`, `products`, `product_variants`, `ingredient_product`.
Listing ↔ product links (`merchant_products`) belong to Offers; matching (Phase 2) writes them.

## Public API

| Class | Member | Contract |
|---|---|---|
| `ProductStatus` (enum) | `active`, `merged`, `retired` | |
| `Completeness\ProductCompletenessService` | `evaluate(ProductFacts): ProductCompleteness` | Pure |
| `Completeness\ProductFacts` | `hasEan`, `hasBrand`, `hasPackSize`, `hasCategory`, `ingredientCount`, `hasServings`, `descriptionLength`, `flavourVariantCount` | Immutable input |
| `Completeness\ProductCompleteness` | `percent` (0–100), `missing` (field labels), `ratio()` | Immutable output |
| `App\Models\Product` | relations `brand`, `category`, `mergedInto`, `variants`, `ingredients`, `offers`, `complianceRules`, `marketPriceStats`; scope `listed`; `isMerged()` | Persistence |

Completeness: 8 equally weighted fields — EAN, Brand, Pack size, Category, Ingredients (≥ 1), Servings,
Description (> 80 characters), Variants (≥ 1 flavour variant); JS-rounded percentage.

## Invariants

- `products.slug` and `products.reference` are unique; `ean` is indexed but not unique.
- Merges never delete: `status = merged`, `merged_into_id`, `merged_at`; URLs 301 to the survivor;
  merges need `catalogue.merge` and an audit entry.
- Models hold no scoring logic.
- Rating summary columns on `products` are derived (written by the Reviews context later), never edited by hand.

## Parity status

| Capability | Cases | Status |
|---|---|---|
| Completeness | 46 products | passing |
| Dosing (cost per active gram / serving) | `dosing.json`, 1 242 cases | exported, not ported |
| Matching (listing → product) | `matching.json`, 22 items | exported, not ported (Phase 2) |
