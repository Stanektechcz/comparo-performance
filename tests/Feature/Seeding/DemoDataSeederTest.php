<?php

use App\Domain\Accounts\Authorization\StaffRole;
use App\Domain\Catalog\BrandAliasStatus;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Merchants\MerchantStatus;
use App\Domain\Offers\LinkStatus;
use App\Domain\Offers\Ranking\RankingWeights;
use App\Domain\Platform\PrototypeImport\DemoDataRefused;
use App\Domain\Platform\PrototypeImport\DemoEnvironment;
use App\Domain\Platform\PrototypeImport\PrototypeSnapshotImporter;
use App\Domain\Pricing\CouponType;
use App\Domain\Pricing\History\SnapshotSource;
use App\Domain\Pricing\PriceAnomaly;
use App\Models\Coupon;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\Product;
use App\Models\RankingVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'comparo.demo.enabled' => true,
        'comparo.demo.password' => 'demo-password-for-tests',
    ]);
});

/**
 * An importer anchored at seed.NOW: zero time shift, so values equal the prototype's.
 */
function demoSeedingParityImporter(): PrototypeSnapshotImporter
{
    return PrototypeSnapshotImporter::fromConfig(
        PrototypeSnapshotImporter::seedNow((string) config('comparo.demo.snapshot')),
    );
}

/**
 * @return array<string, int>
 */
function demoSeedingTableCounts(): array
{
    $tables = [
        'currencies', 'exchange_rates', 'countries', 'brands', 'categories', 'ingredients', 'products',
        'product_variants', 'ingredient_product', 'merchants', 'merchant_shipping_zones', 'merchant_trust_signals',
        'merchant_risk_events', 'merchant_products', 'offers', 'coupons', 'coupon_country',
        'product_compliance_rules', 'market_price_stats', 'price_snapshots',
    ];

    return collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
}

it('imports the prototype snapshot with the documented counts and values', function () {
    app()->instance(PrototypeSnapshotImporter::class, demoSeedingParityImporter());

    $this->seed(DemoDataSeeder::class);

    expect(demoSeedingTableCounts())->toMatchArray([
        'currencies' => 12,
        'exchange_rates' => 11,
        'countries' => 27,
        'brands' => 13,
        'categories' => 9,
        'products' => 46,
        'merchants' => 13,
        'merchant_products' => 267,
        'offers' => 267,
        'coupons' => 28,
        'product_compliance_rules' => 46 * 27,
        'merchant_trust_signals' => 13,
        'market_price_stats' => 46 * 365,
    ])
        ->and(DB::table('price_snapshots')->count())->toBeGreaterThan(267)
        ->and(DB::table('price_snapshots')->where('reason', 'first_seen')->count())->toBe(267);

    // Prototype ids are preserved and the shift is zero at anchor = seed.NOW.
    $offer = Offer::query()->findOrFail(1);
    expect($offer->merchant_product_id)->toBe(1)
        ->and($offer->price_minor)->toBe(3595)
        ->and($offer->source_updated_at->getTimestamp())->toBe(intdiv(1788642000000, 1000))
        ->and(Offer::query()->findOrFail(59)->anomaly)->toBe(PriceAnomaly::TooLow)
        ->and(Offer::query()->where('link_status', LinkStatus::Broken)->count())->toBe(1)
        ->and(Offer::query()->whereColumn('offers.merchant_product_id', '!=', 'offers.id')->count())->toBe(0);

    $product = Product::query()->findOrFail(1);
    expect($product->slug)->toBe('performance-alpha')
        ->and($product->pack_quantity)->toBe('360.000')
        ->and($product->pack_unit)->toBe('g')
        ->and($product->rrp_minor)->toBe(4870)
        ->and($product->variants()->count())->toBe(6);

    // Every prototype merchant is listable; verification is kept.
    expect(Merchant::query()->where('status', '!=', MerchantStatus::Active)->count())->toBe(0)
        ->and(Merchant::query()->findOrFail(7)->isVerified())->toBeFalse()
        ->and(Merchant::query()->findOrFail(1)->verified_at?->getTimestamp())->toBe(intdiv(1781686800000, 1000));

    expect(Coupon::query()->findOrFail(2)->type)->toBe(CouponType::FreeShipping)
        ->and(Coupon::query()->findOrFail(1)->amount_off_minor)->toBe(600);

    // Explicit rules survive; everything else is an explicit default `allowed`.
    expect(DB::table('product_compliance_rules')->where('status', '!=', ComplianceStatus::Allowed->value)->count())->toBeGreaterThan(0)
        ->and(DB::table('product_compliance_rules')->where('source', PrototypeSnapshotImporter::DEFAULT_RULE_SOURCE)->count())->toBe(46 * 27 - 65);

    // The last market-stat day is the anchor day.
    expect(DB::table('market_price_stats')->max('stat_date'))->toBe('2026-09-06');

    // Brand aliases: 4 of the 5 seed sets resolve to a seeded brand ("Peak
    // Labs" doesn't exist), each alias kept, seed status and prototype_demo source.
    expect(DB::table('brand_aliases')->count())->toBe(9)
        ->and(DB::table('brand_aliases')->where('source', PrototypeSnapshotImporter::SOURCE)->count())->toBe(9)
        ->and(DB::table('brand_aliases')->where('status', BrandAliasStatus::Approved->value)->count())->toBe(7)
        ->and(DB::table('brand_aliases')->where('status', BrandAliasStatus::Suggested->value)->count())->toBe(2);

    // Listed ingredients (matcher input, prototype `p.ingredients`) are flagged
    // distinctly from dose-only rows; in this seed every dose ingredient is
    // also listed, so all rows are is_listed = true.
    expect(DB::table('ingredient_product')->where('is_listed', true)->count())->toBe(DB::table('ingredient_product')->count())
        ->and(DB::table('ingredient_product')->where('is_listed', false)->count())->toBe(0);

    // Every demo listing is already linked (no feed pipeline ran for it).
    expect(MerchantProduct::query()->where('match_status', ListingMatchStatus::Auto)->count())->toBe(267)
        ->and(MerchantProduct::query()->whereNotNull('match_score')->count())->toBe(0)
        ->and(MerchantProduct::query()->whereNull('matched_at')->count())->toBe(0);

    // Demo offers are tagged distinctly from real feed-published offers.
    expect(Offer::query()->where('source', SnapshotSource::PrototypeDemo->value)->count())->toBe(267);
});

