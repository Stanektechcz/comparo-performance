<?php

namespace App\Domain\Platform\PrototypeImport;

use App\Domain\Catalog\ProductStatus;
use App\Domain\Compliance\ComplianceStatus;
use App\Domain\Merchants\MerchantStatus;
use App\Domain\Offers\LinkStatus;
use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Domain\Pricing\History\SnapshotSource;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deterministic import of the prototype's fictional catalogue into the real schema.
 *
 * - Prototype ids are preserved for brands, categories, products, merchants,
 *   offers (and their merchant listings) and coupons, so parity tests can look
 *   rows up by prototype id.
 * - Every timestamp is shifted by (anchor − seed.NOW); see TimeShift.
 * - Idempotent: reference rows are upserted, pivots insert-or-ignore, and
 *   append-only histories are only written for owners without demo rows yet.
 * - Commercial prototype data (affiliate, tier, partner, subscriptions,
 *   payments, sponsored, clicks) is never read: it must not reach ranking.
 *
 * Only ever runs in local/testing/demo environments (DemoEnvironment).
 */
final class PrototypeSnapshotImporter
{
    public const string SOURCE = SnapshotSource::PrototypeDemo->value;

    public const string CURRENCY = 'EUR';

    public const string DEFAULT_RULE_REASON = 'No country-specific restriction on record.';

    public const string DEFAULT_RULE_SOURCE = 'Default policy (prototype demo import)';

    /**
     * @var array<string, string>
     */
    private const array CURRENCY_NAMES = [
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
        'GBP' => 'Pound Sterling',
        'CZK' => 'Czech Koruna',
        'PLN' => 'Polish Zloty',
        'SEK' => 'Swedish Krona',
        'DKK' => 'Danish Krone',
        'NOK' => 'Norwegian Krone',
        'CHF' => 'Swiss Franc',
        'HUF' => 'Hungarian Forint',
        'RON' => 'Romanian Leu',
        'BGN' => 'Bulgarian Lev',
    ];

    /**
     * Tables whose primary keys come from the prototype (PostgreSQL sequences must follow).
     *
     * @var list<string>
     */
    private const array EXPLICIT_ID_TABLES = ['brands', 'categories', 'products', 'merchants', 'merchant_products', 'offers', 'coupons'];

    private readonly PrototypeSnapshot $snapshot;

    private readonly TimeShift $time;

    private readonly ImportReport $report;

    /**
     * @throws DemoDataRefused outside local/testing/demo
     * @throws PrototypeImportException when the snapshot cannot be read
     */
    public function __construct(string $snapshotPath, ?DateTimeImmutable $anchor = null)
    {
        DemoEnvironment::assertAllowed();

        $this->snapshot = PrototypeSnapshot::fromFile($snapshotPath);
        $this->time = TimeShift::between($this->snapshot->nowMs, $anchor ?? TimeShift::utcNow());
        $this->report = new ImportReport;
    }

    public static function fromConfig(?DateTimeImmutable $anchor = null): self
    {
        return new self((string) config('comparo.demo.snapshot'), $anchor);
    }

    /**
     * The prototype's frozen clock (seed.NOW) as a date, e.g. for a zero-shift parity import.
     */
    public static function seedNow(string $snapshotPath): DateTimeImmutable
    {
        return CarbonImmutable::createFromTimestampUTC(intdiv(PrototypeSnapshot::fromFile($snapshotPath)->nowMs, 1000))->toDateTimeImmutable();
    }

    public function anchor(): CarbonImmutable
    {
        return $this->time->anchor;
    }

    public function report(): ImportReport
    {
        return $this->report;
    }

    /**
     * All stages, in dependency order, atomically.
     */
    public function run(): ImportReport
    {
        DB::transaction(function (): void {
            $this->importReferenceData();
            $this->importCatalogue();
            $this->importMerchants();
            $this->importOffers();
            $this->importCompliance();
            $this->importPriceHistory();
        });

        return $this->report;
    }

