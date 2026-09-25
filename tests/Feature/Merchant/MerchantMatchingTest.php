<?php

use App\Domain\Catalog\ProductStatus;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Matching\Actions\DecideMatch;
use App\Domain\Matching\CandidateStatus;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Merchants\MerchantRole;
use App\Domain\Platform\Audit\AuditAction;
use App\Http\Presenters\Merchant\MatchingPresenter;
use App\Models\AuditLog;
use App\Models\MatchingDecision;
use App\Models\MerchantProduct;
use App\Models\Product;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use App\Models\ProductComplianceRule;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Matching\MatchingScenario;
use Tests\Feature\Merchant\MerchantPortal;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-09-25 10:00:00');
    $this->tenant = MerchantPortal::tenant();
    $this->merchant = $this->tenant['merchant'];
    $this->listing = $this->tenant['listing'];
    $this->suggestedProductId = (int) MatchingDecision::query()->whereKey($this->listing->current_matching_decision_id)->value('product_id');
    $this->owner = MerchantPortal::member($this->merchant);
    $this->foreign = MerchantPortal::tenant()['listing'];
});

function allowInGermany(int $productId): void
{
    ProductComplianceRule::factory()->status(ComplianceStatus::Allowed)->create([
        'product_id' => $productId,
        'country_id' => MerchantPortal::country()->id,
    ]);
}

