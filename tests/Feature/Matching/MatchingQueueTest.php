<?php

use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\Actions\ProposeProductCandidate;
use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\MerchantMatchingQueue;
use App\Domain\Matching\Queries\QueueListing;
use App\Domain\Matching\Queries\StaffMatchingQueue;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Matching\MatchingScenario as Scenario;

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    $this->merchantA = Merchant::factory()->create();
    $this->merchantB = Merchant::factory()->create();
    $this->product = Scenario::product();
});

/**
 * @return list<MerchantProduct>
 */
function suggestedListingsFor(Merchant $merchant, int $count): array
{
    $listings = [];

    for ($i = 0; $i < $count; $i++) {
        $listing = Scenario::listing(Scenario::confirmFacts(test()->product), $merchant);
        Scenario::match($listing);
        $listings[] = $listing->fresh();
    }

    return $listings;
}

it('never shows merchant A another merchant\'s listings or decisions', function () {
    $own = suggestedListingsFor($this->merchantA, 2);
    suggestedListingsFor($this->merchantB, 3);
    Scenario::listing(merchant: $this->merchantB);
    $ownUnmatched = Scenario::listing(merchant: $this->merchantA);
    $queue = app(MerchantMatchingQueue::class);

    $suggested = $queue->suggested($this->merchantA->id);
    $unmatched = $queue->unmatched($this->merchantA->id);
    $history = $queue->history($this->merchantA->id);

    expect(collect($suggested->items())->pluck('listingId')->sort()->values()->all())->toBe(collect($own)->pluck('id')->sort()->values()->all())
        ->and(collect($suggested->items())->pluck('merchantId')->unique()->all())->toBe([$this->merchantA->id])
        ->and(collect($unmatched->items())->pluck('listingId')->all())->toBe([$ownUnmatched->id])
        ->and($history->total())->toBe(2)
        ->and(collect($history->items())->pluck('merchantId')->unique()->all())->toBe([$this->merchantA->id]);
});

it('exposes the suggested product, score and evidence parts of each queued listing', function () {
    [$listing] = suggestedListingsFor($this->merchantA, 1);

    /** @var QueueListing $item */
    $item = app(MerchantMatchingQueue::class)->suggested($this->merchantA->id)->items()[0];

    expect($item->listingId)->toBe($listing->id)
        ->and($item->status)->toBe(ListingMatchStatus::Suggested)
        ->and($item->linkedProductId)->toBeNull()
        ->and($item->product?->id)->toBe($this->product->id)
        ->and($item->product?->brandName)->toBe('Acme Nutrition')
        ->and($item->score)->toBe($listing->match_score)
        ->and($item->decisionKind)->toBe(MatchDecisionKind::Suggested)
        ->and($item->parts[0]['signal'])->toBe('ean_exact')
        ->and($item->parts[0]['label'])->toBe('EAN exact match');
});

it('paginates the merchant queue', function () {
    suggestedListingsFor($this->merchantA, 5);
    $queue = app(MerchantMatchingQueue::class);

    $first = $queue->suggested($this->merchantA->id, perPage: 2, page: 1);
    $last = $queue->suggested($this->merchantA->id, perPage: 2, page: 3);

    expect($first->total())->toBe(5)
        ->and($first->lastPage())->toBe(3)
        ->and($first->items())->toHaveCount(2)
        ->and($last->items())->toHaveCount(1)
        ->and(array_intersect(collect($first->items())->pluck('listingId')->all(), collect($last->items())->pluck('listingId')->all()))->toBe([]);
});

it('loads a queue page with a constant number of queries', function () {
    suggestedListingsFor($this->merchantA, 2);
    $queue = app(MerchantMatchingQueue::class);
    $count = function () use ($queue): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $queue->suggested($this->merchantA->id);
        $queue->history($this->merchantA->id);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $small = $count();

    suggestedListingsFor($this->merchantA, 6);

    expect($count())->toBe($small);
});

it('shows staff every merchant, filtered by merchant, status and score', function () {
    suggestedListingsFor($this->merchantA, 2);
    suggestedListingsFor($this->merchantB, 1);
    $unmatched = Scenario::listing(merchant: $this->merchantB);
    $held = Scenario::listing(Scenario::autoFacts($this->product), $this->merchantB);
    Scenario::match($held, Scenario::context(Scenario::blocking($this->product->id)));
    $queue = app(StaffMatchingQueue::class);

    expect($queue->listings()->total())->toBe(5)
        ->and($queue->listings(merchantId: $this->merchantB->id)->total())->toBe(3)
        ->and(collect($queue->listings(status: ListingMatchStatus::Unmatched)->items())->pluck('listingId')->all())->toBe([$unmatched->id])
        ->and(collect($queue->listings(status: ListingMatchStatus::ComplianceHold)->items())->pluck('listingId')->all())->toBe([$held->id])
        ->and($queue->listings(minScore: 65, maxScore: 89)->total())->toBe(3)
        ->and($queue->listings(minScore: 90)->total())->toBe(1)
        ->and($queue->history()->total())->toBe(4);
});

it('lists open conflicts and open candidates with their source counts for staff', function () {
    $held = Scenario::listing(Scenario::autoFacts($this->product), $this->merchantA);
    Scenario::match($held, Scenario::context(Scenario::blocking($this->product->id)));
    $facts = ['title' => 'Zenith Hydro Whey', 'brand_raw' => 'Zenith', 'pack_raw' => '750 g'];
    $a = Scenario::listing($facts, $this->merchantA);
    $b = Scenario::listing($facts, $this->merchantB);
    $propose = app(ProposeProductCandidate::class);
    $propose->handle($a, Scenario::merchantActor($a), Scenario::at());
    $propose->handle($b, Scenario::merchantActor($b), Scenario::at());
    $queue = app(StaffMatchingQueue::class);

    $conflicts = $queue->openConflicts();
    $candidates = $queue->openCandidates();

    expect($conflicts->total())->toBe(1)
        ->and($conflicts->items()[0]->kind)->toBe(ConflictKind::ComplianceHold)
        ->and($conflicts->items()[0]->product?->id)->toBe($this->product->id)
        ->and($conflicts->items()[0]->values[0]['merchant_product_id'])->toBe($held->id)
        ->and($queue->openConflicts(ConflictKind::FieldConflict)->total())->toBe(0)
        ->and($candidates->total())->toBe(1)
        ->and($candidates->items()[0]->sourceCount)->toBe(2)
        ->and($candidates->items()[0]->proposedName)->toBe('Zenith Hydro Whey');
});

it('lists a listing\'s decision history newest first with products', function () {
    [$listing] = suggestedListingsFor($this->merchantA, 1);
    app(DecideMatch::class)->confirm($listing, Scenario::merchantActor($listing), Scenario::at());

    $history = app(MerchantMatchingQueue::class)->history($this->merchantA->id, $listing->id);

    expect(collect($history->items())->pluck('kind')->all())->toBe([MatchDecisionKind::Manual, MatchDecisionKind::Suggested])
        ->and($history->items()[0]->product?->id)->toBe($this->product->id)
        ->and($history->items()[0]->supersedesId)->toBe($history->items()[1]->id)
        ->and($history->items()[0]->merchantSku)->toBe($listing->merchant_sku);
});
