<?php

use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Platform\Exceptions\AppendOnlyViolation;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedMapping;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MatchingConflict;
use App\Models\MatchingDecision;
use App\Models\MatchingPolicy;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductCandidate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 schema invariants (docs/architecture/phase-2-feeds-matching.md §2):
 * single-active / single-current partial unique indexes, idempotency, tenant
 * isolation through composite foreign keys, encrypted credentials, and the
 * Phase 1 partial indexes and triggers surviving the Phase 2 migrations.
 * Written to run on SQLite and PostgreSQL.
 */

/**
 * Runs a write inside a savepoint and reports whether the database rejected
 * it (PostgreSQL aborts the whole test transaction otherwise).
 */
function phase2Rejects(Closure $write): bool
{
    try {
        DB::transaction($write);
    } catch (QueryException) {
        return true;
    }

    return false;
}

function phase2IndexDefinition(string $index): ?string
{
    $definition = match (DB::getDriverName()) {
        'sqlite' => DB::table('sqlite_master')->where('type', 'index')->where('name', $index)->value('sql'),
        'pgsql' => DB::table('pg_indexes')->where('indexname', $index)->value('indexdef'),
        default => null,
    };

    return is_string($definition) ? $definition : null;
}

/**
 * @return list<string>
 */
function phase2TriggerNames(string $table): array
{
    $names = match (DB::getDriverName()) {
        'sqlite' => DB::table('sqlite_master')->where('type', 'trigger')->where('tbl_name', $table)->pluck('name')->all(),
        'pgsql' => array_column(DB::select('SELECT tgname FROM pg_trigger WHERE tgrelid = ?::regclass AND NOT tgisinternal', [$table]), 'tgname'),
        default => [],
    };
    sort($names);

    return array_map(strval(...), $names);
}

it('seeds exactly one active matching policy with the prototype-v1 values', function () {
    $policy = MatchingPolicy::query()->where('is_active', true)->sole();

    expect($policy->version)->toBe('prototype-v1')
        ->and($policy->algorithm)->toBe('intel-engine-2')
        ->and($policy->weights)->toBe([
            'ean_exact' => 50,
            'brand_exact' => 15,
            'brand_in_title' => 9,
            'title_similarity_scale' => 22,
            'pack_exact' => 10,
            'pack_alternate' => 6,
            'pack_differs' => -12,
            'variant' => 7,
            'ingredient' => 5,
        ])
        ->and($policy->thresholds)->toBe(['auto' => 90, 'review' => 65])
        ->and($policy->levels)->toBe(['exact' => 100, 'very_high' => 90, 'high' => 80, 'possible' => 65])
        ->and($policy->activated_at)->not->toBeNull();
});

it('allows at most one active matching policy', function () {
    expect(phase2Rejects(fn () => MatchingPolicy::factory()->create(['is_active' => true])))->toBeTrue()
        ->and(MatchingPolicy::factory()->count(2)->create())->toHaveCount(2);

    $activated = MatchingPolicy::factory()->active()->create();

    expect(MatchingPolicy::query()->where('is_active', true)->pluck('id')->all())->toBe([$activated->id])
        ->and(phase2IndexDefinition('matching_policies_single_active'))->toContain('WHERE');
});

it('allows one current mapping per source and keeps mapping content immutable', function () {
    $source = FeedSource::factory()->create();
    $current = FeedMapping::factory()->forSource($source)->current()->create();

    expect(phase2Rejects(fn () => FeedMapping::factory()->forSource($source)->current()->create()))->toBeTrue();

    $next = FeedMapping::factory()->forSource($source)->create();
    $current->update(['is_current' => false]);
    $next->update(['is_current' => true, 'activated_at' => now()]);

    expect($next->version)->toBe($current->version + 1)
        ->and($source->currentMapping()->sole()->is($next))->toBeTrue()
        ->and($next->merchant_id)->toBe($source->merchant_id)
        ->and(fn () => $next->update(['field_map' => ['merchant_sku' => 'id']]))->toThrow(AppendOnlyViolation::class)
        ->and(phase2Rejects(fn () => FeedMapping::factory()->forSource($source)->version($next->version)->create()))->toBeTrue();
});

