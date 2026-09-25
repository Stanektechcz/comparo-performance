<?php

use App\Domain\Matching\Actions\MatchingActor;
use App\Domain\Matching\Actions\ProposeProductCandidate;
use App\Domain\Matching\Actions\ResolveProductCandidate;
use App\Domain\Matching\CandidateStatus;
use App\Domain\Matching\Exceptions\ListingOutsideMerchantScope;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\MatchingDecision;
use App\Models\Merchant;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use App\Models\User;
use Tests\Feature\Matching\MatchingScenario as Scenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

function proposableListing(array $facts = [], ?Merchant $merchant = null)
{
    return Scenario::listing([
        'title' => 'Zenith Labs Hydro Whey 750 g',
        'brand_raw' => 'Zenith Labs',
        'pack_raw' => '750 g',
        'ean' => '4099999999990',
        ...$facts,
    ], $merchant);
}

it('proposes a candidate from an unmatched listing without changing the listing', function () {
    $brand = Brand::factory()->create(['name' => 'Zenith Labs']);
    $listing = proposableListing();
    $user = User::factory()->create();

    $candidate = app(ProposeProductCandidate::class)->handle($listing, MatchingActor::merchant($user, $listing->merchant_id), Scenario::at());

    expect($candidate->only(['status', 'proposed_name', 'brand_id', 'brand_raw', 'ean', 'pack_label', 'source_count', 'fingerprint']))
        ->toBe([
            'status' => CandidateStatus::Proposed,
            'proposed_name' => 'Zenith Labs Hydro Whey 750 g',
            'brand_id' => $brand->id,
            'brand_raw' => 'Zenith Labs',
            'ean' => '4099999999990',
            'pack_label' => '750 g',
            'source_count' => 1,
            'fingerprint' => ProposeProductCandidate::fingerprint('Zenith Labs', 'Zenith Labs Hydro Whey 750 g', '750 g'),
        ])
        ->and($candidate->evidence)->toBe(['titles' => ['Zenith Labs Hydro Whey 750 g']])
        ->and($listing->fresh()->only(['product_id', 'match_status', 'current_matching_decision_id']))
        ->toBe(['product_id' => null, 'match_status' => ListingMatchStatus::Unmatched, 'current_matching_decision_id' => null])
        ->and(AuditLog::query()->sole()->only(['action', 'actor_id', 'auditable_type', 'auditable_id']))
        ->toBe(['action' => AuditAction::ProductCandidateProposed->value, 'actor_id' => $user->id, 'auditable_type' => ProductCandidate::class, 'auditable_id' => $candidate->id]);
});

it('deduplicates proposals with the same identity fingerprint across merchants', function () {
    $first = proposableListing();
    $second = proposableListing(['title' => 'ZENITH LABS — Hydro-Whey 750 g!', 'brand_raw' => 'ZENITH LABS', 'pack_raw' => '750 g']);
    $propose = app(ProposeProductCandidate::class);

    $a = $propose->handle($first, Scenario::merchantActor($first), Scenario::at());
    $b = $propose->handle($second, Scenario::merchantActor($second), Scenario::at());
    $again = $propose->handle($first, Scenario::merchantActor($first), Scenario::at());

    expect($b->id)->toBe($a->id)
        ->and($again->id)->toBe($a->id)
        ->and(ProductCandidate::query()->count())->toBe(1)
        ->and(ProductCandidateSource::query()->count())->toBe(2)
        ->and($again->fresh()->source_count)->toBe(2)
        ->and($again->fresh()->evidence['titles'])->toHaveCount(2);
});

it('opens a new proposal for the same identity after the previous one was rejected', function () {
    $listing = proposableListing();
    $propose = app(ProposeProductCandidate::class);
    $first = $propose->handle($listing, Scenario::merchantActor($listing), Scenario::at());
    app(ResolveProductCandidate::class)->reject($first, Scenario::staffActor(), Scenario::at());

    $second = $propose->handle($listing, Scenario::merchantActor($listing), Scenario::at());

    expect($second->id)->not->toBe($first->id)
        ->and($second->status)->toBe(CandidateStatus::Proposed);
});

it('refuses proposals for linked listings, listings without a title and foreign listings', function (string $case) {
    $product = Scenario::product();

    match ($case) {
        'linked' => (function () use ($product) {
            $listing = Scenario::listing(Scenario::autoFacts($product));
            Scenario::match($listing);
            app(ProposeProductCandidate::class)->handle($listing, Scenario::merchantActor($listing), Scenario::at());
        })(),
        'no title' => (function () {
            $listing = proposableListing(['title' => '  ']);
            app(ProposeProductCandidate::class)->handle($listing, Scenario::merchantActor($listing), Scenario::at());
        })(),
    };
})->with(['linked', 'no title'])->throws(MatchDecisionNotAllowed::class);

