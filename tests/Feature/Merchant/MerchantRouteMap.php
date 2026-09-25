<?php

namespace Tests\Feature\Merchant;

use Illuminate\Http\UploadedFile;

/**
 * Every `merchant.*` route with how to call it and what kind of access it
 * needs. RouteIsolationCoverageTest fails when a route is missing here, so a
 * new merchant route cannot ship without the isolation suite covering it.
 *
 * Kinds: `read` (every member), `write` (owner/manager), `credentials`
 * (owner + password confirmation), `context` (switching merchants).
 */
final class MerchantRouteMap
{
    /** @var array<string, array{method: string, ids: list<string>, kind: string}> */
    public const array ROUTES = [
        'merchant.context.update' => ['method' => 'post', 'ids' => [], 'kind' => 'context'],
        'merchant.feeds.index' => ['method' => 'get', 'ids' => [], 'kind' => 'read'],
        'merchant.feeds.create' => ['method' => 'get', 'ids' => [], 'kind' => 'read'],
        'merchant.feeds.store' => ['method' => 'post', 'ids' => [], 'kind' => 'write'],
        'merchant.feeds.show' => ['method' => 'get', 'ids' => ['feed'], 'kind' => 'read'],
        'merchant.feeds.edit' => ['method' => 'get', 'ids' => ['feed'], 'kind' => 'read'],
        'merchant.feeds.update' => ['method' => 'put', 'ids' => ['feed'], 'kind' => 'write'],
        'merchant.feeds.mapping.edit' => ['method' => 'get', 'ids' => ['feed'], 'kind' => 'read'],
        'merchant.feeds.mapping.update' => ['method' => 'put', 'ids' => ['feed'], 'kind' => 'write'],
        'merchant.feeds.credentials.confirm' => ['method' => 'get', 'ids' => ['feed'], 'kind' => 'credentials'],
        'merchant.feeds.credentials.update' => ['method' => 'put', 'ids' => ['feed'], 'kind' => 'credentials'],
        'merchant.feeds.status.update' => ['method' => 'post', 'ids' => ['feed'], 'kind' => 'write'],
        'merchant.feeds.runs.store' => ['method' => 'post', 'ids' => ['feed'], 'kind' => 'write'],
        'merchant.feeds.uploads.store' => ['method' => 'post', 'ids' => ['feed'], 'kind' => 'write'],
        'merchant.feeds.runs.show' => ['method' => 'get', 'ids' => ['feed', 'run'], 'kind' => 'read'],
        'merchant.feeds.runs.cancel' => ['method' => 'post', 'ids' => ['feed', 'run'], 'kind' => 'write'],
        'merchant.feeds.runs.errors.export' => ['method' => 'get', 'ids' => ['feed', 'run'], 'kind' => 'read'],
        'merchant.matching.index' => ['method' => 'get', 'ids' => [], 'kind' => 'read'],
        'merchant.matching.suggested' => ['method' => 'get', 'ids' => [], 'kind' => 'read'],
        'merchant.matching.unmatched' => ['method' => 'get', 'ids' => [], 'kind' => 'read'],
        'merchant.matching.history' => ['method' => 'get', 'ids' => [], 'kind' => 'read'],
        'merchant.matching.listings.show' => ['method' => 'get', 'ids' => ['listing'], 'kind' => 'read'],
        'merchant.matching.listings.decision' => ['method' => 'post', 'ids' => ['listing'], 'kind' => 'write'],
        'merchant.matching.listings.propose' => ['method' => 'post', 'ids' => ['listing'], 'kind' => 'write'],
        'merchant.catalogue.products.search' => ['method' => 'get', 'ids' => [], 'kind' => 'read'],
    ];

    /**
     * Route names of one kind (all kinds when null), optionally only those with tenant ids.
     *
     * @return list<string>
     */
    public static function names(?string $kind = null, bool $withIds = false): array
    {
        return array_keys(array_filter(
            self::ROUTES,
            static fn (array $route): bool => ($kind === null || $route['kind'] === $kind) && (! $withIds || $route['ids'] !== []),
        ));
    }

    /**
     * @param  array{feed: int, run: int, listing: int}  $ids
     */
    public static function url(string $name, array $ids): string
    {
        $parameters = [];

        foreach (self::ROUTES[$name]['ids'] as $key) {
            $parameters[$key] = $ids[$key];
        }

        if ($name === 'merchant.catalogue.products.search') {
            $parameters['q'] = 'whey';
        }

        return route($name, $parameters);
    }

    /**
     * A plausible payload: with it, a route that is allowed does not fail
     * on missing input before it reaches the authorisation under test.
     *
     * @return array<string, mixed>
     */
    public static function payload(string $name): array
    {
        return match ($name) {
            'merchant.feeds.store', 'merchant.feeds.update' => [
                'name' => 'Isolation feed', 'format' => 'csv', 'transport' => 'url',
                'url' => 'https://feeds.example.com/isolation.csv', 'currency' => 'EUR', 'encoding' => 'UTF-8',
            ],
            'merchant.feeds.mapping.update' => ['mapping' => [
                'merchant_sku' => 'sku', 'title' => 'title', 'price' => 'price', 'availability' => 'availability', 'product_url' => 'url',
            ]],
            'merchant.feeds.credentials.update' => ['type' => 'bearer', 'token' => 'isolation-token'],
            'merchant.feeds.status.update' => ['action' => 'pause'],
            'merchant.feeds.uploads.store' => ['file' => UploadedFile::fake()->createWithContent('feed.csv', "sku,title\nA,B\n")],
            'merchant.matching.listings.decision' => ['action' => 'reject'],
            default => [],
        };
    }
}
