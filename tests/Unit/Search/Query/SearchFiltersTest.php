<?php

use App\Domain\Search\Query\SearchFilters;

it('accepts typed filters and reports whether any is set', function () {
    $filters = new SearchFilters(
        brandSlugs: ['ironforge'],
        categorySlugs: ['protein'],
        ingredientSlugs: ['whey-isolate', 'bcaa-4-1-1'],
        priceMinMinor: 1000,
        priceMaxMinor: 1000,
        inStock: true,
        minRating: 4.5,
    );

    expect($filters->isEmpty())->toBeFalse()
        ->and($filters->hasPriceRange())->toBeTrue()
        ->and((new SearchFilters)->isEmpty())->toBeTrue()
        ->and((new SearchFilters(priceMaxMinor: 0))->isEmpty())->toBeFalse();
});

it('rejects invalid filter values', function (array $arguments) {
    new SearchFilters(...$arguments);
})->throws(InvalidArgumentException::class)->with([
    'upper-case slug' => [['brandSlugs' => ['IronForge']]],
    'slug with a space' => [['categorySlugs' => ['pre workout']]],
    'empty slug' => [['ingredientSlugs' => ['']]],
    'repeated slug' => [['brandSlugs' => ['ironforge', 'ironforge']]],
    'non-list slugs' => [['brandSlugs' => ['a' => 'ironforge']]],
    'too many slugs' => [['brandSlugs' => array_map(static fn (int $i): string => "brand-{$i}", range(1, 51))]],
    'negative price' => [['priceMinMinor' => -1]],
    'inverted price range' => [['priceMinMinor' => 2000, 'priceMaxMinor' => 1999]],
    'rating above five' => [['minRating' => 5.1]],
    'negative rating' => [['minRating' => -0.1]],
    'NaN rating' => [['minRating' => NAN]],
]);
