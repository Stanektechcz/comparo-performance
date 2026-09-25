<?php

namespace App\Providers;

use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Domain\Platform\Markets\MarketResolver;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\MerchantRiskEvent;
use App\Models\MerchantShippingZone;
use App\Models\MerchantTrustSignal;
use App\Models\Offer;
use App\Models\ProductComplianceRule;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->invalidateCatalogCacheOnChange();
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
     * (Phase 2 replaces these model hooks with domain events + queued listeners.)
     */
    protected function invalidateCatalogCacheOnChange(): void
    {
        $versions = fn (): CatalogCacheVersion => $this->app->make(CatalogCacheVersion::class);

        $byProduct = static fn (Offer|ProductComplianceRule $model) => $versions()->bumpProduct($model->product_id);
        Offer::saved($byProduct);
        Offer::deleted($byProduct);
        ProductComplianceRule::saved($byProduct);
        ProductComplianceRule::deleted($byProduct);

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
}
