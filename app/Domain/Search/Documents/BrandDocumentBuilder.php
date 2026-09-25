<?php

namespace App\Domain\Search\Documents;

use App\Domain\Catalog\BrandAliasStatus;
use App\Domain\Search\Contracts\SearchIndex;
use App\Models\Brand;
use App\Models\BrandAlias;
use DateTimeImmutable;

/**
 * The only factory of brand documents. Missing brands are returned for deletion.
 */
final readonly class BrandDocumentBuilder
{
    /**
     * @param  list<int>  $brandIds
     * @return BuiltDocuments<BrandDocument>
     */
    public function build(array $brandIds, DateTimeImmutable $now): BuiltDocuments
    {
        $requested = BuildIds::normalize($brandIds);
        $indexedAt = BuildIds::indexedAt($now);
        $brands = Brand::query()
            ->whereKey($requested)
            ->with(['aliases' => static fn ($query) => $query->where('status', '!=', BrandAliasStatus::Rejected->value)->orderBy('id')])
            ->withCount(['products' => static fn ($query) => $query->listed()])
            ->orderBy('id')
            ->get();

        $documents = array_values(array_map(static fn (Brand $brand): BrandDocument => new BrandDocument(
            id: $brand->id,
            slug: $brand->slug,
            name: $brand->name,
            aliases: array_values(array_unique(array_map(static fn (BrandAlias $alias): string => $alias->alias, $brand->aliases->all()))),
            productCount: (int) $brand->getAttribute('products_count'),
            indexedAt: $indexedAt,
        ), $brands->all()));

        return new BuiltDocuments(
            SearchIndex::Brands,
            $documents,
            BuildIds::missing($requested, array_map(static fn (BrandDocument $document): int => $document->id, $documents)),
        );
    }
}
