<?php

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Accounts\Authorization\StaffRole;
use App\Domain\Catalog\ProductStatus;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Matching\CandidateStatus;
use App\Domain\Matching\ConflictStatus;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\FeedSource;
use App\Models\MatchingConflict;
use App\Models\MatchingDecision;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use App\Models\ProductComplianceRule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Matching\MatchingScenario as Scenario;

const PLANTED_FEED_PASSWORD = 'planted-feed-password-7Qx9';
const PLANTED_FEED_TOKEN = 'planted-url-token-3Kd2';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo('2026-09-25 10:00:00');
    $this->country = Country::factory()->code('DE')->create();
});

function adminStaff(StaffRole $role = StaffRole::MerchantManager): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role->value));
}

/**
 * @param  list<Permission>  $permissions
 */
function adminStaffWith(array $permissions): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->givePermissionTo(array_map(
        static fn (Permission $permission): string => $permission->value,
        $permissions,
    )));
}

function adminGermanFeed(?Merchant $merchant = null): FeedSource
{
    return FeedSource::factory()
        ->withCredentials(['username' => 'feed-user', 'password' => PLANTED_FEED_PASSWORD])
        ->create([
            'merchant_id' => $merchant->id ?? Merchant::factory(),
            'country_id' => test()->country->id,
            'url' => 'https://feeds.example.com/listings.csv?token='.PLANTED_FEED_TOKEN,
        ]);
}

function adminAllowIn(Product $product, ComplianceStatus $status = ComplianceStatus::Allowed): void
{
    ProductComplianceRule::factory()->status($status)->create(['product_id' => $product->id, 'country_id' => test()->country->id]);
}

/**
 * A listing of a German feed in the confirm bucket for the product.
 */
function adminSuggestedListing(Product $product, ?Merchant $merchant = null): MerchantProduct
{
    $listing = MerchantProduct::factory()->fromFeed(adminGermanFeed($merchant))->unmatched()->create([
        ...Scenario::confirmFacts($product),
        'variant_raw' => 'Vanilla',
        'raw_payload' => ['sku' => 'RAW-1', 'title' => 'Protein powder vanilla', 'price' => '24.90'],
    ]);
    Scenario::match($listing);

    return $listing->fresh();
}

/**
 * A listing of a German feed auto-linked to the product (with its offer).
 */
function adminLinkedListing(Product $product): MerchantProduct
{
    $offer = Offer::factory()->create(['product_id' => $product->id]);
    $listing = $offer->merchantProduct;
    $listing->update(['feed_source_id' => adminGermanFeed($listing->merchant)->id, ...Scenario::autoFacts($product)]);
    Scenario::match($listing);

    return $listing->fresh();
}

/**
 * @return array{0: string, 1: string}
 */
function adminMatchingRoute(string $route): array
{
    $product = Scenario::product();

    return match ($route) {
        'queue' => ['get', route('admin.catalogue.matching.index')],
        'listing' => ['get', route('admin.catalogue.matching.listings.show', adminSuggestedListing($product))],
        'decision' => ['post', route('admin.catalogue.matching.listings.decision', adminSuggestedListing($product))],
        'rematch' => ['post', route('admin.catalogue.matching.listings.rematch', adminLinkedListing($product))],
        'candidate' => ['post', route('admin.catalogue.matching.candidates.resolve', ProductCandidate::factory()->create())],
        'conflict' => ['post', route('admin.catalogue.matching.conflicts.resolve', MatchingConflict::factory()->create())],
        'search' => ['get', route('admin.catalogue.products.search', ['q' => 'whey'])],
    };
}

dataset('admin matching routes', ['queue', 'listing', 'decision', 'rematch', 'candidate', 'conflict', 'search']);

