<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Merchants\Queries\MerchantScores;
use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Shared\Money;
use App\Http\Controllers\Controller;
use App\Http\Presenters\MoneyPresenter;
use App\Http\Presenters\OfferComparisonPresenter;
use App\Http\Presenters\ProductPresenter;
use App\Http\Presenters\SeoPresenter;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public shop profiles. Only public trust signals are shown — never internal
 * risk, commercial terms or affiliate data.
 */
class ShopController extends Controller
{
    private const int PRODUCTS_ON_PROFILE = 24;

    public function index(MarketContext $market, MerchantScores $scores, SeoPresenter $seo): Response
    {
        $shops = Merchant::query()
            ->listed()
            ->withCount(['offers' => static fn ($query) => $query->where('is_active', true)])
            ->withExists(['shippingZones as ships_to_market' => static fn ($query) => $query->where('country_id', $market->countryId ?? 0)])
            ->orderBy('name')
            ->get();

        return Inertia::render('catalog/shops/index', [
            'seo' => $seo->page('Shops', 'Sports nutrition shops rated on verified trust signals: pricing accuracy, delivery, complaint resolution.', route('shops.index')),
            'shops' => $shops->map(static fn (Merchant $shop): array => [
                'slug' => $shop->slug,
                'name' => $shop->name,
                'verified' => $shop->isVerified(),
                'trust' => OfferComparisonPresenter::trust($scores->trust($shop)),
                'shipsToMarket' => (bool) $shop->getAttribute('ships_to_market'),
                'offerCount' => (int) $shop->getAttribute('offers_count'),
            ])->all(),
        ]);
    }

    public function show(string $slug, MarketContext $market, MerchantScores $scores, ProductPresenter $products, SeoPresenter $seo): Response
    {
        $shop = Merchant::query()->listed()->where('slug', $slug)->with('shippingZones.country')->firstOrFail();
        $zone = $shop->shippingZones->firstWhere('country_id', $market->countryId);
        $listed = Product::query()
            ->listed()
            ->whereHas('offers', static fn ($query) => $query->where('merchant_id', $shop->id)->where('is_active', true))
            ->orderBy('name')
            ->limit(self::PRODUCTS_ON_PROFILE)
            ->get();

        return Inertia::render('catalog/shops/show', [
            'seo' => $seo->page("{$shop->name} — shop trust and prices", "Trust signals, delivery and shipping costs for {$shop->name}.", route('shops.show', $shop->slug)),
            'shop' => [
                'slug' => $shop->slug,
                'name' => $shop->name,
                'website' => $shop->website,
                'description' => $shop->description,
                'verified' => $shop->isVerified(),
                'returnDays' => $shop->return_days,
                'trust' => OfferComparisonPresenter::trust($scores->trust($shop)),
                'shipping' => $zone === null ? null : [
                    'cost' => MoneyPresenter::present(Money::of($zone->cost_minor, $zone->currency)),
                    'deliveryDays' => [$zone->min_days, $zone->max_days],
                    'freeOver' => $shop->free_shipping_threshold_minor === null ? null : MoneyPresenter::present(Money::of($shop->free_shipping_threshold_minor, $shop->currency)),
                ],
                'markets' => $shop->shippingZones->map(static fn (MerchantShippingZone $z): string => $z->country->code)->sort()->values()->all(),
            ],
            'products' => $products->summaries($listed, $market, now()->toImmutable()),
        ]);
    }
}
