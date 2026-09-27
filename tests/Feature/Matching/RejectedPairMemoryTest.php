<?php

use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use App\Models\Product;
use Tests\Feature\Matching\MatchingScenario as Scenario;

/*
 * BACKLOG F-05: a (listing, product) pair a person rejected is a negative
 * signal derived from the append-only decision history — automatic matching
 * never suggests or links that product for that listing again.
 */

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

function rejectSuggestion(MerchantProduct $listing): MatchingDecision
{
    return app(DecideMatch::class)->reject($listing->fresh(), Scenario::merchantActor($listing), Scenario::at());
}

/**
 * Two catalogue duplicates (same brand, name, pack and EAN) that score
 * identically for the listing; the first (lower id) wins the tie.
 *
 * @return array{0: Product, 1: Product, 2: MerchantProduct}
 */
function twinProductsListing(): array
{
    $first = Scenario::product('Acme Nutrition', 'Whey Isolate', '900 g');
    $second = Scenario::product('Acme Nutrition', 'Whey Isolate', '900 g', ['ean' => $first->ean]);

    return [$first, $second, Scenario::listing(Scenario::autoFacts($first))];
}

it('does not re-suggest a rejected product after the listing facts change', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::confirmFacts($product));
    Scenario::match($listing);
    $rejected = rejectSuggestion($listing);
    $listing->update(['title' => 'Protein powder vanilla flavour']);

    $outcome = Scenario::match($listing);

    expect($outcome->reused)->toBeFalse()
        ->and($outcome->status)->toBe(ListingMatchStatus::Unmatched)
        ->and($outcome->productId)->toBeNull()
        ->and($listing->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => null, 'match_status' => ListingMatchStatus::Unmatched, 'current_matching_decision_id' => $rejected->id])
        ->and(MatchingDecision::query()->where('product_id', $product->id)->count())->toBe(1);
});

it('does not auto-link a rejected product, even when a forced rematch runs', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    Scenario::match($listing);
    rejectSuggestion($listing);

    $outcome = Scenario::match($listing, Scenario::context(force: true));

    expect($outcome->status)->toBe(ListingMatchStatus::Unmatched)
        ->and($outcome->productId)->toBeNull()
        ->and($listing->fresh()->product_id)->toBeNull();
});

it('uses the next-best candidate instead of the rejected product', function () {
    [$first, $second, $listing] = twinProductsListing();
    $initial = Scenario::match($listing);
    rejectSuggestion($listing);
    $listing->update(['variant_raw' => 'Vanilla']);

    $outcome = Scenario::match($listing);

    expect($initial->productId)->toBe($first->id)
        ->and($outcome->productId)->toBe($second->id)
        ->and($outcome->score)->toBe($initial->score)
        ->and(MatchingDecision::query()->findOrFail($outcome->decisionId)->reason)->toBe(MatchListing::REASON_FACTS_CHANGED);
});

it('lets a person link a rejected product explicitly, which lifts the rejection', function () {
    $product = Scenario::product();
    $listing = Scenario::listing(Scenario::autoFacts($product));
    Scenario::match($listing);
    rejectSuggestion($listing);

    $chosen = app(DecideMatch::class)->choose($listing->fresh(), $product->id, Scenario::staffActor(), Scenario::at());
    $listing->update(['title' => $listing->title.' tub']);
    $outcome = Scenario::match($listing, Scenario::context(force: true));

    expect($chosen->kind)->toBe(MatchDecisionKind::Manual)
        ->and($outcome->productId)->toBe($product->id)
        ->and($outcome->status)->toBe(ListingMatchStatus::Auto);
});

it('remembers every rejected product of the listing, not only the latest', function () {
    [$first, $second, $listing] = twinProductsListing();
    Scenario::match($listing);
    rejectSuggestion($listing);
    $listing->update(['variant_raw' => 'Vanilla']);
    expect(Scenario::match($listing)->productId)->toBe($second->id);
    rejectSuggestion($listing);
    $listing->update(['variant_raw' => 'Chocolate']);

    $outcome = Scenario::match($listing);

    expect($outcome->productId)->toBeNull()
        ->and($outcome->status)->toBe(ListingMatchStatus::Unmatched)
        ->and(MatchingDecision::query()->where('kind', MatchDecisionKind::Rejected)->orderBy('id')->pluck('previous_product_id')->all())
        ->toBe([$first->id, $second->id]);
});

it('keeps the rejection scoped to the listing that rejected it', function () {
    $product = Scenario::product();
    $rejecting = Scenario::listing(Scenario::autoFacts($product));
    Scenario::match($rejecting);
    rejectSuggestion($rejecting);

    $other = Scenario::listing(Scenario::autoFacts($product));

    expect(Scenario::match($other)->productId)->toBe($product->id);
});
