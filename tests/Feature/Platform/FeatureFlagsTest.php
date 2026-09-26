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

it('defaults reviews-submission and merchant-reviews on in the testing environment (A-36)', function () {
    $flags = app(FeatureFlags::class);

    expect(app()->environment())->toBe('testing')
        ->and($flags->enabled(Feature::ReviewsSubmission))->toBeTrue()
        ->and($flags->enabled(Feature::MerchantReviews))->toBeTrue();
});

it('defaults reviews-submission and merchant-reviews OFF outside local/testing/demo (A-36)', function () {
    $originalAppEnv = getenv('APP_ENV');

    putenv('APP_ENV=production');
    $_ENV['APP_ENV'] = 'production';
    putenv('FEATURE_REVIEWS_SUBMISSION');
    unset($_ENV['FEATURE_REVIEWS_SUBMISSION']);
    putenv('FEATURE_MERCHANT_REVIEWS');
    unset($_ENV['FEATURE_MERCHANT_REVIEWS']);

    try {
        // Re-evaluated fresh, bypassing the already-cached app config, so
        // this asserts the literal env-aware default in the file itself.
        $features = require base_path('config/features.php');

        expect($features['reviews-submission'])->toBeFalse()
            ->and($features['merchant-reviews'])->toBeFalse();
    } finally {
        if ($originalAppEnv === false) {
            putenv('APP_ENV');
            unset($_ENV['APP_ENV']);
        } else {
            putenv("APP_ENV={$originalAppEnv}");
            $_ENV['APP_ENV'] = $originalAppEnv;
        }
    }
});

it('shares only client-visible flags as the features inertia prop', function () {
    config(['features.feed-url-fetch' => false, 'features.matching-auto-publish' => false]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('features', ['merchant-feeds' => true]));
});
