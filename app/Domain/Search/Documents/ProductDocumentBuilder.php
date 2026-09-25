<?php

namespace App\Domain\Search\Documents;

use App\Domain\Catalog\BrandAliasStatus;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Queries\ProductMarketSnapshot;
use App\Domain\Search\Queries\ProductMarketState;
use App\Models\BrandAlias;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use DateTimeImmutable;

/**
 * The only factory of product documents. Only `Product::listed()` products
 * get a document; any other requested id (merged, retired, missing) is
 * returned for deletion. Market data comes from ProductMarketSnapshot.
 */
final readonly class ProductDocumentBuilder
{
    public function __construct(private ProductMarketSnapshot $snapshot) {}

    /**
     * @param  list<int>  $productIds
     * @return BuiltDocuments<ProductDocument>
     */
    public function build(array $productIds, DateTimeImmutable $now): BuiltDocuments
    {
        $requested = BuildIds::normalize($productIds);
        $products = Product::query()
            ->listed()
            ->whereKey($requested)
            ->with([
                'brand.aliases' => static fn ($query) => $query->where('status', '!=', BrandAliasStatus::Rejected->value)->orderBy('id'),
                'category',
                'variants',
                'ingredients' => static fn ($query) => $query->where('ingredient_product.is_listed', true),
            ])
            ->orderBy('id')
            ->get();

        $listedIds = array_values(array_map(static fn (Product $product): int => $product->id, $products->all()));
        $states = $this->snapshot->forProducts($listedIds, $now);
        $paths = $listedIds === [] ? [] : CategoryPaths::all();
        $indexedAt = BuildIds::indexedAt($now);
        $documents = [];

        foreach ($products as $product) {
            $documents[] = $this->document($product, $states[$product->id] ?? [], $paths[$product->category_id] ?? [$product->category->slug], $indexedAt);
        }

        return new BuiltDocuments(
            SearchIndex::Products,
            $documents,
            BuildIds::missing($requested, $listedIds),
        );
    }

    /**
     * @param  array<string, ProductMarketState>  $states
     * @param  list<string>  $categoryPath
     */
    private function document(Product $product, array $states, array $categoryPath, string $indexedAt): ProductDocument
    {
        $variants = $product->variants;
        $ingredients = $product->ingredients;
        $ratingCount = $product->rating_count;

        return new ProductDocument(
            id: $product->id,
            slug: $product->slug,
            name: $product->name,
            packLabel: $product->pack_label,
            shortDescription: $product->short_description === null ? null : mb_substr($product->short_description, 0, ProductDocument::SHORT_DESCRIPTION_LIMIT),
            variantNames: array_values(array_unique(array_map(static fn (ProductVariant $variant): string => $variant->name, $variants->all()))),
            identifiers: self::identifiers($product),
            ean: $product->ean,
            sku: $product->reference,
            brandId: $product->brand->id,
            brandSlug: $product->brand->slug,
            brandName: $product->brand->name,
            brandAliases: array_values(array_unique(array_map(static fn (BrandAlias $alias): string => $alias->alias, $product->brand->aliases->all()))),
            categoryId: $product->category->id,
            categorySlug: $product->category->slug,
            categoryName: $product->category->name,
            categoryPath: $categoryPath,
            ingredientNames: array_values(array_map(static fn (Ingredient $ingredient): string => $ingredient->name, $ingredients->all())),
            ingredientSlugs: array_values(array_map(static fn (Ingredient $ingredient): string => $ingredient->slug, $ingredients->all())),
            ratingAverage: $ratingCount > 0 && $product->weighted_rating !== null ? (float) $product->weighted_rating : null,
            ratingCount: $ratingCount,
            markets: array_map(ProductMarketEntry::fromState(...), $states),
            indexedAt: $indexedAt,
        );
    }

    /**
     * EAN, SKU (reference) and variant EANs, first occurrence kept.
     *
     * @return list<string>
     */
    private static function identifiers(Product $product): array
    {
        $identifiers = [$product->ean, $product->reference];

        foreach ($product->variants as $variant) {
            $identifiers[] = $variant->ean;
        }

        return array_values(array_unique(array_filter($identifiers, static fn (?string $value): bool => $value !== null && $value !== '')));
    }
}
