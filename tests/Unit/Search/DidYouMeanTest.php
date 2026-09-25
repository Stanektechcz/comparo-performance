<?php

use App\Domain\Search\DidYouMean;
use App\Domain\Search\DidYouMeanSuggestion;
use App\Domain\Search\Local\SearchableEntry;

/**
 * @param  list<DidYouMeanSuggestion>  $suggestions
 * @return list<array{0: string, 1: string, 2: int}>
 */
function suggestionRows(array $suggestions): array
{
    return array_map(static fn (DidYouMeanSuggestion $s): array => [$s->entry->type->value, $s->label(), $s->score], $suggestions);
}

it('suggests close labels above 12, best first, without categories', function () {
    $entries = [
        SearchableEntry::category(1, 'Protein'),
        SearchableEntry::ingredient('whey-isolate', 'Whey isolate'),
        SearchableEntry::product(1, 'Whey Isolate 90'),
        SearchableEntry::brand(1, 'Kinetiq'),
    ];

    $suggestions = suggestionRows((new DidYouMean)->suggest('protien isolat', $entries));

    // "isolat" is a prefix hit (60), "protien" misses: round(60 / 2) = 30.
    expect($suggestions)->toBe([
        ['product', 'Whey Isolate 90', 30],
        ['ingredient', 'Whey isolate', 30],
    ]);
});

it('keeps at most the limit and trims the query once', function () {
    $entries = array_map(static fn (int $id): SearchableEntry => SearchableEntry::brand($id, "Whey {$id}"), range(1, 7));

    expect(suggestionRows((new DidYouMean)->suggest('  whey ', $entries)))->toHaveCount(5)
        ->and(suggestionRows((new DidYouMean(limit: 2))->suggest('whey', $entries)))->toBe([['brand', 'Whey 1', 92], ['brand', 'Whey 2', 92]])
        ->and((new DidYouMean)->suggest(' w ', $entries))->toBe([]);
});
