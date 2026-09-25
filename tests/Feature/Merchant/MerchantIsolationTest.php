<?php

use App\Domain\Merchants\MerchantRole;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MerchantProduct;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Merchant\MerchantPortal;
use Tests\Feature\Merchant\MerchantRouteMap;

/*
|--------------------------------------------------------------------------
| Tenant isolation over EVERY merchant route (MerchantRouteMap::ROUTES)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();
    $this->a = MerchantPortal::tenant();
    $this->b = MerchantPortal::tenant();
});

/**
 * @param  array{feed: int, run: int, listing: int}  $ids
 * @param  array<string, mixed>  $session
 */
function callMerchantRoute(string $name, array $ids, ?User $user = null, array $session = []): TestResponse
{
    $test = test();

    if ($user !== null) {
        $test->actingAs($user);
    }

    $method = MerchantRouteMap::ROUTES[$name]['method'];
    $url = MerchantRouteMap::url($name, $ids);

    return $test->withSession($session)->{$method}($url, MerchantRouteMap::payload($name));
}

/**
 * @param  array{feed: FeedSource, run: FeedRun, listing: MerchantProduct}  $tenant
 * @return array{feed: int, run: int, listing: int}
 */
function tenantIds(array $tenant): array
{
    return ['feed' => $tenant['feed']->id, 'run' => $tenant['run']->id, 'listing' => $tenant['listing']->id];
}

dataset('merchant routes', MerchantRouteMap::names());
dataset('merchant routes with ids', MerchantRouteMap::names(withIds: true));
dataset('merchant read routes', MerchantRouteMap::names('read'));
dataset('merchant mutations', [...MerchantRouteMap::names('write'), ...MerchantRouteMap::names('credentials')]);
dataset('merchant credential routes', MerchantRouteMap::names('credentials'));

it('answers 404 for another merchant\'s feed, run and listing ids', function (string $name) {
    $owner = MerchantPortal::member($this->a['merchant']);

    callMerchantRoute($name, tenantIds($this->b), $owner, MerchantPortal::passwordConfirmed())->assertNotFound();
})->with('merchant routes with ids');

it('answers 404 for another merchant\'s run under the merchant\'s own feed', function (string $name) {
    $owner = MerchantPortal::member($this->a['merchant']);
    $ids = ['feed' => $this->a['feed']->id, 'run' => $this->b['run']->id, 'listing' => $this->a['listing']->id];

    callMerchantRoute($name, $ids, $owner)->assertNotFound();
})->with(['merchant.feeds.runs.show', 'merchant.feeds.runs.cancel', 'merchant.feeds.runs.errors.export']);

it('answers 404 for a run of another feed of the same merchant', function () {
    $owner = MerchantPortal::member($this->a['merchant']);
    $otherFeed = MerchantPortal::feed($this->a['merchant']);
    $ids = ['feed' => $otherFeed->id, 'run' => $this->a['run']->id, 'listing' => $this->a['listing']->id];

    callMerchantRoute('merchant.feeds.runs.show', $ids, $owner)->assertNotFound();
});

it('answers 404 for ids of the user\'s OTHER merchant while merchant A is active', function (string $name) {
    $user = MerchantPortal::member($this->a['merchant']);
    MerchantPortal::member($this->b['merchant'], MerchantRole::Owner, $user);
    $session = ['merchant.active_id' => $this->a['merchant']->id, ...MerchantPortal::passwordConfirmed()];

    callMerchantRoute($name, tenantIds($this->b), $user, $session)->assertNotFound();
})->with('merchant routes with ids');

it('lets an analyst open every read-only page', function (string $name) {
    $analyst = MerchantPortal::member($this->a['merchant'], MerchantRole::Analyst);

    callMerchantRoute($name, tenantIds($this->a), $analyst)->assertOk();
})->with('merchant read routes');

it('forbids every mutation to an analyst', function (string $name) {
    $analyst = MerchantPortal::member($this->a['merchant'], MerchantRole::Analyst);

    callMerchantRoute($name, tenantIds($this->a), $analyst, MerchantPortal::passwordConfirmed())->assertForbidden();

    expect($this->a['feed']->fresh()?->status)->toBe($this->a['feed']->status)
        ->and($this->a['listing']->fresh()?->match_status)->toBe($this->a['listing']->match_status);
})->with('merchant mutations');

it('forbids credential changes to a manager even after password confirmation', function (string $name) {
    $manager = MerchantPortal::member($this->a['merchant'], MerchantRole::Manager);

    callMerchantRoute($name, tenantIds($this->a), $manager, MerchantPortal::passwordConfirmed())->assertForbidden();
})->with('merchant credential routes');

it('requires a fresh password confirmation from the owner before credentials change', function (string $name) {
    $owner = MerchantPortal::member($this->a['merchant']);

    callMerchantRoute($name, tenantIds($this->a), $owner)->assertRedirect(route('password.confirm'));

    callMerchantRoute($name, tenantIds($this->a), $owner, MerchantPortal::passwordConfirmed())
        ->assertRedirect(route('merchant.feeds.edit', $this->a['feed']->id).'#credentials');
})->with('merchant credential routes');

it('sends guests to the login page', function (string $name) {
    callMerchantRoute($name, tenantIds($this->a))->assertRedirect(route('login'));
})->with('merchant routes');

it('forbids verified users without any merchant membership', function (string $name) {
    callMerchantRoute($name, tenantIds($this->a), User::factory()->create(), MerchantPortal::passwordConfirmed())->assertForbidden();
})->with('merchant routes');

it('hides every merchant route when the merchant-feeds flag is off', function (string $name) {
    config(['features.merchant-feeds' => false]);
    $owner = MerchantPortal::member($this->a['merchant']);

    callMerchantRoute($name, tenantIds($this->a), $owner, MerchantPortal::passwordConfirmed())->assertNotFound();
})->with('merchant routes');
