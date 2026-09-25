<?php

namespace App\Providers;

use App\Domain\Compliance\Events\ComplianceRuleChanged;
use App\Domain\Feeds\Fetching\DnsHostResolver;
use App\Domain\Feeds\Fetching\FeedFetcher;
use App\Domain\Feeds\Fetching\HostResolver;
use App\Domain\Feeds\Listeners\PublishLatestObservation;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Domain\Matching\Events\ProductMatched;
use App\Domain\Offers\Events\OfferDeactivated;
use App\Domain\Offers\Events\OfferPublished;
use App\Domain\Offers\Events\OfferRelinked;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Domain\Platform\Listeners\BumpProductCacheVersion;
use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Platform\Queues\LongRunningQueue;
use App\Domain\Pricing\Events\PriceChanged;
use App\Domain\Search\Indexing\CatalogIndexTriggers;
use App\Domain\Search\Indexing\Listeners\EnqueueProductDocuments;
use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Category;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\Ingredient;
use App\Models\Merchant;
use App\Models\MerchantRiskEvent;
use App\Models\MerchantShippingZone;
use App\Models\MerchantTrustSignal;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductComplianceRule;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerFeedFetcher();
    }

    /**
     * The feed fetcher gets its own HTTP client factory WITHOUT an event
     * dispatcher: request/response events carry the auth headers and the
     * token-bearing URL, so they must never reach HTTP client listeners
     * (Telescope, Nightwatch, Pulse, logging).
     */
    protected function registerFeedFetcher(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
        $this->app->bind(FeedFetcher::class, static fn (Application $app): FeedFetcher => new FeedFetcher(
            new HttpFactory,
            $app->make(HostResolver::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->invalidateCatalogCacheOnChange();
        $this->registerDomainListeners();
        $this->queueSearchIndexingOnChange();
        $this->guardLongRunningQueue();
    }

    /**
     * Catalogue, merchant and market changes write search outbox rows after
     * commit (docs/architecture/phase-3-search.md §5; the mapping lives in
     * {@see CatalogIndexTriggers}). Offer, price and compliance events are
     * handled by {@see EnqueueProductDocuments} ({@see self::registerDomainListeners()}).
     */
    protected function queueSearchIndexingOnChange(): void
    {
        $triggers = fn (): CatalogIndexTriggers => $this->app->make(CatalogIndexTriggers::class);

        // `updated` fires only for a real change; `saved` cannot tell a creation
        // from a later update of the same instance (wasRecentlyCreated stays true).
        foreach (['created', 'updated', 'deleted'] as $hook) {
            Offer::{$hook}(static fn (Offer $model) => $triggers()->offerChanged($model));
        }

        Product::created(static fn (Product $model) => $triggers()->productSaved($model, true));
        Product::updated(static fn (Product $model) => $triggers()->productSaved($model, false));
        Product::deleted(static fn (Product $model) => $triggers()->productDeleted($model));
        Brand::created(static fn (Brand $model) => $triggers()->brandSaved($model, true));
        Brand::updated(static fn (Brand $model) => $triggers()->brandSaved($model, false));
        Brand::deleted(static fn (Brand $model) => $triggers()->brandDeleted($model));
        Category::created(static fn (Category $model) => $triggers()->categorySaved($model, true));
        Category::updated(static fn (Category $model) => $triggers()->categorySaved($model, false));
        Category::deleted(static fn (Category $model) => $triggers()->categoryDeleted($model));
        Ingredient::created(static fn (Ingredient $model) => $triggers()->ingredientSaved($model, true));
        Ingredient::updated(static fn (Ingredient $model) => $triggers()->ingredientSaved($model, false));
        Ingredient::deleted(static fn (Ingredient $model) => $triggers()->ingredientDeleted($model));
        Merchant::created(static fn (Merchant $model) => $triggers()->merchantSaved($model, true));
        Merchant::updated(static fn (Merchant $model) => $triggers()->merchantSaved($model, false));
        Merchant::deleted(static fn (Merchant $model) => $triggers()->merchantChanged($model->id));
        Country::updated(static fn (Country $model) => $triggers()->countryUpdated($model));
        Country::deleted(static fn (Country $model) => $triggers()->countryDeleted($model));

        $byAlias = static fn (BrandAlias $model) => $triggers()->brandAliasChanged($model->brand_id);
        $byMerchant = static fn (Coupon|MerchantShippingZone|MerchantTrustSignal|MerchantRiskEvent $model) => $triggers()->merchantChanged($model->merchant_id);

        foreach ([BrandAlias::class, Coupon::class, MerchantShippingZone::class, MerchantTrustSignal::class, MerchantRiskEvent::class] as $model) {
            $listener = $model === BrandAlias::class ? $byAlias : $byMerchant;
            $model::created($listener);
            $model::updated($listener);
            $model::deleted($listener);
        }
    }

    /**
     * The long-running connection must not re-deliver a feed job that is
     * still running ({@see LongRunningQueue}). Local and testing throw, so the
     * mistake never ships; elsewhere it is logged as critical when a console
     * process (queue worker, scheduler, artisan) boots, not on every request.
     */
    protected function guardLongRunningQueue(): void
    {
        $strict = $this->app->environment('local', 'testing');

        if (! $strict && ! $this->app->runningInConsole()) {
            return;
        }

        $this->app->make(LongRunningQueue::class)->guard(FeedRunPipeline::longestJobTimeout(), $strict);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public-api', static fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
    }

    /**
     * Cached comparisons are keyed by a per-product version token; any change
     * that can move a price, a coupon, shipping, trust or compliance bumps it.
     *
     * Offers are published through domain actions whose after-commit events
     * bump the version ({@see self::registerDomainListeners()}). The Offer
     * hooks remain as a safety net for direct writes (tests, tinker, imports):
     * they bump only when the row really changed, after the transaction
     * commits, and bump the original product too when an offer moved.
     */
    protected function invalidateCatalogCacheOnChange(): void
    {
        $versions = fn (): CatalogCacheVersion => $this->app->make(CatalogCacheVersion::class);

        $bumpAfterCommit = static function (array $productIds) use ($versions): void {
            DB::afterCommit(static function () use ($versions, $productIds): void {
                foreach (array_unique($productIds) as $productId) {
                    $versions()->bumpProduct($productId);
                }
            });
        };

        Offer::saved(static function (Offer $offer) use ($bumpAfterCommit): void {
            if (! $offer->wasRecentlyCreated && ! $offer->wasChanged()) {
                return;
            }

            $productIds = [$offer->product_id];
            if ($offer->wasChanged('product_id') && $offer->getOriginal('product_id') !== null) {
                $productIds[] = (int) $offer->getOriginal('product_id');
            }

            $bumpAfterCommit($productIds);
        });
        Offer::deleted(static fn (Offer $offer) => $bumpAfterCommit([$offer->product_id]));

        $byProduct = static fn (ProductComplianceRule $model) => $versions()->bumpProduct($model->product_id);
        ProductComplianceRule::saved($byProduct);
        ProductComplianceRule::deleted($byProduct);

        // A real rule change raises the after-commit ComplianceRuleChanged event,
        // which re-indexes the product with priority (`updated` fires only when dirty).
        $complianceChanged = static fn (ProductComplianceRule $model) => Event::dispatch(new ComplianceRuleChanged($model->product_id));
        ProductComplianceRule::created($complianceChanged);
        ProductComplianceRule::updated($complianceChanged);
        ProductComplianceRule::deleted($complianceChanged);

        $byMerchant = static fn (Coupon|MerchantShippingZone|MerchantTrustSignal|MerchantRiskEvent $model) => $versions()->bumpMerchant($model->merchant_id);
        // Risk events move hidden rank penalties when recorded and when resolved.
        MerchantRiskEvent::saved($byMerchant);
        MerchantRiskEvent::deleted($byMerchant);
        Coupon::saved($byMerchant);
        Coupon::deleted($byMerchant);
        MerchantShippingZone::saved($byMerchant);
        MerchantShippingZone::deleted($byMerchant);
        MerchantTrustSignal::created($byMerchant);

        $forgetMarkets = fn () => $this->app->make(MarketResolver::class)->forget();
        Country::saved($forgetMarkets);
        Country::deleted($forgetMarkets);
    }

    /**
     * Domain listeners outside app/Listeners are not auto-discovered.
     */
    protected function registerDomainListeners(): void
    {
        foreach ([OfferPublished::class, OfferDeactivated::class, OfferRelinked::class, PriceChanged::class] as $event) {
            Event::listen($event, BumpProductCacheVersion::class);
            Event::listen($event, EnqueueProductDocuments::class);
        }

        Event::listen(ComplianceRuleChanged::class, EnqueueProductDocuments::class);

        // A listing linked outside a feed run publishes its latest feed observation.
        Event::listen(ProductMatched::class, PublishLatestObservation::class);
    }
}
