<?php

namespace Tests\Feature\Feeds;

use App\Domain\Feeds\Fetching\FeedFetcher;
use App\Domain\Feeds\Fetching\HostResolver;
use App\Domain\Feeds\Pipeline\FeedStorage;
use App\Models\FeedSource;
use App\Models\Merchant;
use Database\Factories\CurrencyFactory;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;

/**
 * Shared arrangement for the feed pipeline feature tests.
 */
final class FeedPipelineFixtures
{
    public const string MERCHANT_DOMAIN = 'peaksupps.de';

    public const string FEED_URL = 'https://peaksupps.de/feeds/products.csv?token=s3cret-feed-token';

    public const string HEADER = 'merchant_sku,title,brand,ean,price,currency,availability,url';

    /** A valid row without warnings (valid GTIN, merchant domain). */
    public static function row(string $sku, string $price = '40.54', string $ean = '4006381333931'): string
    {
        return "{$sku},Whey Isolate 90 Vanilla 900 g,IRONFORGE,{$ean},{$price},EUR,in_stock,https://peaksupps.de/p/".strtolower($sku);
    }

    /**
     * @param  list<string>  $rows
     */
    public static function csv(array $rows): string
    {
        return self::HEADER."\n".implode("\n", $rows)."\n";
    }

    public static function currencies(): void
    {
        foreach (['EUR', 'CZK'] as $code) {
            CurrencyFactory::resolveId($code);
        }
    }

    public static function merchant(): Merchant
    {
        return Merchant::factory()->create(['website' => self::MERCHANT_DOMAIN]);
    }

    /**
     * Bind a feed fetcher whose HTTP client answers from `$responses` and whose
     * resolver knows only the merchant host. Stray requests throw.
     *
     * @param  array<string, mixed>  $responses  URL pattern => response
     */
    public static function fakeFetcher(array $responses): Factory
    {
        $http = new Factory;
        $http->preventStrayRequests();
        $http->fake($responses);

        $resolver = new class implements HostResolver
        {
            public function resolve(string $host): array
            {
                return $host === FeedPipelineFixtures::MERCHANT_DOMAIN ? ['93.184.216.34'] : [];
            }
        };

        app()->instance(HostResolver::class, $resolver);
        app()->instance(FeedFetcher::class, new FeedFetcher($http, $resolver));

        return $http;
    }

    /**
     * Store a payload in the source merchant's private feed directory.
     */
    public static function storePayload(FeedSource $source, string $contents): string
    {
        $path = FeedStorage::merchantDirectory($source->merchant_id).'/'.Str::uuid()->toString().'.'.$source->format->value;
        app(FeedStorage::class)->disk()->put($path, $contents);

        return $path;
    }

    public static function fixture(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/Feeds/'.$name);
    }
}
