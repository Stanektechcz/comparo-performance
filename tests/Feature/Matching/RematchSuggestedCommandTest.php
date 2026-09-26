<?php

use App\Console\Commands\MatchingRematchSuggestedCommand;
use App\Domain\Accounts\Authorization\StaffRole;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Matching\Actions\MatchListing;
use App\Domain\Matching\Actions\RematchSuggestedListings;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\FeedSource;
use App\Models\MatchingDecision;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Feature\Matching\MatchingScenario as Scenario;

/*
 * BACKLOG F-06: listings demoted to `suggested` while matching-auto-publish
 * was off are re-matched by an audited staff operation.
 */

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
});

/**
 * An auto-bucket listing matched while auto-publish was off (demoted to suggested).
 */
function demotedListing(Product $product, array $attributes = []): MerchantProduct
{
    config(['features.matching-auto-publish' => false]);
    $listing = Scenario::listing(Scenario::autoFacts($product));
    $listing->update($attributes);
    Scenario::match($listing);
    config(['features.matching-auto-publish' => true]);

    return $listing->fresh();
}

it('relinks demoted listings, leaves ordinary suggestions alone and audits each change', function () {
    $product = Scenario::product();
    $demoted = demotedListing($product);
    $confirm = Scenario::listing(Scenario::confirmFacts(Scenario::product('Other Brand', 'Casein', '1 kg')));
    Scenario::match($confirm);
    $decisionsBefore = MatchingDecision::query()->where('merchant_product_id', $confirm->id)->count();

    $this->artisan('comparo:matching:rematch-suggested')
        ->expectsOutputToContain('Re-matched 1 demoted listings: 1 linked, 0 held, 0 still suggested, 0 unmatched.')
        ->assertSuccessful();

    $decision = MatchingDecision::query()->findOrFail($demoted->fresh()->current_matching_decision_id);
    $audit = AuditLog::query()->sole();
    expect($demoted->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::Auto])
        ->and($decision->reason)->toBe(MatchListing::REASON_FORCED)
        ->and($confirm->fresh()->match_status)->toBe(ListingMatchStatus::Suggested)
        ->and(MatchingDecision::query()->where('merchant_product_id', $confirm->id)->count())->toBe($decisionsBefore)
        ->and($audit->only(['action', 'actor_type', 'actor_id', 'auditable_id']))
        ->toBe(['action' => AuditAction::MatchingRematched->value, 'actor_type' => 'system', 'actor_id' => null, 'auditable_id' => $demoted->id])
        ->and($audit->before)->toBe(['product_id' => null, 'match_status' => 'suggested'])
        ->and($audit->after)->toMatchArray([
            'product_id' => $product->id,
            'match_status' => 'auto',
            'operation' => RematchSuggestedListings::OPERATION,
            'decision_id' => $decision->id,
        ])
        ->and($audit->after)->not->toHaveKey(AuditLogger::SYSTEM_COMPONENT_KEY)
        ->and($audit->actor_component)->toBe(MatchingRematchSuggestedCommand::AUDIT_COMPONENT);
});

it('only counts the demoted listings on a dry run', function () {
    $demoted = demotedListing(Scenario::product());
    $decisions = MatchingDecision::query()->count();

    $this->artisan('comparo:matching:rematch-suggested', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: 1 demoted suggested listings would be re-matched.')
        ->assertSuccessful();

    expect($demoted->fresh()->match_status)->toBe(ListingMatchStatus::Suggested)
        ->and(MatchingDecision::query()->count())->toBe($decisions)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('refuses to run while auto-publish is still off', function () {
    $demoted = demotedListing(Scenario::product());
    config(['features.matching-auto-publish' => false]);

    $this->artisan('comparo:matching:rematch-suggested')
        ->expectsOutputToContain('Enable the matching-auto-publish feature')
        ->assertFailed();

    expect($demoted->fresh()->match_status)->toBe(ListingMatchStatus::Suggested)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('limits the operation to one merchant', function () {
    $mine = demotedListing(Scenario::product());
    $theirs = demotedListing(Scenario::product('Other Brand', 'Casein', '1 kg'));

    $this->artisan('comparo:matching:rematch-suggested', ['--merchant' => $mine->merchant_id])->assertSuccessful();

    expect($mine->fresh()->match_status)->toBe(ListingMatchStatus::Auto)
        ->and($theirs->fresh()->match_status)->toBe(ListingMatchStatus::Suggested);
});

it('rejects an unknown merchant', function () {
    $this->artisan('comparo:matching:rematch-suggested', ['--merchant' => 999999])
        ->expectsOutputToContain('existing merchant')
        ->assertExitCode(2);
});

it('holds a product blocked in the listing feed market', function () {
    $country = Country::factory()->code('DE')->create();
    $product = Scenario::product();
    ProductComplianceRule::factory()->status(ComplianceStatus::NotAllowed)->create(['product_id' => $product->id, 'country_id' => $country->id]);
    $merchant = Merchant::factory()->create();
    $feed = FeedSource::factory()->create(['merchant_id' => $merchant->id, 'country_id' => $country->id]);
    $demoted = demotedListing($product, ['merchant_id' => $merchant->id, 'feed_source_id' => $feed->id]);

    $this->artisan('comparo:matching:rematch-suggested')
        ->expectsOutputToContain('0 linked, 1 held')
        ->assertSuccessful();

    expect($demoted->fresh()->only(['product_id', 'match_status']))->toBe(['product_id' => $product->id, 'match_status' => ListingMatchStatus::ComplianceHold]);
});

it('attributes the operation to a staff member who may rematch listings', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $staff = tap(User::factory()->create(), fn (User $user) => $user->assignRole(StaffRole::MerchantManager->value));
    demotedListing(Scenario::product());

    $this->artisan('comparo:matching:rematch-suggested', ['--staff' => $staff->id])->assertSuccessful();

    expect(AuditLog::query()->sole()->only(['actor_type', 'actor_id']))->toBe(['actor_type' => 'user', 'actor_id' => $staff->id]);
});

it('refuses a --staff user without the rematch permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $demoted = demotedListing(Scenario::product());

    $this->artisan('comparo:matching:rematch-suggested', ['--staff' => User::factory()->create()->id])
        ->expectsOutputToContain('must be the id of a staff user')
        ->assertExitCode(2);

    expect($demoted->fresh()->match_status)->toBe(ListingMatchStatus::Suggested);
});

it('re-matches listings across several chunks', function () {
    $listings = collect(range(1, 3))->map(fn (int $i) => demotedListing(Scenario::product('Brand '.$i, 'Whey', '900 g')));

    $summary = app(RematchSuggestedListings::class)->handle(
        AuditActor::system('test'),
        Scenario::at(),
        fn () => null,
        chunk: 2,
    );

    expect($summary->eligible)->toBe(3)
        ->and($summary->linked)->toBe(3)
        ->and($listings->map(fn (MerchantProduct $listing) => $listing->fresh()->match_status)->unique()->all())->toBe([ListingMatchStatus::Auto]);
});