it('allows only one non-terminal run per source', function () {
    $source = FeedSource::factory()->active()->create();
    FeedRun::factory()->forSource($source)->completed()->create();
    FeedRun::factory()->forSource($source)->failed()->create();
    FeedRun::factory()->forSource($source)->cancelled()->create();
    $queued = FeedRun::factory()->forSource($source)->queued()->create();

    foreach (FeedRunStatus::active() as $status) {
        expect(phase2Rejects(fn () => FeedRun::factory()->forSource($source)->state(['status' => $status])->create()))
            ->toBeTrue("a second {$status->value} run was accepted");
    }

    $queued->update(['status' => FeedRunStatus::Completed, 'outcome' => FeedRunOutcome::Unchanged]);
    $next = FeedRun::factory()->forSource($source)->scheduled()->create();

    expect($next->status)->toBe(FeedRunStatus::Queued)
        ->and(FeedRun::factory()->running(FeedRunStatus::Parsing)->create()->status)->toBe(FeedRunStatus::Parsing)
        ->and(FeedRunStatus::activeValues())->toBe(['queued', 'fetching', 'parsing', 'normalizing', 'matching', 'publishing'])
        ->and(phase2IndexDefinition('feed_runs_single_active'))->toContain('WHERE');
});

it('rejects a duplicate idempotency key per source only', function () {
    $source = FeedSource::factory()->create();
    FeedRun::factory()->forSource($source)->completed()->create(['idempotency_key' => 'schedule:1:202609251200']);

    expect(phase2Rejects(fn () => FeedRun::factory()->forSource($source)->create(['idempotency_key' => 'schedule:1:202609251200'])))->toBeTrue()
        ->and(FeedRun::factory()->create(['idempotency_key' => 'schedule:1:202609251200'])->exists)->toBeTrue();
});

it('keeps feed rows inside their merchant through composite foreign keys', function () {
    $source = FeedSource::factory()->create();
    $otherMerchant = Merchant::factory()->create();
    $otherListing = MerchantProduct::factory()->for($otherMerchant)->create();

    expect(phase2Rejects(fn () => FeedRun::factory()->forSource($source)->create(['merchant_id' => $otherMerchant->id])))->toBeTrue()
        ->and(phase2Rejects(fn () => FeedMapping::factory()->forSource($source)->create(['merchant_id' => $otherMerchant->id])))->toBeTrue()
        ->and(phase2Rejects(fn () => MerchantProduct::factory()->fromFeed($source)->create(['merchant_id' => $otherMerchant->id])))->toBeTrue()
        ->and(phase2Rejects(fn () => MatchingDecision::factory()->forListing($otherListing)->create(['merchant_id' => $source->merchant_id])))->toBeTrue()
        ->and(MerchantProduct::factory()->fromFeed($source)->create()->feedSource->is($source))->toBeTrue();
});

it('keeps a listing with matching history from being deleted', function () {
    $decision = MatchingDecision::factory()->create();
    $listing = $decision->merchantProduct;
    $listing->update(['current_matching_decision_id' => $decision->id]);

    expect(phase2Rejects(fn () => DB::table('merchant_products')->where('id', $listing->id)->delete()))->toBeTrue()
        ->and($listing->fresh()->currentDecision->is($decision))->toBeTrue()
        ->and($listing->decisions()->pluck('id')->all())->toBe([$decision->id]);
});

it('stores feed credentials encrypted and never serializes them', function () {
    $source = FeedSource::factory()->withCredentials(['username' => 'shop-user', 'password' => 'hunter2-secret'])->create();
    $raw = (string) DB::table('feed_sources')->where('id', $source->id)->value('credentials');
    $fresh = $source->fresh();

    expect($raw)->not->toBe('')
        ->not->toContain('hunter2-secret')
        ->not->toContain('shop-user')
        ->and($fresh->credentials)->toBe(['username' => 'shop-user', 'password' => 'hunter2-secret'])
        ->and($fresh->hasCredentials())->toBeTrue()
        ->and($fresh->toArray())->not->toHaveKey('credentials')
        ->and($fresh->toJson())->not->toContain('hunter2-secret')->not->toContain($raw)
        ->and(FeedSource::factory()->create()->hasCredentials())->toBeFalse()
        ->and($fresh->getFillable())->not->toContain('credentials');
});

