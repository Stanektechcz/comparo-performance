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
use App\Domain\Orders\Delivery\DeliveryObservation;
use App\Domain\Orders\Delivery\DeliveryPolicy;
use App\Domain\Orders\Delivery\DeliveryStatsCalculator;
use App\Domain\Pricing\CouponState;
use App\Domain\Pricing\CouponType;
use App\Domain\Pricing\LandedPrice\CouponTerms;
use App\Domain\Pricing\LandedPrice\LandedPriceInput;
use App\Domain\Pricing\LandedPrice\ShippingTerms;
use App\Domain\Reviews\Abuse\TextHeuristics;
use App\Domain\Reviews\Abuse\TextSignal;
use App\Domain\Reviews\Aggregation\RatingAggregate;
use App\Domain\Reviews\Aggregation\RatingAggregator;
use App\Domain\Reviews\Aggregation\RatingInput;
use App\Domain\Reviews\Credibility\ReviewTrustCalculator;
use App\Domain\Reviews\Credibility\ReviewTrustInput;
use App\Domain\Reviews\Credibility\ReviewTrustSignal;
use App\Domain\Reviews\Credibility\ReviewWeight;
use App\Domain\Search\DidYouMean;
use App\Domain\Search\DidYouMeanSuggestion;
use App\Domain\Search\Local\EntryAttributes;
use App\Domain\Search\Local\SearchableEntry;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Relevance\JsString;
use App\Domain\Search\Relevance\PrototypeRelevance;
use App\Domain\Search\Relevance\RelevanceHit;
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
     * The seed catalogue as search entries in DC `searchAll` insertion order:
     * canonical products (no merges in the fixture state), brands, shops,
     * categories, ingredients (`S.ingredients`; the id is the name, like the
     * prototype result). Attributes carry slugs and ratings only; no market
     * data (the prototype does not filter search by market).
     *
     * @return list<SearchableEntry>
     */
    public static function searchEntries(): array
    {
        $seed = self::seed();
        $brands = self::indexed('brands');
        $categories = self::indexed('categories');
        $ingredientSlugs = array_column($seed['ingredientEntities'], 'slug', 'name');
        $ratings = self::derived()['productRatings'];
        $entries = [];

        foreach ($seed['products'] as $product) {
            $brand = $brands[$product['brandId']];
            $category = $categories[$product['categoryId']];
            $rating = $ratings[(string) $product['id']] ?? null;
            $ingredients = array_map(self::string(...), $product['ingredients']);

            $entries[] = SearchableEntry::product(
                id: (int) $product['id'],
                name: self::string($product['name']),
                brandName: self::string($brand['name']),
                ingredientNames: $ingredients,
                categoryName: self::string($category['name']),
                sku: self::optionalString($product['sku'] ?? null),
                ean: self::optionalString($product['ean'] ?? null),
                attributes: new EntryAttributes(
                    brandSlug: self::string($brand['slug']),
                    categoryPath: [self::string($category['slug'])],
                    ingredientSlugs: array_values(array_map(static fn (string $name): string => $ingredientSlugs[$name], $ingredients)),
                    ratingAverage: $rating === null || $rating['count'] === 0 ? null : (float) $rating['average'],
                    ratingCount: (int) ($rating['count'] ?? 0),
                ),
            );
        }

        foreach ($seed['brands'] as $brand) {
            $entries[] = SearchableEntry::brand((int) $brand['id'], self::string($brand['name']));
        }

        foreach ($seed['merchants'] as $merchant) {
            $entries[] = SearchableEntry::shop((int) $merchant['id'], self::string($merchant['name']), self::string($merchant['web']));
        }

        foreach ($seed['categories'] as $category) {
            $entries[] = SearchableEntry::category((int) $category['id'], self::string($category['name']));
        }

        foreach ($seed['ingredients'] as $name) {
            $entries[] = SearchableEntry::ingredient(self::string($name), self::string($name));
        }

        return $entries;
    }

    /**
     * The fixture record of a search section for a query. The port trims the
     * query once (documented deviation), so a query is compared with the
     * prototype's answer for its trimmed text; the exporter adds every
     * trimmed variant to the query set.
     *
     * @return array<string, mixed>
     */
    public static function searchRecord(string $section, string $query): array
    {
        $records = self::$cache["__search_{$section}"] ??= array_column(self::load('search')[$section], null, 'query');
        $trimmed = JsString::trim($query);

        return $records[$trimmed] ?? throw new UnexpectedValueException("No {$section} record for the trimmed query ".json_encode($trimmed).'.');
    }

    /**
     * Every fixture query run through PrototypeRelevance. Expected lists drop
     * articles and coupons (not searchable in Phase 3, A-25) and keep the
     * prototype order of what remains.
     *
     * @return array<string, array{expected: list<array<string, mixed>>, actual: list<array<string, mixed>>}>
     */
    public static function searchResultCases(PrototypeRelevance $relevance): array
    {
        $entries = self::searchEntries();
        $cases = [];

        foreach (self::load('search')['results'] as $record) {
            $expected = array_values(array_filter(
                self::searchRecord('results', $record['query'])['list'],
                static fn (array $hit): bool => ! in_array($hit['type'], ['article', 'coupon'], true),
            ));
            $actual = array_map(self::hitAsPrototype(...), $relevance->rank($record['query'], $entries));
            $cases['results: '.json_encode($record['query'], JSON_UNESCAPED_UNICODE)] = ['expected' => $expected, 'actual' => $actual];
        }

        return $cases;
    }

    /**
     * Every fixture query run through DidYouMean (the zero-result gate open,
     * like the exporter).
     *
     * @return array<string, array{expected: list<array<string, mixed>>, actual: list<array<string, mixed>>}>
     */
    public static function didYouMeanCases(DidYouMean $didYouMean): array
    {
        $entries = self::searchEntries();
        $cases = [];

        foreach (self::load('search')['didYouMean'] as $record) {
            $actual = array_map(static fn (DidYouMeanSuggestion $suggestion): array => [
                'kind' => $suggestion->entry->type->value,
                'label' => $suggestion->label(),
                'score' => $suggestion->score,
            ], $didYouMean->suggest($record['query'], $entries));
            $cases['didYouMean: '.json_encode($record['query'], JSON_UNESCAPED_UNICODE)] = [
                'expected' => self::searchRecord('didYouMean', $record['query'])['list'],
                'actual' => $actual,
            ];
        }

        return $cases;
    }

    /**
     * A relevance hit in the fixture's `{type, id|name, score}` shape.
     *
     * @return array<string, int|string>
     */
    public static function hitAsPrototype(RelevanceHit $hit): array
    {
        $entry = $hit->entry;
        $reference = $entry->type === SearchableType::Ingredient ? ['name' => $entry->name] : ['id' => $entry->id];

        return ['type' => $entry->type->value, ...$reference, 'score' => $hit->score];
    }

    /**
     * An epoch-milliseconds prototype timestamp with its milliseconds kept.
     */
    public static function atMilliseconds(int|float $epochMilliseconds): DateTimeImmutable
    {
        $milliseconds = (int) $epochMilliseconds;
        $moment = DateTimeImmutable::createFromFormat('U.u', sprintf('%d.%06d', intdiv($milliseconds, 1000), ($milliseconds % 1000) * 1000));

        if ($moment === false) {
            throw new UnexpectedValueException("Invalid prototype timestamp {$milliseconds}.");
        }

        return $moment->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * JSON has no float/int distinction: integral floats become integers, as
     * JSON.stringify prints them.
     */
    public static function jsonNumbers(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => array_map(self::jsonNumbers(...), $value),
            is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 1e15 => (int) $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $input  a reviews.json `input` record
     */
    public static function reviewTrustInput(array $input): ReviewTrustInput
    {
        return new ReviewTrustInput(
            verifiedPurchase: $input['verifiedPurchase'],
            body: $input['text'] ?? '',
            duplicateText: $input['duplicate'],
            burstCluster: $input['burst'],
            declaredAccountAgeDays: $input['flaggedAgeDays'],
            accountCreatedAt: $input['userJoined'] === null ? null : self::atMilliseconds($input['userJoined']),
            sharedDeviceCount: $input['deviceCount'],
            sameTargetCount: $input['sameTargetCount'],
        );
    }

    /**
     * Per-review parity cases of a reviews.json section (`reviews` or
     * `syntheticReviews`): credibility, weight and spam signals.
     *
     * @return array<int, array{expected: array<string, mixed>, actual: mixed}>
     */
    public static function reviewCases(string $section, ReviewTrustCalculator $calculator, ReviewWeight $weights, ?TextHeuristics $heuristics = null): array
    {
        $heuristics ??= TextHeuristics::prototype();
        $cases = [];

        foreach (self::load('reviews')[$section] as $record) {
            $input = $record['input'];
            $trust = $calculator->evaluate(self::reviewTrustInput($input), self::now());
            $signals = $heuristics->signals($input['text'] ?? '', $input['rating'], $input['verifiedPurchase']);

            $cases[$record['reviewId']] = [
                'expected' => ['reviewTrust' => $record['reviewTrust'], 'weight' => $record['weight'], 'spamSignals' => $record['spamSignals']],
                'actual' => self::jsonNumbers([
                    'reviewTrust' => [
                        'score' => $trust->score,
                        'level' => $trust->level->label(),
                        'signals' => array_map(static fn (ReviewTrustSignal $signal): array => ['label' => $signal->label(), 'pts' => $signal->points, 'detail' => $signal->detail], $trust->signals),
                        'ageDays' => $trust->accountAgeDays,
                    ],
                    'weight' => $weights->of($trust->level, $input['verifiedPurchase']),
                    'spamSignals' => array_map(static fn (TextSignal $signal): string => $signal->label(), $signals),
                ]),
            ];
        }

        return $cases;
    }

    /**
     * Rating parity cases: every seed product (full product summary), every
     * seed merchant (held-only aggregate) and the synthetic review sets.
     *
     * @return array<string, array{expected: mixed, actual: mixed}>
     */
    public static function ratingCases(ReviewTrustCalculator $calculator, ReviewWeight $weights, RatingAggregator $aggregator): array
    {
        $fixture = self::load('reviews');
        $seedInputs = self::ratingInputs('reviews', $calculator, $weights);
        $syntheticInputs = self::ratingInputs('syntheticReviews', $calculator, $weights);
        $held = static fn (array $rating): array => $rating['heldCount'] === 0
            ? ['heldCount' => 0]
            : array_intersect_key($rating, array_flip(['heldCount', 'heldAvg', 'verifiedCount']));
        $cases = [];

        foreach ($fixture['productRatings'] as $record) {
            $aggregate = $aggregator->aggregate(self::subjectInputs($seedInputs, 'product', $record['productId']));
            $cases["product {$record['productId']}"] = [
                'expected' => $record,
                'actual' => ['productId' => $record['productId'], ...self::productRatingAsPrototype($aggregate)],
            ];
        }

        foreach ($fixture['merchantRatings'] as $record) {
            $cases["merchant {$record['merchantId']}"] = [
                'expected' => $held($record['rating']),
                'actual' => self::heldRatingAsPrototype($aggregator->aggregate(self::subjectInputs($seedInputs, 'merchant', $record['merchantId']))),
            ];
        }

        foreach ($fixture['syntheticRatings'] as $record) {
            $members = array_intersect_key($syntheticInputs, array_flip($record['reviewIds']));
            $cases["synthetic {$record['name']}"] = [
                'expected' => ['product' => $record['product'], 'merchant' => $held($record['merchant'])],
                'actual' => [
                    'product' => self::productRatingAsPrototype($aggregator->aggregate(self::subjectInputs($members, 'product', 1))),
                    'merchant' => self::heldRatingAsPrototype($aggregator->aggregate(self::subjectInputs($members, 'merchant', 1))),
                ],
            ];
        }

        return $cases;
    }

    /**
     * A product aggregate in the fixture's product-page shape.
     *
     * @return array<string, mixed>
     */
    public static function productRatingAsPrototype(RatingAggregate $aggregate): array
    {
        $labels = ['value' => 'Value for money', 'quality' => 'Quality', 'packaging' => 'Packaging', 'ease' => 'Ease of use'];
        $percentages = $aggregate->distributionPercentages();
        $distribution = [];
        $subRatings = [];

        foreach ($aggregate->distribution as $stars => $count) {
            $distribution[] = ['n' => $stars, 'count' => $count, 'pct' => $percentages[$stars]];
        }

        foreach ($aggregate->subRatingDisplay() as $dimension => $value) {
            $subRatings[] = ['label' => $labels[$dimension], 'value' => $value];
        }

        return (array) self::jsonNumbers([
            'rating' => $aggregate->isEmpty()
                ? ['avg' => 0, 'count' => 0]
                : ['avg' => $aggregate->average, 'count' => $aggregate->count, 'verifiedCount' => $aggregate->verifiedCount, 'weighted' => true],
            'distribution' => $distribution,
            'recommendPct' => $aggregate->recommendPercent === null ? '—' : "{$aggregate->recommendPercent} %",
            'subRatings' => $subRatings,
            'verifiedCount' => $aggregate->verifiedCount,
        ]);
    }

    /**
     * A merchant aggregate in the shape of the prototype's held-only fields.
     *
     * @return array<string, mixed>
     */
    public static function heldRatingAsPrototype(RatingAggregate $aggregate): array
    {
        return $aggregate->isEmpty()
            ? ['heldCount' => 0]
            : (array) self::jsonNumbers(['heldCount' => $aggregate->count, 'heldAvg' => $aggregate->average, 'verifiedCount' => $aggregate->verifiedCount]);
    }

    /**
     * delivery.json parity cases: seed shops per market, all markets, and the
     * synthetic boundary sets (a custom minimum sample overrides the policy).
     *
     * @return array<string, array{expected: array<string, mixed>, actual: mixed}>
     */
    public static function deliveryCases(DeliveryPolicy $policy): array
    {
        $fixture = self::load('delivery');
        $orders = self::seed()['orders'];
        $cases = [];

        foreach ([...$fixture['cases'], ...$fixture['allMarkets']] as $record) {
            $scope = array_values(array_filter($orders, static fn (array $order): bool => $order['merchantId'] === $record['merchantId']
                && ($record['market'] === null || $order['market'] === $record['market'])));
            $cases["merchant {$record['merchantId']} market ".($record['market'] ?? 'all')] = self::deliveryCase($policy, $scope, $record['stats']);
        }

        foreach ($fixture['synthetic'] as $record) {
            $scoped = $record['minSample'] === null ? $policy : $policy->with(['minSample' => $record['minSample']]);
            $cases["synthetic {$record['name']}"] = self::deliveryCase($scoped, $record['orders'], $record['stats']);
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $order  a prototype order row
     */
    public static function deliveryObservation(array $order): DeliveryObservation
    {
        return new DeliveryObservation(
            actualDays: self::float($order['actualDays']),
            promisedDays: (float) $order['promisedDays'],
            returned: $order['status'] === 'returned',
            disputed: $order['status'] === 'disputed',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @param  array<string, mixed>  $expected
     * @return array{expected: array<string, mixed>, actual: mixed}
     */
    private static function deliveryCase(DeliveryPolicy $policy, array $orders, array $expected): array
    {
        $stats = (new DeliveryStatsCalculator($policy))->calculate(array_map(self::deliveryObservation(...), $orders));
        unset($expected['note']);

        $actual = $stats->enough
            ? [
                'enough' => true, 'sample' => $stats->sample, 'total' => $stats->total,
                'medianDays' => $stats->medianDays, 'p90' => $stats->p90Days, 'promised' => $stats->promisedDays,
                'onTimePct' => $stats->onTimePercent, 'returnPct' => $stats->returnPercent, 'disputePct' => $stats->disputePercent,
                'faster' => $stats->fasterThanPromised,
            ]
            : ['enough' => false, 'sample' => $stats->sample, 'min' => $stats->minSample];

        return ['expected' => $expected, 'actual' => self::jsonNumbers($actual)];
    }

    /**
     * Rating inputs of a reviews.json section keyed by review id, weighted by
     * the ported credibility (so policy changes flow into the aggregates).
     *
     * @return array<int, array{subject: string, targetId: int, input: RatingInput}>
     */
    private static function ratingInputs(string $section, ReviewTrustCalculator $calculator, ReviewWeight $weights): array
    {
        $inputs = [];

        foreach (self::load('reviews')[$section] as $record) {
            $input = $record['input'];
            $level = $calculator->evaluate(self::reviewTrustInput($input), self::now())->level;
            $inputs[$record['reviewId']] = [
                'subject' => $input['subject'],
                'targetId' => $input['targetId'],
                'input' => new RatingInput(
                    rating: $input['rating'],
                    weight: $weights->of($level, $input['verifiedPurchase']),
                    approved: $input['status'] === 'approved',
                    verifiedPurchase: $input['verifiedPurchase'],
                    recommends: (bool) $input['recommend'],
                    subRatings: $input['sub'],
                ),
            ];
        }

        return $inputs;
    }

    /**
     * @param  array<int, array{subject: string, targetId: int, input: RatingInput}>  $inputs
     * @return list<RatingInput>
     */
    private static function subjectInputs(array $inputs, string $subject, int $targetId): array
    {
        $matching = array_filter($inputs, static fn (array $row): bool => $row['subject'] === $subject && $row['targetId'] === $targetId);

        return array_values(array_map(static fn (array $row): RatingInput => $row['input'], $matching));
    }

    /**
     * @return array<string, mixed>
     */
    private static function derived(): array
    {
        self::seed();

        return self::$cache['__seed']['derived'];
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