    /**
     * Currencies, EUR exchange rates and countries (markets).
     */
    public function importReferenceData(): void
    {
        $now = $this->time->anchorDb();
        $currencies = [];
        $rates = [];

        foreach ($this->snapshot->records('currencies') as $currency) {
            $code = (string) $currency['code'];
            $currencies[] = [
                'code' => $code,
                'name' => self::CURRENCY_NAMES[$code] ?? $code,
                'symbol' => (string) $currency['symbol'],
                'minor_unit' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($code !== self::CURRENCY) {
                $rates[] = [
                    'base_currency' => self::CURRENCY,
                    'quote_currency' => $code,
                    'rate' => (string) $currency['rate'],
                    'source' => self::SOURCE,
                    'effective_at' => $now,
                    'created_at' => $now,
                ];
            }
        }

        ChunkedWriter::upsert('currencies', $currencies, ['code']);
        ChunkedWriter::upsert('exchange_rates', $rates, ['base_currency', 'quote_currency', 'effective_at'], ['rate', 'source']);

        $currencyIds = $this->idsByCode('currencies');
        $countries = [];

        foreach ($this->snapshot->records('countries') as $country) {
            $customs = $country['customs'] ?? null;
            $countries[] = [
                'code' => (string) $country['iso'],
                'name' => (string) $country['name'],
                'currency_id' => $currencyIds[$country['currency']] ?? throw PrototypeImportException::missingReference('currency', (string) $country['currency']),
                'default_locale' => (string) $country['lang'],
                'region' => $country['region'] ?? null,
                'is_eu' => (bool) ($country['eu'] ?? false),
                'standard_vat_rate' => $country['vat'] ?? null,
                'minimum_age' => $country['age'] ?? null,
                'customs_note' => $customs === null || is_string($customs) ? $customs : json_encode($customs, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ChunkedWriter::upsert('countries', $countries, ['code']);
    }

    /**
     * Brands, categories, ingredients, products, variants and label doses.
     */
    public function importCatalogue(): void
    {
        $now = $this->time->anchorDb();

        ChunkedWriter::upsert('brands', array_map(fn (array $brand): array => [
            'id' => (int) $brand['id'],
            'slug' => (string) $brand['slug'],
            'name' => (string) $brand['name'],
            'origin_country_code' => $brand['country'] ?? null,
            'description' => $brand['desc'] ?? null,
            'founded_year' => $brand['founded'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->snapshot->records('brands')), ['id']);

        ChunkedWriter::upsert('categories', array_map(fn (array $category): array => [
            'id' => (int) $category['id'],
            'parent_id' => null,
            'slug' => (string) $category['slug'],
            'name' => (string) $category['name'],
            'description' => $category['context'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->snapshot->records('categories')), ['id']);

        $this->importIngredients($now);

        ChunkedWriter::upsert('products', array_map(fn (array $product): array => $this->productRow($product, $now), $this->snapshot->records('products')), ['id']);

        $this->resetSequences(['brands', 'categories', 'products']);
        $this->importVariants($now);
        $this->importDoses();
    }

    /**
     * Merchants, shipping zones, the first trust measurement and risk events.
     * All prototype merchants become `active` (the prototype compared 'pending' shops too).
     */
    public function importMerchants(): void
    {
        $now = $this->time->anchorDb();
        $countryIds = $this->idsByCode('countries');
        $merchants = [];
        $zones = [];

        foreach ($this->snapshot->records('merchants') as $merchant) {
            $id = (int) $merchant['id'];
            $rating = $this->snapshot->rating('merchantRatings', $id);
            $merchants[] = [
                'id' => $id,
                'slug' => (string) $merchant['slug'],
                'name' => (string) $merchant['name'],
                'website' => $merchant['web'] ?? null,
                'home_country_code' => $merchant['country'] ?? null,
                'status' => MerchantStatus::Active->value,
                'verified_at' => ($merchant['verified'] ?? false) === true ? $this->time->db($merchant['created'] ?? null) ?? $now : null,
                'free_shipping_threshold_minor' => self::minor($merchant['freeOverEur'] ?? null),
                'currency' => self::CURRENCY,
                'return_days' => $merchant['returnDays'] ?? null,
                'description' => $merchant['desc'] ?? null,
                'rating_average' => $merchant['rating'] ?? null,
                'rating_count' => (int) ($merchant['reviews'] ?? 0),
                'weighted_rating' => $rating['average'],
                'rating_source' => self::SOURCE,
                'created_at' => $this->time->db($merchant['created'] ?? null) ?? $now,
                'updated_at' => $now,
            ];

            foreach ((array) ($merchant['zones'] ?? []) as $code => $zone) {
                if (! isset($countryIds[$code])) {
                    $this->report->note('Unknown shipping-zone country (skipped)', "merchant {$id} → {$code}");

                    continue;
                }

                $zones[] = [
                    'merchant_id' => $id,
                    'country_id' => $countryIds[$code],
                    'cost_minor' => self::minor($zone['cost'] ?? 0),
                    'currency' => self::CURRENCY,
                    'min_days' => (int) ($zone['days'][0] ?? 0),
                    'max_days' => (int) ($zone['days'][1] ?? $zone['days'][0] ?? 0),
                    'carrier' => $zone['carrier'] ?? null,
                    'duties_apply' => (bool) ($zone['duty'] ?? false),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        ChunkedWriter::upsert('merchants', $merchants, ['id']);
        $this->resetSequences(['merchants']);
        ChunkedWriter::upsert('merchant_shipping_zones', $zones, ['merchant_id', 'country_id']);

        $this->importTrustSignals($now);
        $this->importRiskEvents();
    }

    /**
     * Merchant listings (id = offer id), offers, coupons and coupon markets.
     */
    public function importOffers(): void
    {
        $now = $this->time->anchorDb();
        $products = [];
        foreach ($this->snapshot->records('products') as $product) {
            $products[(int) $product['id']] = $product;
        }

        $listings = [];
        $offers = [];
        $seenSkus = [];

        foreach ($this->snapshot->records('offers') as $offer) {
            $id = (int) $offer['id'];
            $productId = (int) $offer['productId'];
            $merchantId = (int) $offer['merchantId'];
            $product = $products[$productId] ?? throw PrototypeImportException::missingReference('product', (string) $productId);
            $sku = (string) ($offer['merchantSku'] ?? "OFFER-{$id}");

            if (isset($seenSkus[$merchantId][$sku])) {
                $this->report->note('Duplicate merchant SKU (suffixed with -{offerId})', "merchant {$merchantId} sku {$sku} offer {$id}");
                $sku .= "-{$id}";
            }
            $seenSkus[$merchantId][$sku] = true;

            $listings[] = [
                'id' => $id,
                'merchant_id' => $merchantId,
                'product_id' => $productId,
                'merchant_sku' => $sku,
                'title' => trim($product['name'].' '.($offer['variant'] ?? '')),
                'ean' => $offer['ean'] ?? null,
                'url' => $offer['url'] ?? null,
                'first_seen_at' => $this->time->db($product['created'] ?? $offer['updated'] ?? null),
                'last_seen_at' => $this->time->db($offer['updated'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $offers[] = $this->offerRow($offer, $now);
        }

        ChunkedWriter::upsert('merchant_products', $listings, ['id']);
        ChunkedWriter::upsert('offers', $offers, ['id']);
        $this->resetSequences(['merchant_products', 'offers']);

        $this->importCoupons($now);
    }

    /**
     * Explicit prototype rules, then an explicit `allowed` row for every other
     * product × country (the prototype defaulted to allowed; production defaults to unknown).
     */
    public function importCompliance(): void
    {
        $now = $this->time->anchorDb();
        $countryIds = $this->idsByCode('countries');
        $productIds = array_map(static fn (array $product): int => (int) $product['id'], $this->snapshot->records('products'));
        $knownProducts = array_flip($productIds);
        $explicit = [];
        $covered = [];

        foreach ($this->snapshot->records('complianceRules') as $rule) {
            $productId = (int) $rule['productId'];
            $code = (string) $rule['country'];
            $status = ComplianceStatus::tryFrom((string) $rule['status']);

            if (! isset($knownProducts[$productId], $countryIds[$code]) || $status === null) {
                $this->report->note('Unusable compliance rule (skipped)', "rule {$rule['id']}: product {$productId} / {$code} / {$rule['status']}");

                continue;
            }

            $covered[$productId][$countryIds[$code]] = true;
            $explicit[] = [
                'product_id' => $productId,
                'country_id' => $countryIds[$code],
                'status' => $status->value,
                'reason' => $rule['reason'] ?? null,
                'source' => $rule['source'] ?? null,
                'reviewer_label' => $rule['reviewedBy'] ?? null,
                'reviewed_at' => $this->time->db($rule['reviewedAt'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ChunkedWriter::upsert('product_compliance_rules', $explicit, ['product_id', 'country_id']);

        $defaults = [];
        foreach ($productIds as $productId) {
            foreach ($countryIds as $countryId) {
                if (isset($covered[$productId][$countryId])) {
                    continue;
                }

                $defaults[] = [
                    'product_id' => $productId,
                    'country_id' => $countryId,
                    'status' => ComplianceStatus::Allowed->value,
                    'reason' => self::DEFAULT_RULE_REASON,
                    'source' => self::DEFAULT_RULE_SOURCE,
                    'reviewer_label' => null,
                    'reviewed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        ChunkedWriter::insertOrIgnore('product_compliance_rules', $defaults);
    }

    public function importPriceHistory(): void
    {
        (new PriceHistoryImport($this->snapshot, $this->time, $this->report))->run();
    }

    private function importIngredients(string $now): void
    {
        $bySlug = [];

        foreach ($this->ingredientNames() as $name) {
            $slug = Str::slug($name);

            if (isset($bySlug[$slug]) && $bySlug[$slug]['name'] !== $name) {
                $this->report->note('Ingredient slug collision (first name kept)', "{$name} → {$slug}");

                continue;
            }

            $bySlug[$slug] = ['slug' => $slug, 'name' => $name, 'created_at' => $now, 'updated_at' => $now];
        }

        ChunkedWriter::upsert('ingredients', array_values($bySlug), ['slug']);
    }

    /**
     * seed.ingredients ∪ every product.ingredients ∪ every product.doses[].ingredient, first-seen order.
     *
     * @return list<string>
     */
    private function ingredientNames(): array
    {
        $names = $this->snapshot->strings('ingredients');

        foreach ($this->snapshot->records('products') as $product) {
            foreach ((array) ($product['ingredients'] ?? []) as $name) {
                $names[] = (string) $name;
            }
            foreach ((array) ($product['doses'] ?? []) as $dose) {
                $names[] = (string) $dose['ingredient'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function productRow(array $product, string $now): array
    {
        $id = (int) $product['id'];
        $pack = PackLabel::parse($product['pack'] ?? null);
        $rating = $this->snapshot->rating('productRatings', $id);

        return [
            'id' => $id,
            'brand_id' => (int) $product['brandId'],
            'category_id' => (int) $product['categoryId'],
            'slug' => (string) $product['slug'],
            'name' => (string) $product['name'],
            'ean' => $product['ean'] ?? null,
            'reference' => $product['sku'] ?? null,
            'pack_label' => (string) ($product['pack'] ?? ''),
            'pack_quantity' => $pack->quantity,
            'pack_unit' => $pack->unit,
            'servings' => $product['servings'] ?? null,
            'short_description' => $product['short'] ?? null,
            'description' => $product['desc'] ?? null,
            'rrp_minor' => self::minor($product['rrp'] ?? null),
            'rrp_currency' => isset($product['rrp']) ? self::CURRENCY : null,
            'dose_source' => $product['doseSource'] ?? null,
            'dose_updated_at' => $this->time->db($product['doseUpdated'] ?? null),
            'weighted_rating' => $rating['average'],
            'rating_count' => $rating['count'],
            'rating_source' => self::SOURCE,
            'status' => ProductStatus::Active->value,
            'merged_into_id' => null,
            'merged_at' => null,
            'created_at' => $this->time->db($product['created'] ?? null) ?? $now,
            'updated_at' => $now,
        ];
    }

    private function importVariants(string $now): void
    {
        $rows = [];

        foreach ($this->snapshot->records('products') as $product) {
            $productId = (int) $product['id'];
            $groups = ['flavour' => (array) ($product['variants'] ?? []), 'pack' => (array) ($product['packs'] ?? [])];

            foreach ($groups as $kind => $names) {
                foreach (array_values($names) as $position => $name) {
                    $slug = Str::slug((string) $name);
                    $key = "{$productId}|{$kind}|{$slug}";

                    if (isset($rows[$key])) {
                        $this->report->note('Duplicate variant slug (skipped)', "product {$productId} {$kind} {$name}");

                        continue;
                    }

                    $rows[$key] = [
                        'product_id' => $productId,
                        'kind' => $kind,
                        'slug' => $slug,
                        'name' => (string) $name,
                        'ean' => null,
                        'position' => $position,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        ChunkedWriter::upsert('product_variants', array_values($rows), ['product_id', 'kind', 'slug']);
    }

    /**
     * Label doses first (in label order), then undisclosed ingredients with a null amount.
     */
    private function importDoses(): void
    {
        $ingredientIds = DB::table('ingredients')->pluck('id', 'name')->all();
        $rows = [];

        foreach ($this->snapshot->records('products') as $product) {
            $productId = (int) $product['id'];
            $position = 0;
            $linked = [];

            foreach ((array) ($product['doses'] ?? []) as $dose) {
                $name = (string) $dose['ingredient'];
                $ingredientId = $ingredientIds[$name] ?? throw PrototypeImportException::missingReference('ingredient', $name);
                $linked[$name] = true;
                $rows[] = [
                    'product_id' => $productId,
                    'ingredient_id' => $ingredientId,
                    'amount_mg' => $dose['mg'] ?? null,
                    'is_carrier' => (bool) ($dose['carrier'] ?? false),
                    'nrv_percent' => $dose['nrv'] ?? null,
                    'position' => $position++,
                ];
            }

            foreach ((array) ($product['ingredients'] ?? []) as $name) {
                if (isset($linked[$name])) {
                    continue;
                }

                $linked[$name] = true;
                $rows[] = [
                    'product_id' => $productId,
                    'ingredient_id' => $ingredientIds[$name] ?? throw PrototypeImportException::missingReference('ingredient', (string) $name),
                    'amount_mg' => null,
                    'is_carrier' => false,
                    'nrv_percent' => null,
                    'position' => $position++,
                ];
            }
        }

        ChunkedWriter::insertOrIgnore('ingredient_product', $rows);
    }

    /**
     * One append-only measurement per merchant, only if it has no demo measurement yet.
     */
    private function importTrustSignals(string $now): void
    {
        $measured = DB::table('merchant_trust_signals')->where('source', self::SOURCE)->distinct()->pluck('merchant_id')->flip()->all();
        $rows = [];

        foreach ($this->snapshot->records('merchants') as $merchant) {
            $id = (int) $merchant['id'];
            $ix = (array) ($merchant['ix'] ?? []);

            if (isset($measured[$id])) {
                continue;
            }

            $rows[] = [
                'merchant_id' => $id,
                'business_verified' => (bool) ($ix['bizVerified'] ?? false),
                'account_age_days' => $ix['accountAgeDays'] ?? null,
                'verified_review_ratio' => $ix['verifiedReviewRatio'] ?? null,
                'complaint_rate' => $ix['complaintRate'] ?? null,
                'complaint_resolution_rate' => $ix['resolution'] ?? null,
                'response_rate' => $ix['responseRate'] ?? null,
                'verified_order_rate' => $ix['verifiedOrderRate'] ?? null,
                'price_accuracy' => $ix['priceAccuracy'] ?? null,
                'feed_uptime' => $ix['feedUptime'] ?? null,
                'shipping_accuracy' => $ix['shipAccuracy'] ?? null,
                'broken_link_rate' => $ix['brokenLinkRate'] ?? null,
                'community_reports' => (int) ($ix['communityReports'] ?? 0),
                'delivery_on_time' => $ix['deliveryOnTime'] ?? null,
                'source' => self::SOURCE,
                'measured_at' => $now,
                'created_at' => $now,
            ];
        }

        ChunkedWriter::insertOrIgnore('merchant_trust_signals', $rows);
    }

    /**
     * Keyed by (merchant, kind, description) so a re-import refreshes instead of duplicating.
     */
    private function importRiskEvents(): void
    {
        foreach ((array) ($this->snapshot->section('ix')['riskEvents'] ?? []) as $event) {
            DB::table('merchant_risk_events')->updateOrInsert(
                ['merchant_id' => (int) $event['merchantId'], 'kind' => (string) $event['kind'], 'description' => $event['text'] ?? null],
                ['severity' => (string) $event['severity'], 'detected_at' => $this->time->db($event['ts'] ?? null) ?? $this->time->anchorDb(), 'created_at' => $this->time->anchorDb()],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    private function offerRow(array $offer, string $now): array
    {
        $ix = (array) ($offer['ix'] ?? []);
        $linkStatus = match ($ix['link'] ?? null) {
            404, '404' => LinkStatus::Broken,
            'notrack' => LinkStatus::MissingTracking,
            default => LinkStatus::Ok,
        };

        return [
            'id' => (int) $offer['id'],
            'merchant_product_id' => (int) $offer['id'],
            'product_id' => (int) $offer['productId'],
            'merchant_id' => (int) $offer['merchantId'],
            'variant_label' => $offer['variant'] ?? null,
            'pack_label' => $offer['pack'] ?? null,
            'price_minor' => self::minor($offer['price']),
            'currency' => self::CURRENCY,
            'reference_price_minor' => empty($offer['oldPrice']) ? null : self::minor($offer['oldPrice']),
            'reference_price_raised_at' => $this->time->db($ix['refPriceRaisedAt'] ?? null),
            'availability' => (string) $offer['availability'],
            'stock_quantity' => $offer['stock'] ?? null,
            'warehouse_country_code' => $offer['warehouse'] ?? null,
            'url' => (string) $offer['url'],
            'anomaly' => $ix['anomaly'] ?? null,
            'anomaly_reference_minor' => empty($ix['marketMedian']) ? null : self::minor($ix['marketMedian']),
            'link_status' => $linkStatus->value,
            'is_active' => true,
            'source_updated_at' => $this->time->db($offer['updated'] ?? null) ?? $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function importCoupons(string $now): void
    {
        $countryIds = $this->idsByCode('countries');
        $coupons = [];
        $markets = [];

        foreach ($this->snapshot->records('coupons') as $coupon) {
            $id = (int) $coupon['id'];
            $ix = (array) ($coupon['ix'] ?? []);
            $type = $coupon['type'] === 'freeship' ? CouponType::FreeShipping : CouponType::from((string) $coupon['type']);
            $coupons[] = [
                'id' => $id,
                'merchant_id' => (int) $coupon['merchantId'],
                'code' => (string) $coupon['code'],
                'title' => $coupon['title'] ?? null,
                'type' => $type->value,
                'percent_off' => $type === CouponType::Percent ? $coupon['value'] : null,
                'amount_off_minor' => $type === CouponType::Fixed ? self::minor($coupon['value']) : null,
                'currency' => self::CURRENCY,
                'min_order_minor' => self::minor($coupon['minOrder'] ?? 0),
                'starts_at' => $this->time->db($coupon['starts'] ?? null),
                'ends_at' => $this->time->db($coupon['ends']),
                'is_exclusive' => (bool) ($coupon['exclusive'] ?? false),
                'verification_state' => (CouponState::tryFrom((string) ($ix['state'] ?? '')) ?? CouponState::Unverified)->value,
                'last_verified_at' => $this->time->db($coupon['verifiedAt'] ?? null),
                'verified_by' => $ix['verifiedBy'] ?? null,
                'reports_worked' => (int) ($ix['reports']['worked'] ?? 0),
                'reports_failed' => (int) ($ix['reports']['failed'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ((array) ($coupon['countries'] ?? []) as $code) {
                if (! isset($countryIds[$code])) {
                    $this->report->note('Unknown coupon country (skipped)', "coupon {$id} → {$code}");

                    continue;
                }

                $markets[] = ['coupon_id' => $id, 'country_id' => $countryIds[$code]];
            }
        }

        ChunkedWriter::upsert('coupons', $coupons, ['id']);
        $this->resetSequences(['coupons']);
        ChunkedWriter::insertOrIgnore('coupon_country', $markets);
    }

    /**
     * @return array<string, int>
     */
    private function idsByCode(string $table): array
    {
        return DB::table($table)->pluck('id', 'code')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * After explicit-id inserts, PostgreSQL sequences must continue past the highest id.
     *
     * @param  list<string>  $tables
     */
    private function resetSequences(array $tables): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_intersect($tables, self::EXPLICIT_ID_TABLES) as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), (SELECT COALESCE(MAX(id), 1) FROM {$table}))");
        }
    }

    /**
     * EUR float (≤ 2 decimals) → integer cents.
     */
    public static function minor(int|float|string|null $amount): ?int
    {
        return $amount === null ? null : (int) round((float) $amount * 100);
    }
}