it('refuses a proposal from another merchant\'s listing', function () {
    $listing = proposableListing();

    app(ProposeProductCandidate::class)->handle($listing, MatchingActor::merchant(User::factory()->create(), Merchant::factory()->create()->id), Scenario::at());
})->throws(ListingOutsideMerchantScope::class);

it('links every unlinked source listing when staff resolve a candidate as an existing product', function () {
    $product = Scenario::product(name: 'Hydro Whey', pack: '750 g');
    $first = proposableListing();
    $second = proposableListing(['title' => 'zenith labs: Hydro Whey (750 g)']);
    $propose = app(ProposeProductCandidate::class);
    $candidate = $propose->handle($first, Scenario::merchantActor($first), Scenario::at());
    $propose->handle($second, Scenario::merchantActor($second), Scenario::at());
    $staff = User::factory()->create();

    $resolved = app(ResolveProductCandidate::class)->linkExisting($candidate, $product->id, MatchingActor::staff($staff), Scenario::at(), note: 'Same product');

    expect($resolved->only(['status', 'linked_product_id', 'reviewed_by_user_id', 'decision_note']))
        ->toBe(['status' => CandidateStatus::MergedExisting, 'linked_product_id' => $product->id, 'reviewed_by_user_id' => $staff->id, 'decision_note' => 'Same product'])
        ->and($first->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::Manual])
        ->and($second->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::Manual])
        ->and(MatchingDecision::query()->pluck('kind')->all())->toBe([MatchDecisionKind::Manual, MatchDecisionKind::Manual])
        ->and(AuditLog::query()->where('action', 'matching.decided')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'product_candidate.resolved')->sole()->after)
        ->toBe(['status' => 'merged_existing', 'linked_product_id' => $product->id, 'linked_listings' => 2]);
});

it('leaves source listings that were linked in the meantime alone', function () {
    $product = Scenario::product(name: 'Hydro Whey', pack: '750 g');
    $other = Scenario::product(name: 'Other Whey');
    $listing = proposableListing();
    $candidate = app(ProposeProductCandidate::class)->handle($listing, Scenario::merchantActor($listing), Scenario::at());
    $listing->update(['product_id' => $other->id, 'match_status' => ListingMatchStatus::Manual]);

    app(ResolveProductCandidate::class)->linkExisting($candidate, $product->id, Scenario::staffActor(), Scenario::at());

    expect($listing->fresh()->product_id)->toBe($other->id)
        ->and(MatchingDecision::query()->count())->toBe(0);
});

it('rejects a candidate without touching its listings', function () {
    $listing = proposableListing();
    $candidate = app(ProposeProductCandidate::class)->handle($listing, Scenario::merchantActor($listing), Scenario::at());

    $rejected = app(ResolveProductCandidate::class)->reject($candidate, Scenario::staffActor(), Scenario::at(), 'Not a supplement');

    expect($rejected->only(['status', 'linked_product_id', 'decision_note']))->toBe(['status' => CandidateStatus::Rejected, 'linked_product_id' => null, 'decision_note' => 'Not a supplement'])
        ->and($listing->fresh()->match_status)->toBe(ListingMatchStatus::Unmatched)
        ->and(AuditLog::query()->where('action', 'product_candidate.resolved')->sole()->before)->toBe(['status' => 'proposed', 'linked_product_id' => null]);
});

it('refuses resolution by merchants, of closed candidates and to inactive products', function (string $case) {
    $listing = proposableListing();
    $candidate = app(ProposeProductCandidate::class)->handle($listing, Scenario::merchantActor($listing), Scenario::at());
    $resolve = app(ResolveProductCandidate::class);

    match ($case) {
        'merchant' => $resolve->reject($candidate, Scenario::merchantActor($listing), Scenario::at()),
        'closed' => $resolve->reject($resolve->reject($candidate, Scenario::staffActor(), Scenario::at()), Scenario::staffActor(), Scenario::at()),
        'inactive product' => $resolve->linkExisting($candidate, Scenario::product(attributes: ['status' => 'retired'])->id, Scenario::staffActor(), Scenario::at()),
    };
})->with(['merchant', 'closed', 'inactive product'])->throws(MatchDecisionNotAllowed::class);