it('leaves ranking configuration and commercial data untouched', function () {
    app()->instance(PrototypeSnapshotImporter::class, demoSeedingParityImporter());

    $this->seed(DemoDataSeeder::class);

    $active = RankingVersion::query()->where('is_active', true)->sole();
    expect($active->version)->toBe('prototype-v1')
        ->and($active->toRankingWeights()->weights)->toBe(RankingWeights::PROTOTYPE_DEFAULTS)
        ->and(RankingVersion::query()->count())->toBe(1);

    $merchantColumns = Schema::getColumnListing('merchants');
    expect($merchantColumns)->not->toContain('affiliate')
        ->and($merchantColumns)->not->toContain('tier')
        ->and($merchantColumns)->not->toContain('commission');
});

it('is idempotent when run twice with the same anchor', function () {
    $importer = demoSeedingParityImporter();

    $importer->run();
    $first = demoSeedingTableCounts();
    $importer->run();

    // The only expected report finding is the "Peak Labs" alias set, whose
    // canonical brand isn't seeded; it is noted (not silently dropped) on
    // every run, so the category grows by one note per run, not per row.
    expect(demoSeedingTableCounts())->toBe($first)
        ->and($importer->report()->count('Brand alias set with unknown canonical brand (skipped)'))->toBe(2)
        ->and($importer->report()->summary())->toHaveCount(1);
});

it('shifts every prototype timestamp by anchor minus seed.NOW', function () {
    $anchor = new DateTimeImmutable('@'.(intdiv(1788685200000, 1000) + 10 * 86400));
    $importer = PrototypeSnapshotImporter::fromConfig($anchor);

    $importer->importReferenceData();
    $importer->importCatalogue();
    $importer->importMerchants();
    $importer->importOffers();

    expect(Offer::query()->findOrFail(1)->source_updated_at->getTimestamp())->toBe(intdiv(1788642000000, 1000) + 10 * 86400)
        ->and(Coupon::query()->findOrFail(1)->ends_at->getTimestamp())->toBe(intdiv(1788944400000, 1000) + 10 * 86400)
        ->and(DB::table('exchange_rates')->value('effective_at'))->toBe('2026-09-16 09:00:00');
});

it('creates the demo personas with their roles', function () {
    app()->instance(PrototypeSnapshotImporter::class, demoSeedingParityImporter());

    $this->seed(DemoDataSeeder::class);

    $merchantUser = User::query()->where('email', DemoAccountsSeeder::MERCHANT_EMAIL)->sole();
    $staff = User::query()->where('email', DemoAccountsSeeder::STAFF_EMAIL)->sole();

    expect(User::query()->whereIn('email', [DemoAccountsSeeder::SHOPPER_EMAIL, DemoAccountsSeeder::MERCHANT_EMAIL, DemoAccountsSeeder::STAFF_EMAIL])->whereNotNull('email_verified_at')->count())->toBe(3)
        ->and(Merchant::query()->findOrFail(1)->members()->whereKey($merchantUser->id)->first()?->pivot->role)->toBe('owner')
        ->and(DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $staff->id)
            ->where('roles.name', StaffRole::SuperAdmin->value)
            ->exists())->toBeTrue()
        ->and(Hash::check('demo-password-for-tests', $staff->password))->toBeTrue();
});

it('refuses demo seeding in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(DemoDataSeeder::class)->__invoke())->toThrow(DemoDataRefused::class)
        ->and(fn () => app(DatabaseSeeder::class)->__invoke())->toThrow(DemoDataRefused::class)
        ->and(fn () => app(DemoAccountsSeeder::class)->__invoke())->toThrow(DemoDataRefused::class)
        ->and(fn () => demoSeedingParityImporter())->toThrow(DemoDataRefused::class)
        ->and(DB::table('products')->count())->toBe(0)
        ->and(DB::table('roles')->count())->toBe(0)
        ->and(DB::table('users')->count())->toBe(0);
});

it('allows the labelled demo dataset in staging but never in production', function (string $environment, bool $allowed) {
    app()->detectEnvironment(fn () => $environment);

    expect(DemoEnvironment::isAllowed())->toBe($allowed);
})->with([
    'local' => ['local', true],
    'demo' => ['demo', true],
    'staging (A-39)' => ['staging', true],
    'production' => ['production', false],
    'unknown' => ['preview', false],
]);

it('seeds only reference data when demo data is disabled', function () {
    config(['comparo.demo.enabled' => false]);

    app(DatabaseSeeder::class)->__invoke();

    expect(DB::table('roles')->where('name', StaffRole::SuperAdmin->value)->exists())->toBeTrue()
        ->and(DB::table('products')->count())->toBe(0)
        ->and(DB::table('users')->count())->toBe(0);
});
