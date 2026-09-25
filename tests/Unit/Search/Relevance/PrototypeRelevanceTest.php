<?php

use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Relevance\PrototypeRelevance;
use App\Domain\Search\Relevance\RelevanceHit;
use Tests\Support\PrototypeFixtures;

/**
 * @param  list<RelevanceHit>  $hits
 * @return list<array<string, int|string>>
 */
function relevanceRows(array $hits): array
{
    return array_map(PrototypeFixtures::hitAsPrototype(...), $hits);
}

it('normalises the prototype trailing-space inconsistency by trimming once for every type', function () {
    $fixture = array_column(PrototypeFixtures::load('search')['results'], 'list', 'query');

    $hits = relevanceRows(PrototypeRelevance::prototype()->rank('creatine ', PrototypeFixtures::searchEntries()));

    // The prototype scores the Creatine category with the untrimmed query (54) but "creatine" at 94.
    expect($fixture['creatine '])->toContain(['type' => 'category', 'id' => 2, 'score' => 54])
        ->and($fixture['creatine'])->toContain(['type' => 'category', 'id' => 2, 'score' => 94])
        ->and($hits[0])->toBe(['type' => 'category', 'id' => 2, 'score' => 94])
        ->and($hits)->toBe(relevanceRows(PrototypeRelevance::prototype()->rank('creatine', PrototypeFixtures::searchEntries())));
});

it('sends an exact SKU or EAN straight to the product', function (string $query) {
    $hits = PrototypeRelevance::prototype()->rank($query, PrototypeFixtures::searchEntries());

    expect(relevanceRows($hits))->toBe([['type' => 'product', 'id' => 6, 'score' => 100]]);
})->with(['sku' => 'CMP-0006', 'folded sku' => 'cmp-0006', 'ean' => '85910475146', 'ean with trailing space' => '85910475146 ']);

it('keeps insertion order between equal scores, types before input order', function () {
    $entries = [
        SearchableEntry::ingredient('whey', 'Whey'),
        SearchableEntry::brand(2, 'Whey'),
        SearchableEntry::product(9, 'Whey'),
        SearchableEntry::brand(1, 'Whey'),
        SearchableEntry::shop(3, 'Whey'),
        SearchableEntry::category(4, 'Whey'),
    ];

    $hits = relevanceRows(PrototypeRelevance::prototype()->rank('whey', $entries));

    expect($hits)->toBe([
        ['type' => 'brand', 'id' => 2, 'score' => 102],
        ['type' => 'brand', 'id' => 1, 'score' => 102],
        ['type' => 'shop', 'id' => 3, 'score' => 101],
        ['type' => 'product', 'id' => 9, 'score' => 100],
        ['type' => 'category', 'id' => 4, 'score' => 94],
        ['type' => 'ingredient', 'name' => 'Whey', 'score' => 88],
    ]);
});

it('lists other types by their score before the offset and products by their final score', function () {
    $entries = [
        SearchableEntry::product(1, 'Zinc', brandName: 'Kinetiq', categoryName: 'Whey'),
        SearchableEntry::ingredient('whey', 'Whey'),
    ];

    // One fuzzy token of four: round(38 / 4) = 10. The product keeps max(0, 10 − 30) = 0 and is dropped;
    // the ingredient passes on 10 and is listed at 10 − 12.
    $hits = relevanceRows(PrototypeRelevance::prototype()->rank('wey xxxx yyyy zzzz', $entries));

    expect($hits)->toBe([['type' => 'ingredient', 'name' => 'Whey', 'score' => -2]]);
});

it('raises a product below 40 that contains a sibling synonym to 34', function () {
    $entries = [
        SearchableEntry::product(1, 'Night Blend', ingredientNames: ['Micellar casein'], categoryName: 'Recovery'),
        SearchableEntry::product(2, 'Night Blend', ingredientNames: ['Zinc'], categoryName: 'Recovery'),
    ];

    $hits = relevanceRows(PrototypeRelevance::prototype()->rank('whey', $entries));

    expect($hits)->toBe([['type' => 'product', 'id' => 1, 'score' => 34]]);
});

it('returns nothing below the minimum length', function (string $query) {
    expect(PrototypeRelevance::prototype()->rank($query, PrototypeFixtures::searchEntries()))->toBe([]);
})->with(['', 'a', ' a ', "\u{00A0}a\u{3000}"]);