describe('access', function () {
    it('sends guests to the login page', function (string $route) {
        [$method, $url] = adminMatchingRoute($route);

        $this->{$method}($url)->assertRedirect(route('login'));
    })->with('admin matching routes');

    it('forbids verified non-staff users and merchant owners', function (string $route) {
        [$method, $url] = adminMatchingRoute($route);
        $owner = User::factory()->create();
        $owner->merchants()->attach(Merchant::factory()->create(), ['role' => 'owner']);

        $this->actingAs(User::factory()->create())->{$method}($url)->assertForbidden();
        $this->actingAs($owner)->{$method}($url)->assertForbidden();
    })->with('admin matching routes');

    it('forbids staff without matching.review', function (string $route) {
        [$method, $url] = adminMatchingRoute($route);

        $this->actingAs(adminStaff(StaffRole::ComplianceManager))->{$method}($url)->assertForbidden();
        $this->actingAs(adminStaff(StaffRole::Analyst))->{$method}($url)->assertForbidden();
    })->with('admin matching routes');

    it('sends unverified staff to email verification', function () {
        $staff = tap(User::factory()->unverified()->create(), fn (User $user) => $user->assignRole(StaffRole::SuperAdmin->value));

        $this->actingAs($staff)->get(route('admin.catalogue.matching.index'))->assertRedirect(route('verification.notice'));
    });

    it('forbids a rematch without offers.manage', function () {
        $listing = adminLinkedListing(Scenario::product());
        $target = Scenario::product(name: 'Clear Whey', pack: '500 g');
        $reviewer = adminStaffWith([Permission::AccessStaffConsole, Permission::ReviewMatching]);

        $this->actingAs($reviewer)
            ->post(route('admin.catalogue.matching.listings.rematch', $listing), ['product_id' => $target->id, 'note' => 'Wrong pack'])
            ->assertForbidden();

        expect(MatchingDecision::query()->where('kind', MatchDecisionKind::Rematch)->exists())->toBeFalse();
    });

    it('forbids closing a compliance-hold conflict without compliance.manage', function () {
        $conflict = MatchingConflict::factory()->complianceHold()->create();

        $this->actingAs(adminStaff(StaffRole::MerchantManager))
            ->post(route('admin.catalogue.matching.conflicts.resolve', $conflict), ['resolution' => 'resolved', 'note' => 'Checked'])
            ->assertForbidden();

        expect($conflict->fresh()->status)->toBe(ConflictStatus::Open);
    });
});

