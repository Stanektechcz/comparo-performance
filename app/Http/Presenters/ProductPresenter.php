<?php

namespace App\Http\Presenters;

use App\Domain\Catalog\Completeness\ProductCompletenessService;
use App\Domain\Catalog\Completeness\ProductFacts;
use App\Domain\Compliance\ComplianceDecision;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Shared\Money;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

final class ProductPresenter
{
    public function __construct(
        private readonly OfferComparisonPresenter $offers,
        private readonly ProductCompletenessService $completeness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(Product $product): array
    {
        $product->loadMissing(['brand', 'category', 'variants', 'ingredients']);
        $flavours = $product->variants->where('kind', ProductVariant::FLAVOUR);

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => ['name' => $product->brand->name, 'slug' => $product->brand->slug],
            'category' => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'ean' => $product->ean,
            'packLabel' => $product->pack_label,
            'servings' => $product->servings,
            'shortDescription' => $product->short_description,
            'description' => $product->description,
            'flavours' => $flavours->pluck('name')->values()->all(),
            'packs' => $product->variants->where('kind', ProductVariant::PACK)->pluck('name')->values()->all(),
            'ingredients' => $product->ingredients->map(static fn (Ingredient $ingredient): array => [
                'name' => $ingredient->name,
                'slug' => $ingredient->slug,
                'amountMg' => $ingredient->pivot->amount_mg === null ? null : (float) $ingredient->pivot->amount_mg,
                'isCarrier' => (bool) $ingredient->pivot->is_carrier,
                'nrvPercent' => $ingredient->pivot->nrv_percent === null ? null : (float) $ingredient->pivot->nrv_percent,
            ])->values()->all(),
            'completeness' => (function () use ($product, $flavours): array {
                $result = $this->completeness->evaluate(new ProductFacts(
                    hasEan: filled($product->ean),
                    hasBrand: true,
                    hasPackSize: filled($product->pack_label),
                    hasCategory: true,
                    ingredientCount: $product->ingredients->count(),
                    hasServings: (bool) $product->servings,
                    descriptionLength: mb_strlen((string) $product->description),
                    flavourVariantCount: $flavours->count(),
                ));

                return ['percent' => $result->percent, 'missing' => $result->missing];
            })(),
            'rrp' => $product->rrp_minor === null ? null : MoneyPresenter::present(Money::of($product->rrp_minor, (string) $product->rrp_currency)),
            'rating' => self::rating($product),
        ];
    }

    /**
     * `$decisionsByProductId`, when given, must map each product's id to what
     * `ComplianceResolver::decide()` would return for it in this market at
     * `$now` (e.g. a caller's own `decideMany()` batch) — passing it skips a
     * redundant per-product compliance query for a product already covered
     * by the caller's batch; any product missing from the map still resolves
     * its own decision.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<int, ComplianceDecision>|null  $decisionsByProductId
     * @return list<array<string, mixed>>
     */
    public function summaries(Collection $products, MarketContext $market, DateTimeImmutable $now, ?array $decisionsByProductId = null): array
    {
        $products->loadMissing(['brand', 'category']);

        return array_values($products->map(function (Product $product) use ($market, $now, $decisionsByProductId): array {
            $compliance = $decisionsByProductId[$product->id] ?? null;
            $summary = $this->offers->forPage($product, $market, $now, $compliance)['offerSummary'];

            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'brand' => ['name' => $product->brand->name, 'slug' => $product->brand->slug],
                'category' => ['name' => $product->category->name, 'slug' => $product->category->slug],
                'packLabel' => $product->pack_label,
                'lowestTotal' => $summary['lowestTotal'],
                'offerCount' => $summary['shown'],
                'rating' => self::rating($product),
            ];
        })->all());
    }

    /**
     * @return array{average: float|null, count: int, source: string|null}
     */
    public static function rating(Product $product): array
    {
        return [
            'average' => $product->weighted_rating === null ? null : (float) $product->weighted_rating,
            'count' => $product->rating_count,
            'source' => $product->rating_source,
        ];
    }
}