describe('queues', function () {
    it('counts only the active merchant\'s listings on the overview', function () {
        MatchingScenario::listing(merchant: $this->merchant);

        $this->actingAs($this->owner)->get(route('merchant.matching.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/matching/index')
                ->where('counts.suggested', 1)
                ->where('counts.unmatched', 1)
                ->where('counts.decisions', 1));
    });

    it('lists only the merchant\'s own suggested and unmatched listings', function () {
        $unmatched = MatchingScenario::listing(merchant: $this->merchant);

        $this->actingAs($this->owner)->get(route('merchant.matching.suggested'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/matching/suggested')
                ->has('listings.data', 1)
                ->where('listings.data.0.id', $this->listing->id)
                ->where('listings.data.0.status.value', 'suggested')
                ->where('listings.data.0.product.id', $this->suggestedProductId));

        $this->actingAs($this->owner)->get(route('merchant.matching.unmatched'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/matching/unmatched')
                ->has('listings.data', 1)
                ->where('listings.data.0.id', $unmatched->id));
    });

    it('shows the decision history with team members by name and staff as the Comparo team', function () {
        $staff = User::factory()->create(['name' => 'Staff Reviewer Name']);
        MatchingScenario::match($this->listing);
        allowInGermany($this->suggestedProductId);
        app(DecideMatch::class)->reject($this->listing->fresh(), MatchingScenario::staffActor($staff), MatchingScenario::at());

        $this->actingAs($this->owner)->get(route('merchant.matching.history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/matching/history')
                ->has('decisions.data', 2)
                ->where('decisions.data.0.kind.value', 'rejected')
                ->where('decisions.data.0.decidedBy', MatchingPresenter::DECIDED_BY_STAFF)
                ->where('decisions.data.1.decidedBy', MatchingPresenter::DECIDED_BY_SYSTEM))
            ->assertDontSee('Staff Reviewer Name');
    });
});

describe('listing', function () {
    it('shows source facts, live candidates with points per signal and the allowed actions', function () {
        $this->actingAs($this->owner)->get(route('merchant.matching.listings.show', $this->listing->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/matching/show')
                ->where('listing.id', $this->listing->id)
                ->where('listing.sku', $this->listing->merchant_sku)
                ->where('listing.feedSource.id', $this->tenant['feed']->id)
                ->missing('listing.feedSource.url')
                ->where('listing.market.code', 'DE')
                ->where('currentDecision.kind.value', 'suggested')
                ->where('candidates.0.product.id', $this->suggestedProductId)
                ->where('candidates.0.parts', fn ($parts) => collect($parts)->every(fn ($part) => is_int($part['points']) && is_string($part['label'])))
                ->where('can.decide', true)
                ->where('actions.confirm', true)
                ->where('actions.propose', true))
            ->assertDontSee(MerchantPortal::PLANTED_TOKEN);
    });

    it('shows analysts the listing read-only', function () {
        $analyst = MerchantPortal::member($this->merchant, MerchantRole::Analyst);

        $this->actingAs($analyst)->get(route('merchant.matching.listings.show', $this->listing->id))
            ->assertInertia(fn (Assert $page) => $page->where('can.decide', false)->where('can.propose', false));
    });
});

describe('decisions', function () {
    it('confirms the suggestion as the merchant user, with an audit row', function () {
        allowInGermany($this->suggestedProductId);

        $this->actingAs($this->owner)->from(route('merchant.matching.listings.show', $this->listing->id))
            ->post(route('merchant.matching.listings.decision', $this->listing->id), ['action' => 'confirm', 'note' => 'Same EAN'])
            ->assertRedirect(route('merchant.matching.listings.show', $this->listing->id))
            ->assertSessionHasNoErrors();

        $decision = MatchingDecision::query()->latest('id')->firstOrFail();
        expect($decision->kind)->toBe(MatchDecisionKind::Manual)
            ->and($decision->product_id)->toBe($this->suggestedProductId)
            ->and($decision->decided_by_user_id)->toBe($this->owner->id)
            ->and($decision->note)->toBe('Same EAN')
            ->and($this->listing->fresh()?->match_status)->toBe(ListingMatchStatus::Manual);

        $audit = AuditLog::query()->where('action', AuditAction::MatchingDecided->value)->sole();
        expect($audit->actor_id)->toBe($this->owner->id)
            ->and($audit->auditable_id)->toBe($this->listing->id);
    });

    it('holds a confirmed link when the product is not allowed in the market', function () {
        ProductComplianceRule::factory()->status(ComplianceStatus::NotAllowed)->create([
            'product_id' => $this->suggestedProductId,
            'country_id' => MerchantPortal::country()->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('merchant.matching.listings.decision', $this->listing->id), ['action' => 'confirm'])
            ->assertSessionHasNoErrors();

        expect($this->listing->fresh()?->match_status)->toBe(ListingMatchStatus::ComplianceHold);
    });

    it('chooses another active product', function () {
        $chosen = MatchingScenario::product(name: 'Clear Whey', pack: '500 g');
        allowInGermany($chosen->id);

        $this->actingAs($this->owner)
            ->post(route('merchant.matching.listings.decision', $this->listing->id), ['action' => 'choose', 'product_id' => $chosen->id])
            ->assertSessionHasNoErrors();

        expect($this->listing->fresh()?->product_id)->toBe($chosen->id)
            ->and(MatchingDecision::query()->latest('id')->value('decided_by_user_id'))->toBe($this->owner->id);
    });

    it('refuses to choose an inactive product', function () {
        $archived = MatchingScenario::product(name: 'Old Whey', attributes: ['status' => ProductStatus::Retired]);

        $this->actingAs($this->owner)->from(route('merchant.matching.listings.show', $this->listing->id))
            ->post(route('merchant.matching.listings.decision', $this->listing->id), ['action' => 'choose', 'product_id' => $archived->id])
            ->assertSessionHasErrors('product_id');
    });

    it('rejects the suggestion; the listing returns to the unmatched queue', function () {
        $manager = MerchantPortal::member($this->merchant, MerchantRole::Manager);

        $this->actingAs($manager)
            ->post(route('merchant.matching.listings.decision', $this->listing->id), ['action' => 'reject'])
            ->assertSessionHasNoErrors();

        $decision = MatchingDecision::query()->latest('id')->firstOrFail();
        expect($decision->kind)->toBe(MatchDecisionKind::Rejected)
            ->and($decision->previous_product_id)->toBe($this->suggestedProductId)
            ->and($decision->decided_by_user_id)->toBe($manager->id)
            ->and($this->listing->fresh()?->match_status)->toBe(ListingMatchStatus::Unmatched)
            ->and(AuditLog::query()->where('action', AuditAction::MatchingDecided->value)->where('actor_id', $manager->id)->exists())->toBeTrue();
    });

    it('turns a decision the listing state does not allow into a friendly error', function () {
        $unmatched = MatchingScenario::listing(merchant: $this->merchant);

        $this->actingAs($this->owner)->from(route('merchant.matching.listings.show', $unmatched->id))
            ->post(route('merchant.matching.listings.decision', $unmatched->id), ['action' => 'confirm'])
            ->assertSessionHasErrors(['decision' => 'There is no pending suggestion to confirm any more. Reload the page.']);
    });

    it('proposes a new product from the listing, audited', function () {
        $this->actingAs($this->owner)
            ->post(route('merchant.matching.listings.propose', $this->listing->id))
            ->assertSessionHasNoErrors();

        $candidate = ProductCandidate::query()->sole();
        expect($candidate->status)->toBe(CandidateStatus::Proposed)
            ->and(ProductCandidateSource::query()->where('product_candidate_id', $candidate->id)->value('merchant_product_id'))->toBe($this->listing->id)
            ->and(AuditLog::query()->where('action', AuditAction::ProductCandidateProposed->value)->where('actor_id', $this->owner->id)->exists())->toBeTrue();
    });

    it('never touches another merchant\'s listing', function () {
        $this->actingAs($this->owner)
            ->post(route('merchant.matching.listings.decision', $this->foreign->id), ['action' => 'reject'])
            ->assertNotFound();

        expect($this->foreign->fresh()?->match_status)->toBe(ListingMatchStatus::Suggested)
            ->and(MerchantProduct::query()->whereKey($this->foreign->id)->value('product_id'))->toBeNull();
    });
});

it('searches at most 20 active catalogue products', function () {
    foreach (range(1, 22) as $index) {
        MatchingScenario::product(name: "Searchable Whey {$index}");
    }
    MatchingScenario::product(name: 'Searchable Whey Archived', attributes: ['status' => ProductStatus::Retired]);

    $response = $this->actingAs($this->owner)->getJson(route('merchant.catalogue.products.search', ['q' => 'searchable']))->assertOk();

    expect($response->json('data'))->toHaveCount(20)
        ->and(collect($response->json('data'))->pluck('name'))->not->toContain('Searchable Whey Archived')
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'name', 'brand', 'pack', 'ean']);

    $this->actingAs($this->owner)->getJson(route('merchant.catalogue.products.search', ['q' => 'a']))->assertUnprocessable();
    expect(Product::query()->count())->toBeGreaterThan(20);
});
