<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Search\Local\EntryAttributes;
use App\Domain\Search\Local\MarketAttributes;
use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Local\SearchableType;

it('rejects texts that do not belong to the entry type', function (Closure $build) {
    $build();
})->throws(InvalidArgumentException::class)->with([
    'brand with an EAN' => [fn () => new SearchableEntry(SearchableType::Brand, 1, 'IRONFORGE', ean: '85910475146')],
    'category with ingredients' => [fn () => new SearchableEntry(SearchableType::Category, 1, 'Protein', ingredientNames: ['Whey'])],
    'product with a web domain' => [fn () => new SearchableEntry(SearchableType::Product, 1, 'Whey', web: 'whey.de')],
]);

it('rejects inconsistent market and rating attributes', function (Closure $build) {
    $build();
})->throws(InvalidArgumentException::class)->with([
    'blocked with a price' => [fn () => new MarketAttributes(ComplianceStatus::NotAllowed, minTotalMinor: 1990)],
    'blocked and purchasable' => [fn () => new MarketAttributes(ComplianceStatus::PrescriptionOnly, purchasable: true)],
    'unknown and purchasable' => [fn () => new MarketAttributes(ComplianceStatus::Unknown, purchasable: true)],
    'negative total' => [fn () => new MarketAttributes(ComplianceStatus::Allowed, minTotalEurMinor: -1)],
    'lower-case market' => [fn () => new EntryAttributes(markets: ['de' => new MarketAttributes(ComplianceStatus::Allowed)])],
    'rating above five' => [fn () => new EntryAttributes(ratingAverage: 5.5)],
    'negative rating count' => [fn () => new EntryAttributes(ratingCount: -1)],
]);

it('keeps informational totals for unknown compliance', function () {
    $state = new MarketAttributes(ComplianceStatus::Unknown, minTotalMinor: 1990, minTotalEurMinor: 1990);

    expect($state->purchasable)->toBeFalse()
        ->and($state->minTotalMinor)->toBe(1990);
});