it('keeps the Phase 1 append-only triggers on price_snapshots working', function () {
    $expected = DB::getDriverName() === 'pgsql'
        ? ['price_snapshots_append_only']
        : ['price_snapshots_no_delete', 'price_snapshots_no_update'];
    $offer = Offer::factory()->create();
    $run = FeedRun::factory()->completed()->create();
    DB::table('price_snapshots')->insert([
        'offer_id' => $offer->id,
        'product_id' => $offer->product_id,
        'merchant_id' => $offer->merchant_id,
        'price_minor' => 2999,
        'currency' => 'EUR',
        'reason' => 'first_seen',
        'source' => 'feed',
        'feed_run_id' => $run->id,
        'observed_at' => now(),
    ]);

    expect(phase2TriggerNames('price_snapshots'))->toBe($expected)
        ->and(phase2Rejects(fn () => DB::table('price_snapshots')->update(['price_minor' => 1])))->toBeTrue()
        ->and(phase2Rejects(fn () => DB::table('price_snapshots')->delete()))->toBeTrue()
        ->and(DB::table('price_snapshots')->value('price_minor'))->toBe(2999)
        ->and(phase2IndexDefinition('price_snapshots_feed_run_id_index'))->not->toBeNull();
});

it('indexes the active offers of a product per currency for the anomaly median', function () {
    expect(phase2IndexDefinition('offers_product_currency_active_index'))
        ->toContain('product_id')
        ->toContain('currency')
        ->toContain('WHERE')
        ->toContain('is_active');
});

it('keeps the partial indexes the Phase 2 migrations could have rebuilt', function () {
    expect(phase2IndexDefinition('offers_product_active_index'))->toContain('WHERE')->toContain('is_active')
        ->and(phase2IndexDefinition('merchant_products_review_queue'))->toContain('WHERE')->toContain('match_status')
        ->and(phase2IndexDefinition('feed_mappings_single_current'))->toContain('WHERE')
        ->and(phase2IndexDefinition('matching_conflicts_single_open'))->toContain('WHERE')
        ->and(phase2IndexDefinition('product_candidates_single_proposed'))->toContain('WHERE')
        ->and(phase2IndexDefinition('ranking_versions_single_active'))->toContain('WHERE');
});

it('allows one open conflict per product field and one open candidate per fingerprint', function () {
    $conflict = MatchingConflict::factory()->create();

    expect(phase2Rejects(fn () => MatchingConflict::factory()->create(['product_id' => $conflict->product_id])))->toBeTrue()
        ->and(MatchingConflict::factory()->resolved()->create(['product_id' => $conflict->product_id])->exists)->toBeTrue();

    $candidate = ProductCandidate::factory()->create();

    expect(phase2Rejects(fn () => ProductCandidate::factory()->create(['fingerprint' => $candidate->fingerprint])))->toBeTrue()
        ->and(ProductCandidate::factory()->rejected()->create(['fingerprint' => $candidate->fingerprint])->exists)->toBeTrue();
});

it('keeps feed errors when staged items are pruned', function () {
    $item = FeedItem::factory()->invalid()->create();
    $error = FeedError::factory()->forItem($item)->create();

    $item->delete();

    expect($error->fresh()->feed_item_id)->toBeNull()
        ->and($error->fresh()->row_number)->toBe($item->row_number)
        ->and($error->fresh()->run->is($item->run))->toBeTrue();
});

it('backfills and defaults the new listing, offer and ingredient columns', function () {
    $listing = MerchantProduct::factory()->create()->fresh();
    $offer = Offer::factory()->create()->fresh();
    $product = Product::factory()->create();
    $ingredientId = DB::table('ingredients')->insertGetId(['slug' => 'whey', 'name' => 'Whey', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('ingredient_product')->insert(['product_id' => $product->id, 'ingredient_id' => $ingredientId]);

    expect($listing->match_status)->toBe(ListingMatchStatus::Auto)
        ->and(MerchantProduct::factory()->unmatched()->create()->fresh()->match_status)->toBe(ListingMatchStatus::Unmatched)
        ->and($offer->source)->toBe('feed')
        ->and($offer->deactivated_at)->toBeNull()
        ->and($product->ingredients()->sole()->pivot->is_listed)->toBeTrue();
});
