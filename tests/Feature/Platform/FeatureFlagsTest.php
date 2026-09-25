<?php

use App\Domain\Platform\Features\Feature;
use App\Domain\Platform\Features\FeatureFlags;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * A route protected by feature:<flag> for these tests only, so the
 * middleware can be exercised without depending on real merchant routes.
 */
beforeEach(function () {
    Route::middleware(['web', 'feature:merchant-feeds'])
        ->get('/__test/feature-gated', fn () => 'ok');
});

it('defaults every flag to on', function () {
    $flags = app(FeatureFlags::class);

    expect($flags->enabled(Feature::MerchantFeeds))->toBeTrue()
        ->and($flags->enabled(Feature::FeedUrlFetch))->toBeTrue()
        ->and($flags->enabled(Feature::MatchingAutoPublish))->toBeTrue();
});

it('declares an explicit config default for every flag', function () {
    foreach (Feature::cases() as $feature) {
        expect(config()->has("features.{$feature->value}"))->toBeTrue("features.{$feature->value} is configured");
    }
});

it('fails closed when a flag has no config key', function () {
    config(['features' => []]);

    $flags = app(FeatureFlags::class);

    expect($flags->enabled(Feature::MerchantFeeds))->toBeFalse()
        ->and($flags->enabled(Feature::FeedUrlFetch))->toBeFalse()
        ->and($flags->enabled(Feature::MatchingAutoPublish))->toBeFalse()
        ->and($flags->clientFlags())->toBe(['merchant-feeds' => false]);
});

it('reports only client-visible flags', function () {
    $flags = app(FeatureFlags::class)->clientFlags();

    expect($flags)->toBe(['merchant-feeds' => true]);
});

it('404s a feature-gated route when its flag is disabled', function () {
    config(['features.merchant-feeds' => false]);

    $this->get('/__test/feature-gated')->assertNotFound();
});

it('allows a feature-gated route when its flag is enabled', function () {
    $this->get('/__test/feature-gated')->assertOk();
});

it('shares only client-visible flags as the features inertia prop', function () {
    config(['features.feed-url-fetch' => false, 'features.matching-auto-publish' => false]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('features', ['merchant-feeds' => true]));
});
