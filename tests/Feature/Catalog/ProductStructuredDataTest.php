<?php

use App\Models\Product;
use App\Models\RatingAggregate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogScenario;

/**
 * A-31/A-37: schema.org AggregateRating is published only for a real
 * (aggregated) rating with at least the configured number of reviews — never
 * for imported demo or manual ratings.
 */
beforeEach(function () {
    $this->catalog = CatalogScenario::create();
});

function ratedProduct(CatalogScenario $catalog, ?string $source, int $count): Product
{
    $product = $catalog->product(['weighted_rating' => '4.6', 'rating_count' => $count, 'rating_source' => $source]);
    $catalog->allow($product);
    $catalog->offer($product, $catalog->merchant(['DE' => 390]), 3000);

    return $product;
}

it('publishes AggregateRating for an aggregated rating at the review minimum', function () {
    $minimum = (int) config('comparo.thresholds.rating_min_reviews');
    $product = ratedProduct($this->catalog, RatingAggregate::SOURCE_AGGREGATED, $minimum);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.jsonLd.0.aggregateRating', [
                '@type' => 'AggregateRating',
                'ratingValue' => '4.6',
                'reviewCount' => $minimum,
            ]));
});

it('omits AggregateRating for demo, manual, unlabelled or too-thin ratings', function (?string $source, int $countOffset) {
    $count = (int) config('comparo.thresholds.rating_min_reviews') + $countOffset;
    $product = ratedProduct($this->catalog, $source, $count);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->has('seo.jsonLd.0.name')
            ->missing('seo.jsonLd.0.aggregateRating'));
})->with([
    'imported demo rating' => ['prototype_demo', 100],
    'manual rating' => ['manual', 100],
    'rating without a source' => [null, 100],
    'aggregated below the minimum' => [RatingAggregate::SOURCE_AGGREGATED, -1],
]);