describe('queue', function () {
    it('shows staff the listings waiting for review with scores, levels and suggestions', function () {
        $product = Scenario::product();
        $listing = adminSuggestedListing($product);
        $unmatched = Scenario::listing();
        adminLinkedListing(Scenario::product(name: 'Casein', pack: '1 kg'));

        $this->actingAs(adminStaff())
            ->get(route('admin.catalogue.matching.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalogue/matching/index')
                ->where('tab', 'listings')
                ->has('tabs', 4)
                ->has('listings.data', 2)
                ->where('listings.meta.total', 2)
                ->where('conflicts', null)
                ->where('can.rematch', true)
                ->where('can.resolveComplianceHolds', false)
                ->where('listings.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === collect([$listing->id, $unmatched->id])->sort()->values()->all())
                ->has('filterOptions.statuses', count(ListingMatchStatus::cases())));

        $this->actingAs(adminStaff())
            ->get(route('admin.catalogue.matching.index', ['status' => 'suggested']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('listings.data', 1)
                ->where('listings.data.0.id', $listing->id)
                ->where('listings.data.0.status.value', 'suggested')
                ->where('listings.data.0.score', $listing->match_score)
                ->where('listings.data.0.level.value', fn ($level) => is_string($level))
                ->where('listings.data.0.product.id', $product->id)
                ->where('listings.data.0.product.brand', 'Acme Nutrition')
                ->where('listings.data.0.decision.kind.value', 'suggested'));
    });

    it('filters listings by merchant, status and score range', function () {
        $merchant = Merchant::factory()->create();
        $mine = adminSuggestedListing(Scenario::product(), $merchant);
        adminSuggestedListing(Scenario::product(name: 'Casein', pack: '1 kg'));
        $low = Scenario::listing(merchant: $merchant);
        $staff = adminStaff();

        $ids = fn (array $query): array => collect($this->actingAs($staff)
            ->get(route('admin.catalogue.matching.index', $query))
            ->assertOk()
            ->inertiaProps('listings.data'))->pluck('id')->all();

        expect($ids(['merchant' => $merchant->id]))->toEqualCanonicalizing([$mine->id, $low->id])
            ->and($ids(['merchant' => $merchant->id, 'status' => 'unmatched']))->toBe([$low->id])
            ->and($ids(['minScore' => $mine->match_score, 'maxScore' => 100, 'merchant' => $merchant->id]))->toBe([$mine->id])
            ->and($ids(['maxScore' => 10]))->toBe([]);
    });

    it('rejects an inverted score range', function () {
        $this->actingAs(adminStaff())
            ->from(route('admin.catalogue.matching.index'))
            ->get(route('admin.catalogue.matching.index', ['minScore' => 80, 'maxScore' => 20]))
            ->assertRedirect(route('admin.catalogue.matching.index'))
            ->assertSessionHasErrors('maxScore');
    });

    it('lists open conflicts, open proposals and the decision history on their tabs', function () {
        $conflict = MatchingConflict::factory()->complianceHold()->create();
        MatchingConflict::factory()->resolved()->create();
        $candidate = ProductCandidate::factory()->create(['proposed_name' => 'Zenith Hydro Whey']);
        ProductCandidate::factory()->rejected()->create();
        $listing = adminSuggestedListing(Scenario::product());
        $staff = adminStaff(StaffRole::SuperAdmin);

        $this->actingAs($staff)->get(route('admin.catalogue.matching.index', ['tab' => 'conflicts']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'conflicts')
                ->where('listings', null)
                ->has('conflicts.data', 1)
                ->where('conflicts.data.0.id', $conflict->id)
                ->where('conflicts.data.0.kind.value', 'compliance_hold')
                ->where('conflicts.data.0.canResolve', true));

        $this->actingAs($staff)->get(route('admin.catalogue.matching.index', ['tab' => 'candidates']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('candidates.data', 1)
                ->where('candidates.data.0.id', $candidate->id)
                ->where('candidates.data.0.proposedName', 'Zenith Hydro Whey'));

        $this->actingAs($staff)->get(route('admin.catalogue.matching.index', ['tab' => 'history']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history.data', 1)
                ->where('history.data.0.listingId', $listing->id)
                ->where('history.data.0.kind.value', 'suggested')
                ->where('history.data.0.decidedBy', null));
    });

    it('marks compliance-hold conflicts as not resolvable without compliance.manage', function () {
        MatchingConflict::factory()->complianceHold()->create();

        $this->actingAs(adminStaff())->get(route('admin.catalogue.matching.index', ['tab' => 'conflicts']))
            ->assertInertia(fn (Assert $page) => $page->where('conflicts.data.0.canResolve', false));
    });
});

describe('evidence', function () {
    it('shows the listing facts, live candidates with points, decision history and allowed actions', function () {
        $product = Scenario::product();
        $listing = adminSuggestedListing($product);

        $this->actingAs(adminStaff())
            ->get(route('admin.catalogue.matching.listings.show', $listing))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/catalogue/matching/show')
                ->where('listing.id', $listing->id)
                ->where('listing.sku', $listing->merchant_sku)
                ->where('listing.brandRaw', 'Acme Nutrition')
                ->where('listing.variantRaw', 'Vanilla')
                ->where('listing.status.value', 'suggested')
                ->where('listing.market.code', 'DE')
                ->where('listing.feedSource.id', $listing->feed_source_id)
                ->missing('listing.feedSource.url')
                ->where('listing.rawPayload.truncated', false)
                ->where('listing.rawPayload.fields.0', ['key' => 'sku', 'value' => 'RAW-1'])
                ->where('currentDecision.kind.value', 'suggested')
                ->where('currentDecision.product.id', $product->id)
                ->where('candidates.0.product.id', $product->id)
                ->where('candidates.0.score', $listing->match_score)
                ->where('candidates.0.parts.0.signal', 'ean_exact')
                ->where('candidates.0.parts.0.label', 'EAN exact match')
                ->where('candidates.0.parts.0.points', fn ($points) => $points > 0)
                ->has('history', 1)
                ->where('historyTotal', 1)
                ->where('previewUnavailable', null)
                ->where('can.rematch', true)
                ->where('actions', ['confirm' => true, 'choose' => true, 'reject' => true, 'rematch' => false]));
    });

    it('caps the raw payload shown to staff', function () {
        $listing = Scenario::listing(['raw_payload' => ['description' => str_repeat('x', 2000)]]);

        $this->actingAs(adminStaff())
            ->get(route('admin.catalogue.matching.listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page
                ->where('listing.rawPayload.truncated', true)
                ->where('listing.rawPayload.fields.0.value', fn (string $value) => mb_strlen($value) === 501));
    });

    it('only links merchant urls with an http(s) scheme', function (string $url, ?string $expected) {
        $listing = Scenario::listing(['url' => $url]);

        $this->actingAs(adminStaff())
            ->get(route('admin.catalogue.matching.listings.show', $listing))
            ->assertInertia(fn (Assert $page) => $page->where('listing.url', $expected));
    })->with([
        'https' => ['https://shop.example.com/whey', 'https://shop.example.com/whey'],
        'javascript' => ['javascript:alert(1)', null],
        'data' => ['data:text/html,hi', null],
    ]);

    it('never exposes feed credentials or the feed url', function () {
        $listing = adminSuggestedListing(Scenario::product());
        $staff = adminStaff(StaffRole::SuperAdmin);

        $this->actingAs($staff)->get(route('admin.catalogue.matching.listings.show', $listing))
            ->assertOk()
            ->assertSee('RAW-1')
            ->assertDontSee(PLANTED_FEED_PASSWORD)
            ->assertDontSee(PLANTED_FEED_TOKEN);

        foreach (['listings', 'conflicts', 'candidates', 'history'] as $tab) {
            $this->actingAs($staff)->get(route('admin.catalogue.matching.index', ['tab' => $tab]))
                ->assertOk()
                ->assertDontSee(PLANTED_FEED_PASSWORD)
                ->assertDontSee(PLANTED_FEED_TOKEN);
        }
    });
});

describe('decisions', function () {
    it('confirms a suggestion as the staff actor and audits it', function () {
        $product = Scenario::product();
        adminAllowIn($product);
        $listing = adminSuggestedListing($product);
        $staff = adminStaff();

        $this->actingAs($staff)
            ->from(route('admin.catalogue.matching.listings.show', $listing))
            ->post(route('admin.catalogue.matching.listings.decision', $listing), ['action' => 'confirm', 'note' => 'Same EAN'])
            ->assertRedirect(route('admin.catalogue.matching.listings.show', $listing))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $decision = MatchingDecision::query()->latest('id')->firstOrFail();

        expect($decision->only(['kind', 'product_id', 'decided_by_user_id', 'note']))
            ->toBe(['kind' => MatchDecisionKind::Manual, 'product_id' => $product->id, 'decided_by_user_id' => $staff->id, 'note' => 'Same EAN'])
            ->and($listing->fresh()->only(['match_status', 'product_id']))->toBe(['match_status' => ListingMatchStatus::Manual, 'product_id' => $product->id])
            ->and(AuditLog::query()->sole()->only(['action', 'actor_id', 'actor_type', 'auditable_id']))
            ->toBe(['action' => AuditAction::MatchingDecided->value, 'actor_id' => $staff->id, 'actor_type' => 'user', 'auditable_id' => $listing->id]);
    });

    it('holds a confirmed link when the product is blocked in the listing market', function (ComplianceStatus $status) {
        $product = Scenario::product();
        adminAllowIn($product, $status);
        $listing = adminSuggestedListing($product);

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.listings.decision', $listing), ['action' => 'confirm'])
            ->assertSessionHasNoErrors();

        expect($listing->fresh()->match_status)->toBe(ListingMatchStatus::ComplianceHold);
    })->with([
        'not allowed' => [ComplianceStatus::NotAllowed],
        'prescription only' => [ComplianceStatus::PrescriptionOnly],
    ]);

    // A-19: only blocked products are held. Unreviewed products stay linked; the
    // serialization gate shows their prices without purchase links (ADR-0007).
    it('does not hold a link for a product not yet reviewed in the listing market', function () {
        $product = Scenario::product();
        $listing = adminSuggestedListing($product);

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.listings.decision', $listing), ['action' => 'confirm'])
            ->assertSessionHasNoErrors();

        expect($listing->fresh()->match_status)->toBe(ListingMatchStatus::Manual);
    });

    it('leaves a listing without a known market to the per-market serialization gate', function () {
        $product = Scenario::product();
        adminAllowIn($product);
        $listing = Scenario::listing(Scenario::confirmFacts($product));

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.listings.decision', $listing), ['action' => 'choose', 'product_id' => $product->id])
            ->assertSessionHasNoErrors();

        expect($listing->fresh()->match_status)->toBe(ListingMatchStatus::Manual);
    });

    it('chooses another active product', function () {
        $suggested = Scenario::product();
        $chosen = Scenario::product(name: 'Clear Whey', pack: '500 g');
        adminAllowIn($chosen);
        $listing = adminSuggestedListing($suggested);
        $staff = adminStaff();

        $this->actingAs($staff)
            ->post(route('admin.catalogue.matching.listings.decision', $listing), ['action' => 'choose', 'product_id' => $chosen->id])
            ->assertSessionHasNoErrors();

        expect($listing->fresh()->only(['match_status', 'product_id']))->toBe(['match_status' => ListingMatchStatus::Manual, 'product_id' => $chosen->id])
            ->and(AuditLog::query()->sole()->actor_id)->toBe($staff->id);
    });

    it('rejects unknown, inactive or missing products as validation errors', function (array $payload) {
        $listing = adminSuggestedListing(Scenario::product());
        $payload = array_map(fn ($value) => $value instanceof Closure ? $value() : $value, $payload);

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.listings.decision', $listing), $payload)
            ->assertSessionHasErrors('product_id');

        expect(MatchingDecision::query()->count())->toBe(1)
            ->and(AuditLog::query()->count())->toBe(0);
    })->with([
        'unknown id' => [['action' => 'choose', 'product_id' => 999999]],
        'inactive product' => [['action' => 'choose', 'product_id' => fn () => Scenario::product(name: 'Old', attributes: ['status' => ProductStatus::Retired])->id]],
        'missing id' => [['action' => 'choose']],
    ]);

    it('rejects a suggestion and audits it', function () {
        $listing = adminSuggestedListing(Scenario::product());
        $staff = adminStaff();

        $this->actingAs($staff)
            ->post(route('admin.catalogue.matching.listings.decision', $listing), ['action' => 'reject', 'note' => 'Different flavour'])
            ->assertSessionHasNoErrors();

        expect(MatchingDecision::query()->latest('id')->firstOrFail()->only(['kind', 'decided_by_user_id']))
            ->toBe(['kind' => MatchDecisionKind::Rejected, 'decided_by_user_id' => $staff->id])
            ->and($listing->fresh()->match_status)->toBe(ListingMatchStatus::Unmatched)
            ->and(AuditLog::query()->sole()->only(['action', 'actor_id']))
            ->toBe(['action' => AuditAction::MatchingDecided->value, 'actor_id' => $staff->id]);
    });

    it('turns a refused decision into a validation error, never a 500', function () {
        $listing = Scenario::listing();

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.listings.decision', $listing), ['action' => 'confirm'])
            ->assertRedirect()
            ->assertSessionHasErrors('decision');

        expect(MatchingDecision::query()->count())->toBe(0)
            ->and(AuditLog::query()->count())->toBe(0);
    });

    it('relinks a linked listing as the staff actor and audits the rematch', function () {
        $listing = adminLinkedListing(Scenario::product());
        $target = Scenario::product(name: 'Clear Whey', pack: '500 g');
        adminAllowIn($target);
        $staff = adminStaff();

        $this->actingAs($staff)
            ->post(route('admin.catalogue.matching.listings.rematch', $listing), ['product_id' => $target->id, 'note' => 'Wrong pack size'])
            ->assertSessionHasNoErrors();

        expect(MatchingDecision::query()->latest('id')->firstOrFail()->only(['kind', 'product_id', 'decided_by_user_id', 'note']))
            ->toBe(['kind' => MatchDecisionKind::Rematch, 'product_id' => $target->id, 'decided_by_user_id' => $staff->id, 'note' => 'Wrong pack size'])
            ->and(AuditLog::query()->where('action', AuditAction::MatchingRematched->value)->sole()->actor_id)->toBe($staff->id);
    });

    it('requires a note and a different product for a rematch', function () {
        $product = Scenario::product();
        $listing = adminLinkedListing($product);

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.listings.rematch', $listing), ['product_id' => $product->id])
            ->assertSessionHasErrors('note');

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.listings.rematch', $listing), ['product_id' => $product->id, 'note' => 'Same'])
            ->assertSessionHasErrors('decision');
    });
});

describe('candidates and conflicts', function () {
    it('links a proposal to an existing product and audits it', function () {
        $product = Scenario::product();
        adminAllowIn($product);
        $candidate = ProductCandidate::factory()->create();
        $source = MerchantProduct::factory()->fromFeed(adminGermanFeed())->unmatched()->create();
        ProductCandidateSource::factory()->forListing($source)->create(['product_candidate_id' => $candidate->id]);
        $staff = adminStaff();

        $this->actingAs($staff)
            ->post(route('admin.catalogue.matching.candidates.resolve', $candidate), ['action' => 'link_existing', 'product_id' => $product->id])
            ->assertSessionHasNoErrors();

        expect($candidate->fresh()->only(['status', 'linked_product_id', 'reviewed_by_user_id']))
            ->toBe(['status' => CandidateStatus::MergedExisting, 'linked_product_id' => $product->id, 'reviewed_by_user_id' => $staff->id])
            ->and($source->fresh()->only(['match_status', 'product_id']))->toBe(['match_status' => ListingMatchStatus::Manual, 'product_id' => $product->id])
            ->and(MatchingDecision::query()->sole()->decided_by_user_id)->toBe($staff->id)
            ->and(AuditLog::query()->pluck('action')->sort()->values()->all())
            ->toBe([AuditAction::MatchingDecided->value, AuditAction::ProductCandidateResolved->value]);
    });

    it('rejects a proposal and refuses a closed one', function () {
        $candidate = ProductCandidate::factory()->create();
        $staff = adminStaff();

        $this->actingAs($staff)
            ->post(route('admin.catalogue.matching.candidates.resolve', $candidate), ['action' => 'reject', 'note' => 'Not sports nutrition'])
            ->assertSessionHasNoErrors();

        expect($candidate->fresh()->status)->toBe(CandidateStatus::Rejected)
            ->and(AuditLog::query()->sole()->only(['action', 'actor_id']))
            ->toBe(['action' => AuditAction::ProductCandidateResolved->value, 'actor_id' => $staff->id]);

        $this->actingAs($staff)
            ->post(route('admin.catalogue.matching.candidates.resolve', $candidate), ['action' => 'reject'])
            ->assertSessionHasErrors('decision');
    });

    it('closes a field conflict with a note and audits it', function () {
        $conflict = MatchingConflict::factory()->create();
        $staff = adminStaff();

        $this->actingAs($staff)
            ->post(route('admin.catalogue.matching.conflicts.resolve', $conflict), ['resolution' => 'resolved', 'note' => 'Brand confirmed', 'resolved_value' => '4006040123456'])
            ->assertSessionHasNoErrors();

        expect($conflict->fresh()->only(['status', 'resolved_by_user_id', 'resolution_note', 'resolved_value']))
            ->toBe(['status' => ConflictStatus::Resolved, 'resolved_by_user_id' => $staff->id, 'resolution_note' => 'Brand confirmed', 'resolved_value' => '4006040123456'])
            ->and(AuditLog::query()->sole()->only(['action', 'actor_id']))
            ->toBe(['action' => AuditAction::MatchingConflictResolved->value, 'actor_id' => $staff->id]);
    });

    it('lets compliance managers with matching.review close a compliance-hold conflict', function () {
        $conflict = MatchingConflict::factory()->complianceHold()->create();

        $this->actingAs(adminStaff(StaffRole::SuperAdmin))
            ->post(route('admin.catalogue.matching.conflicts.resolve', $conflict), ['resolution' => 'dismissed', 'note' => 'Rule updated'])
            ->assertSessionHasNoErrors();

        expect($conflict->fresh()->status)->toBe(ConflictStatus::Dismissed);
    });

    it('requires a resolution note', function () {
        $conflict = MatchingConflict::factory()->create();

        $this->actingAs(adminStaff())
            ->post(route('admin.catalogue.matching.conflicts.resolve', $conflict), ['resolution' => 'open'])
            ->assertSessionHasErrors(['resolution', 'note']);
    });
});

describe('product search', function () {
    it('returns at most 20 active products matching name, brand or EAN', function () {
        $whey = Scenario::product(name: 'Hydro Whey');
        Scenario::product(name: 'Retired Whey', attributes: ['status' => ProductStatus::Retired]);
        Scenario::product(brand: 'Other Brand', name: 'Creatine');
        foreach (range(1, 25) as $i) {
            Scenario::product(brand: 'Bulk Brand', name: "Whey Batch {$i}");
        }
        $staff = adminStaff();

        $response = $this->actingAs($staff)->getJson(route('admin.catalogue.products.search', ['q' => 'WHEY']))->assertOk();

        expect($response->json('data'))->toHaveCount(20)
            ->and(collect($response->json('data'))->pluck('name')->contains('Retired Whey'))->toBeFalse()
            ->and($response->json('data.0'))->toHaveKeys(['id', 'name', 'brand', 'pack', 'ean']);

        $this->actingAs($staff)->getJson(route('admin.catalogue.products.search', ['q' => $whey->ean]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $whey->id)
            ->assertJsonCount(1, 'data');

        $this->actingAs($staff)->getJson(route('admin.catalogue.products.search', ['q' => 'other brand']))
            ->assertJsonPath('data.0.name', 'Creatine');

        $this->actingAs($staff)->getJson(route('admin.catalogue.products.search', ['q' => 'w']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    });

    it('treats LIKE wildcards in the term literally', function () {
        Scenario::product(name: 'Whey Isolate');

        $this->actingAs(adminStaff())->getJson(route('admin.catalogue.products.search', ['q' => '%%']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});
