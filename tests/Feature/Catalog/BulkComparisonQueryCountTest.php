<?php

use App\Domain\Compliance\Queries\ComplianceResolver;
use App\Domain\Platform\Markets\MarketContext;
use App\Http\Presenters\ProductPresenter;
use App\Models\MarketPriceStat;
use App\Models\Merchant;
use App\Models\Product;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogScenario;

/*
 * BACKLOG F-15: a cold-cache page of product cards costs a constant number of
 * queries, independent of the number of products (previously ~8–10 per
 * product: offers + six eager loads, compliance, completeness, history).
 */

/** Upper bound of queries for a cold page of product cards, whatever its size. */
const COLD_PAGE_QUERY_BOUND = 16;

beforeEach(function () {
    $this->freezeTime();
    $this->catalog = CatalogScenario::create();
    $this->merchants = [
        $this->catalog->merchant(),
        $this->catalog->merchant(['DE' => 490]),
        $this->catalog->merchant(['DE' => 0, 'CZ' => 290]),
    ];
    $this->catalog->coupon($this->merchants[1], CouponFactory::new()->percent(10));
});

/**
 * @return Collection<int, Product> freshly loaded, as a controller would have them
 */
function coldPageProducts(CatalogScenario $catalog, array $merchants, int $count): Collection
{
    $ids = [];

    foreach (range(1, $count) as $index) {
        $product = $catalog->product();
        $catalog->allow($product);

        foreach ($merchants as $offset => $merchant) {
            /** @var Merchant $merchant */
            $catalog->offer($product, $merchant, 2000 + $index * 10 + $offset * 100);
        }

        foreach ([1, 2, 3] as $daysAgo) {
            MarketPriceStat::create([
                'product_id' => $product->id,
                'market' => MarketPriceStat::ALL_MARKETS,
                'stat_date' => now()->subDays($daysAgo)->toDateString(),
                'min_price_minor' => 2000 + $daysAgo * 10,
                'currency' => 'EUR',
                'source' => MarketPriceStat::SOURCE_AGGREGATED,
                'computed_at' => now(),
            ]);
        }

        $ids[] = $product->id;
    }

    return Product::query()->whereKey($ids)->orderBy('id')->get();
}

/**
 * Queries issued by one cold summaries() call.
 *
 * @param  Collection<int, Product>  $products
 */
function coldPageQueries(Collection $products, MarketContext $market, bool $preResolved): int
{
    Cache::flush();
    $decisions = $preResolved ? app(ComplianceResolver::class)->decideMany($products->pluck('id')->all(), $market) : null;
    $presenter = app(ProductPresenter::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $summaries = $presenter->summaries($products, $market, now()->toImmutable(), $decisions);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($summaries)->toHaveCount($products->count())
        ->and(collect($summaries)->pluck('offerCount')->unique()->all())->toBe([3]);

    return $count;
}

it('presents 20 cold product cards with a constant number of queries', function (bool $preResolved) {
    $market = MarketContext::fromCountry($this->catalog->country('DE'));
    $five = coldPageQueries(coldPageProducts($this->catalog, $this->merchants, 5), $market, $preResolved);
    $twenty = coldPageQueries(coldPageProducts($this->catalog, $this->merchants, 20), $market, $preResolved);

    expect($twenty)->toBe($five)
        ->and($twenty)->toBeLessThanOrEqual(COLD_PAGE_QUERY_BOUND);
})->with([
    'compliance resolved by the caller (search results)' => [true],
    'compliance resolved in the batch (catalogue pages)' => [false],
]);

it('issues no comparison queries for a warm page', function () {
    $market = MarketContext::fromCountry($this->catalog->country('DE'));
    $products = coldPageProducts($this->catalog, $this->merchants, 20);
    $presenter = app(ProductPresenter::class);
    $presenter->summaries($products, $market, now()->toImmutable());

    DB::flushQueryLog();
    DB::enableQueryLog();
    $presenter->summaries($products, $market, now()->toImmutable());
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBe(0);
});
