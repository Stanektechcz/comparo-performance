<?php

use App\Domain\Merchants\MerchantContext;
use App\Domain\Merchants\MerchantRole;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * A route carrying the same middleware stack as routes/merchant.php, so the
 * bound MerchantContext (and the shared Inertia prop it feeds) can be
 * inspected without a real feed page existing yet.
 */
beforeEach(function () {
    Route::middleware(['web', 'auth', 'verified', 'feature:merchant-feeds', 'merchant.context'])
        ->get('/__test/merchant-context', function () {
            $context = app(MerchantContext::class);

            return Inertia::render('dashboard', ['contextProbe' => $context->toArray()]);
        });
});

it('reports canManageFeeds false for an analyst and true for owner/manager', function () {
    expect(MerchantRole::Analyst->canManageFeeds())->toBeFalse()
        ->and(MerchantRole::Owner->canManageFeeds())->toBeTrue()
        ->and(MerchantRole::Manager->canManageFeeds())->toBeTrue()
        ->and(MerchantRole::Owner->canManageFeedCredentials())->toBeTrue()
        ->and(MerchantRole::Manager->canManageFeedCredentials())->toBeFalse()
        ->and(MerchantRole::Analyst->canManageFeedCredentials())->toBeFalse();
});

it('falls back to the lowest membership id when nothing is selected', function () {
    $second = Merchant::factory()->create();
    $first = Merchant::factory()->create();
    $user = User::factory()->create();
    $second->members()->attach($user, ['role' => MerchantRole::Owner->value]);
    $first->members()->attach($user, ['role' => MerchantRole::Manager->value]);

    $lowestId = min($first->id, $second->id);

    $response = $this->actingAs($user)->get('/__test/merchant-context');

    $response->assertInertia(fn ($page) => $page->where('contextProbe.active.id', $lowestId));
});

it('persists a switch to the user\'s own second merchant in the session', function () {
    $merchantA = Merchant::factory()->create();
    $merchantB = Merchant::factory()->create();
    $user = User::factory()->create();
    $merchantA->members()->attach($user, ['role' => MerchantRole::Owner->value]);
    $merchantB->members()->attach($user, ['role' => MerchantRole::Manager->value]);

    $this->actingAs($user)
        ->post(route('merchant.context.update'), ['merchant_id' => $merchantB->id])
        ->assertRedirect();

    expect(session('merchant.active_id'))->toBe($merchantB->id);

    $response = $this->actingAs($user)->get('/__test/merchant-context');
    $response->assertInertia(fn ($page) => $page->where('contextProbe.active.id', $merchantB->id));
});

it('fails to switch to a merchant the user does not belong to, without changing the session', function () {
    $merchantA = Merchant::factory()->create();
    $foreign = Merchant::factory()->create();
    $user = User::factory()->create();
    $merchantA->members()->attach($user, ['role' => MerchantRole::Owner->value]);

    $this->actingAs($user)
        ->from('/merchant')
        ->post(route('merchant.context.update'), ['merchant_id' => $foreign->id])
        ->assertSessionHasErrors('merchant_id');

    expect(session('merchant.active_id'))->not->toBe($foreign->id);
});

it('aborts with 403 for a user with no merchant membership', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/__test/merchant-context')->assertForbidden();
});

it('redirects a guest to login', function () {
    $this->get('/__test/merchant-context')->assertRedirect(route('login'));
});

it('never lists merchants the user does not belong to in the shared prop', function () {
    $merchantA = Merchant::factory()->create();
    Merchant::factory()->create(); // a merchant the user does not belong to

    $user = User::factory()->create();
    $merchantA->members()->attach($user, ['role' => MerchantRole::Owner->value]);

    $response = $this->actingAs($user)->get('/__test/merchant-context');

    $response->assertInertia(fn ($page) => $page
        ->has('contextProbe.available', 1)
        ->where('contextProbe.available.0.id', $merchantA->id));
});
