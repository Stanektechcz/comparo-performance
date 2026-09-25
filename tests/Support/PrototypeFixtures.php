<?php

namespace Tests\Support;

use App\Domain\Catalog\Completeness\ProductFacts;
use App\Domain\Matching\Engine\BrandAliasSet;
use App\Domain\Matching\Engine\CandidateProduct;
use App\Domain\Matching\Engine\FeedItemFacts;
use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Matching\Engine\MatchPart;
use App\Domain\Matching\Engine\MatchPartLabel;
use App\Domain\Matching\Engine\MatchResult;
use App\Domain\Matching\Engine\ProductMatcher;
use App\Domain\Merchants\Risk\RiskInput;
use App\Domain\Merchants\Risk\RiskLevel;
use App\Domain\Merchants\Trust\TrustSignals;
use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Domain\Pricing\LandedPrice\CouponTerms;
use App\Domain\Pricing\LandedPrice\LandedPriceInput;
use App\Domain\Pricing\LandedPrice\ShippingTerms;
use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Access to the golden fixtures exported from the prototype by
 * tools/prototype-parity/export-fixtures.mjs, plus the (test-only) mapping
 * from prototype seed entities to domain inputs.
 */
final class PrototypeFixtures
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array<string, mixed>
     */
    public static function load(string $name): array
    {
        return self::$cache[$name] ??= json_decode(
            (string) file_get_contents(dirname(__DIR__)."/Fixtures/PrototypeParity/{$name}.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function seed(): array
    {
        return (self::$cache['__seed'] ??= json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/database/data/prototype/seed-snapshot.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        ))['seed'];
    }

    public static function now(): DateTimeImmutable
    {
        return self::at(self::seed()['NOW']);
    }

    public static function at(int|float $epochMilliseconds): DateTimeImmutable
    {
        return (new DateTimeImmutable('@'.intdiv((int) $epochMilliseconds, 1000)))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Prototype amounts are EUR floats with at most two decimals.
     */
    public static function minor(int|float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @return array<string, mixed>
     */
    public static function merchant(int $id): array
    {
        return self::indexed('merchants')[$id];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function indexed(string $collection): array
    {
        return self::$cache["__index_{$collection}"] ??= array_column(self::seed()[$collection], null, 'id');
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public static function landedPriceInput(array $offer, string $market): LandedPriceInput
    {
        $merchant = self::merchant($offer['merchantId']);
        $zone = $merchant['zones'][$market] ?? null;
        $coupons = array_values(array_filter(
            self::seed()['coupons'],
            static fn (array $coupon): bool => $coupon['merchantId'] === $merchant['id'],
        ));

        return new LandedPriceInput(
            priceMinor: self::minor($offer['price']),
            currency: 'EUR',
            marketCode: $market,
            priceFlagged: ! empty($offer['ix']['anomaly']) || ! ($offer['price'] > 0),
            shipping: $zone === null ? null : new ShippingTerms(self::minor($zone['cost']), 'EUR', $zone['days'][0], $zone['days'][1], $zone['carrier'] ?? null),
            freeShippingThresholdMinor: isset($merchant['freeOverEur']) ? self::minor($merchant['freeOverEur']) : null,
            coupons: array_map(self::couponTerms(...), $coupons),
        );
    }

    /**
     * @param  array<string, mixed>  $coupon
     */
    public static function couponTerms(array $coupon): CouponTerms
    {
        $type = match ($coupon['type']) {
            'percent' => CouponType::Percent,
            'fixed' => CouponType::Fixed,
            default => CouponType::FreeShipping,
        };

        return new CouponTerms(
            id: $coupon['id'],
            code: $coupon['code'],
            title: $coupon['title'] ?? null,
            type: $type,
            percentOffBasisPoints: $type === CouponType::Percent ? (int) round($coupon['value'] * 100) : null,
            amountOffMinor: $type === CouponType::Fixed ? self::minor($coupon['value']) : null,
            minOrderMinor: self::minor($coupon['minOrder'] ?? 0),
            currency: 'EUR',
            marketCodes: $coupon['countries'],
            startsAt: isset($coupon['starts']) ? self::at($coupon['starts']) : null,
            endsAt: self::at($coupon['ends']),
            state: CouponState::from($coupon['ix']['state'] ?? 'unverified'),
            exclusive: (bool) ($coupon['exclusive'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $merchant
     */
    public static function trustSignals(array $merchant): TrustSignals
    {
        $ix = $merchant['ix'] ?? [];

        return new TrustSignals(
            businessVerified: (bool) ($ix['bizVerified'] ?? false),
            merchantVerified: (bool) ($merchant['verified'] ?? false),
            rating: (float) $merchant['rating'],
            reviewCount: (int) $merchant['reviews'],
            accountAgeDays: $ix['accountAgeDays'] ?? null,
            verifiedReviewRatio: self::float($ix['verifiedReviewRatio'] ?? null),
            complaintRate: self::float($ix['complaintRate'] ?? null),
            complaintResolutionRate: self::float($ix['resolution'] ?? null),
            responseRate: self::float($ix['responseRate'] ?? null),
            verifiedOrderRate: self::float($ix['verifiedOrderRate'] ?? null),
            priceAccuracy: self::float($ix['priceAccuracy'] ?? null),
            feedUptime: self::float($ix['feedUptime'] ?? null),
            shippingAccuracy: self::float($ix['shipAccuracy'] ?? null),
            brokenLinkRate: self::float($ix['brokenLinkRate'] ?? null),
            communityReports: (int) ($ix['communityReports'] ?? 0),
            deliveryOnTime: self::float($ix['deliveryOnTime'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $merchant
     */
    public static function riskInput(array $merchant): RiskInput
    {
        $ix = $merchant['ix'] ?? [];
        $events = array_values(array_filter(
            self::seed()['ix']['riskEvents'] ?? [],
            static fn (array $event): bool => $event['merchantId'] === $merchant['id'],
        ));
        usort($events, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);

        return new RiskInput(
            businessVerified: (bool) ($ix['bizVerified'] ?? false),
            complaintRate: self::float($ix['complaintRate'] ?? null),
            feedUptime: self::float($ix['feedUptime'] ?? null),
            brokenLinkRate: self::float($ix['brokenLinkRate'] ?? null),
            priceAccuracy: self::float($ix['priceAccuracy'] ?? null),
            communityReports: $ix['communityReports'] ?? null,
            responseRate: self::float($ix['responseRate'] ?? null),
            events: array_map(static fn (array $event): array => [
                'kind' => $event['kind'],
                'severity' => RiskLevel::from($event['severity']),
                'description' => $event['text'] ?? '',
            ], $events),
        );
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function productFacts(array $product): ProductFacts
    {
        return new ProductFacts(
            hasEan: ! empty($product['ean']),
            hasBrand: ! empty($product['brandId']),
            hasPackSize: ! empty($product['pack']),
            hasCategory: ! empty($product['categoryId']),
            ingredientCount: count($product['ingredients'] ?? []),
            hasServings: ! empty($product['servings']),
            descriptionLength: mb_strlen($product['desc'] ?? ''),
            flavourVariantCount: count($product['variants'] ?? []),
        );
    }

    /**
     * @return list<int>
     */
    public static function dailyLows(array $product): array
    {
        return array_map(self::minor(...), $product['hist']['min']);
    }

    /**
     * The seed catalogue as matching candidates, in seed order (the prototype
     * scores `S.products` in array order), or only the given products in the
     * given order.
     *
     * @param  ?list<int>  $productIds
     * @return list<CandidateProduct>
     */
    public static function candidateProducts(?array $productIds = null): array
    {
        $products = $productIds === null
            ? self::seed()['products']
            : array_map(static fn (int $id): array => self::indexed('products')[$id], $productIds);

        return array_values(array_map(self::candidateProduct(...), $products));
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function candidateProduct(array $product): CandidateProduct
    {
        $brand = self::indexed('brands')[$product['brandId'] ?? 0] ?? [];

        return new CandidateProduct(
            productId: (int) $product['id'],
            name: self::string($product['name']),
            packLabel: self::string($product['pack']),
            brandName: self::string($brand['name'] ?? ''),
            ean: self::optionalString($product['ean'] ?? null),
            alternatePacks: array_map(self::string(...), $product['packs'] ?? []),
            variants: array_map(self::string(...), $product['variants'] ?? []),
            listedIngredients: array_map(self::string(...), $product['ingredients'] ?? []),
        );
    }

    /**
     * `S.ix.brandAliases`, in seed order.
     *
     * @return list<BrandAliasSet>
     */
    public static function brandAliasSets(): array
    {
        return array_map(static fn (array $set): BrandAliasSet => new BrandAliasSet(
            canonicalBrandName: self::string($set['canonical']),
            aliases: array_map(self::string(...), $set['aliases']),
        ), self::seed()['ix']['brandAliases'] ?? []);
    }

    /**
     * A prototype feed row (`S.feedItems` or a synthetic fixture item).
     *
     * @param  array<string, mixed>  $item
     */
    public static function feedItemFacts(array $item): FeedItemFacts
    {
        return new FeedItemFacts(
            rawTitle: self::string($item['raw'] ?? ''),
            ean: self::optionalString($item['ean'] ?? null),
            brandRaw: self::optionalString($item['brandRaw'] ?? null),
            packRaw: self::optionalString($item['packRaw'] ?? null),
            variantRaw: self::optionalString($item['variantRaw'] ?? null),
        );
    }

    /**
     * Runs the matching fixture cases through the engine under `$policy`.
     * `items` and `synthetic` are full matches (whole seed catalogue unless the
     * case lists its own products); `candidates` score one product in isolation.
     *
     * @param  list<string>  $sections
     * @return array<string, array{expected: array<string, mixed>, actual: array<string, mixed>}>
     */
    public static function matchingCases(MatchingPolicy $policy, array $sections = ['items', 'candidates', 'synthetic']): array
    {
        $fixture = self::load('matching');
        $matcher = new ProductMatcher;
        $aliases = self::brandAliasSets();
        $catalogue = self::candidateProducts();
        $candidates = array_column(array_map(static fn (CandidateProduct $c): array => ['id' => $c->productId, 'c' => $c], $catalogue), 'c', 'id');
        $feedItems = self::indexed('feedItems');
        $cases = [];

        foreach ($sections as $section) {
            foreach ($fixture[$section] as $case) {
                [$key, $result] = match ($section) {
                    'items' => [
                        "item {$case['feedItemId']}",
                        $matcher->match(self::feedItemFacts($feedItems[$case['feedItemId']]), $catalogue, $aliases, $policy),
                    ],
                    'candidates' => [
                        "item {$case['feedItemId']} × product {$case['productId']}",
                        $matcher->scoreCandidate(self::feedItemFacts($feedItems[$case['feedItemId']]), $candidates[$case['productId']], $aliases, $policy),
                    ],
                    'synthetic' => [
                        "{$case['item']['id']} {$case['name']}",
                        $matcher->match(
                            self::feedItemFacts($case['item']),
                            $case['products'] === null ? $catalogue : self::candidateProducts($case['products']),
                            $aliases,
                            $policy,
                        ),
                    ],
                    default => throw new UnexpectedValueException("Unknown matching section [{$section}]."),
                };

                $expected = $case['match'];
                $expected['rawPoints'] = array_sum(array_column($expected['parts'], 'pts'));
                $cases["{$section}: {$key}"] = ['expected' => $expected, 'actual' => self::matchResultAsPrototype($result)];
            }
        }

        return $cases;
    }

    /**
     * A MatchResult in the prototype's `ix.match()` shape (colour omitted).
     *
     * @return array{score: int, level: string, bucket: string, product: ?int, parts: list<array{label: string, pts: int}>, rawPoints: int}
     */
    public static function matchResultAsPrototype(MatchResult $result): array
    {
        return [
            'score' => $result->score,
            'level' => $result->level->label(),
            'bucket' => $result->bucket->value,
            'product' => $result->bestProductId,
            'parts' => array_map(static fn (MatchPart $part): array => ['label' => MatchPartLabel::english($part), 'pts' => $part->points], $result->parts),
            'rawPoints' => $result->rawPoints,
        ];
    }

    /**
     * Seed text fields are strings; anything else would change the JS semantics.
     */
    private static function string(mixed $value): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException('Expected a string in the prototype seed, got '.get_debug_type($value).'.');
        }

        return $value;
    }

    private static function optionalString(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    private static function float(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
