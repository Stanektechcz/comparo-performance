<?php

use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Actions\ResolveProductCandidate;
use App\Domain\Matching\ListingMatchStatus;
use App\Models\Country;
use App\Models\FeedSource;
use App\Models\MerchantProduct;
use App\Models\Product;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use App\Models\ProductComplianceRule;
use Tests\Feature\Matching\MatchingScenario as Scenario;

/*
 * BACKLOG F-07: a candidate whose source listings come from several markets
 * is checked against every source market — the product is held when it is
 * blocked in ANY of them (ListingMarkets::holdForCandidate).
 */

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    $this->germany = Country::factory()->code('DE')->create();
    $this->france = Country::factory()->code('FR')->create();
});

function candidateSourceIn(ProductCandidate $candidate, Country $country): MerchantProduct
{
    $listing = MerchantProduct::factory()->fromFeed(FeedSource::factory()->create(['country_id' => $country->id]))->unmatched()->create();
    ProductCandidateSource::factory()->forListing($listing)->create(['product_candidate_id' => $candidate->id]);

    return $listing;
}

function complianceIn(Product $product, Country $country, ComplianceStatus $status): void
{
    ProductComplianceRule::factory()->status($status)->create(['product_id' => $product->id, 'country_id' => $country->id]);
}

function linkCandidate(ProductCandidate $candidate, Product $product): void
{
    app(ResolveProductCandidate::class)->linkExisting(
        $candidate, $product->id, Scenario::staffActor(), Scenario::at(),
        app(ListingMarkets::class)->holdForCandidate($candidate),
    );
}

it('holds every source listing when the product is blocked in one of the source markets', function () {
    $product = Scenario::product();
    complianceIn($product, $this->germany, ComplianceStatus::Allowed);
    complianceIn($product, $this->france, ComplianceStatus::NotAllowed);
    $candidate = ProductCandidate::factory()->create();
    $german = candidateSourceIn($candidate, $this->germany);
    $french = candidateSourceIn($candidate, $this->france);

    linkCandidate($candidate, $product);

    expect($german->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::ComplianceHold])
        ->and($french->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::ComplianceHold]);
});

it('links the source listings when the product is allowed in every source market', function () {
    $product = Scenario::product();
    complianceIn($product, $this->germany, ComplianceStatus::Allowed);
    complianceIn($product, $this->france, ComplianceStatus::Allowed);
    $candidate = ProductCandidate::factory()->create();
    $german = candidateSourceIn($candidate, $this->germany);
    $french = candidateSourceIn($candidate, $this->france);

    linkCandidate($candidate, $product);

    expect($german->fresh()->match_status)->toBe(ListingMatchStatus::Manual)
        ->and($french->fresh()->match_status)->toBe(ListingMatchStatus::Manual);
});

it('ignores markets none of the source listings are sold in', function () {
    $product = Scenario::product();
    complianceIn($product, $this->germany, ComplianceStatus::Allowed);
    complianceIn($product, $this->france, ComplianceStatus::NotAllowed);
    $candidate = ProductCandidate::factory()->create();
    $german = candidateSourceIn($candidate, $this->germany);

    linkCandidate($candidate, $product);

    expect($german->fresh()->match_status)->toBe(ListingMatchStatus::Manual);
});
