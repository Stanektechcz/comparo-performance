<?php

use App\Domain\Search\Facets\FacetShaper;
use App\Domain\Search\Facets\FacetValue;
use App\Domain\Search\Local\EntryAttributes;
use App\Domain\Search\Local\SearchableEntry;

it('counts types and product facet values once per product, most frequent first', function () {
    $entries = [
        SearchableEntry::product(1, 'A', attributes: new EntryAttributes(brandSlug: 'ironforge', categoryPath: ['sports', 'protein'], ingredientSlugs: ['whey-isolate', 'whey-isolate'])),
        SearchableEntry::product(2, 'B', attributes: new EntryAttributes(brandSlug: 'biopeak', categoryPath: ['sports', 'creatine'], ingredientSlugs: ['creatine'])),
        SearchableEntry::product(3, 'C', attributes: new EntryAttributes(brandSlug: 'ironforge', categoryPath: ['sports', 'protein'], ingredientSlugs: ['whey-isolate', 'casein'])),
        SearchableEntry::product(4, 'D'),
        SearchableEntry::brand(1, 'IRONFORGE', new EntryAttributes(brandSlug: 'ignored')),
        SearchableEntry::shop(1, 'PeakSupps', 'peaksupps.de'),
        SearchableEntry::ingredient('whey-isolate', 'Whey isolate'),
    ];

    $facets = (new FacetShaper)->shape($entries);

    expect($facets->typeCounts)->toBe(['all' => 7, 'product' => 4, 'brand' => 1, 'shop' => 1, 'category' => 0, 'ingredient' => 1])
        ->and($facets->brands)->toEqual([new FacetValue('ironforge', 2), new FacetValue('biopeak', 1)])
        ->and($facets->categories)->toEqual([new FacetValue('sports', 3), new FacetValue('protein', 2), new FacetValue('creatine', 1)])
        ->and($facets->ingredients)->toEqual([new FacetValue('whey-isolate', 2), new FacetValue('casein', 1), new FacetValue('creatine', 1)]);
});

it('returns zero counts for an empty result set', function () {
    $facets = (new FacetShaper)->shape([]);

    expect($facets->typeCounts)->toBe(['all' => 0, 'product' => 0, 'brand' => 0, 'shop' => 0, 'category' => 0, 'ingredient' => 0])
        ->and($facets->brands)->toBe([])
        ->and($facets->categories)->toBe([])
        ->and($facets->ingredients)->toBe([]);
});
