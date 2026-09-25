<?php

namespace App\Domain\Search\Documents;

use App\Domain\Platform\Markets\MarketResolver;
use App\Domain\Search\Contracts\SearchIndex;
use App\Models\Merchant;
use App\Models\MerchantShippingZone;
use DateTimeImmutable;

/**
 * The only factory of merchant ("shop") documents. Only listed (active)
 * merchants get a document; others are returned for deletion.
 * `shipping_markets` lists the ACTIVE markets the merchant has a shipping
 * zone for (A-28).
 */
final readonly class MerchantDocumentBuilder
{
    public function __construct(private MarketResolver $markets) {}

    /**
     * @param  list<int>  $merchantIds
     * @return BuiltDocuments<MerchantDocument>
     */
    public function build(array $merchantIds, DateTimeImmutable $now): BuiltDocuments
    {
        $requested = BuildIds::normalize($merchantIds);
        $indexedAt = BuildIds::indexedAt($now);
        $activeCountries = [];

        foreach ($this->markets->all() as $code => $market) {
            if ($market->countryId !== null) {
                $activeCountries[$market->countryId] = $code;
            }
        }

        $merchants = Merchant::query()
            ->listed()
            ->whereKey($requested)
            ->with('shippingZones')
            ->orderBy('id')
            ->get();

        $documents = array_values(array_map(static fn (Merchant $merchant): MerchantDocument => new MerchantDocument(
            id: $merchant->id,
            slug: $merchant->slug,
            name: $merchant->name,
            websiteHost: self::host($merchant->website),
            verified: $merchant->isVerified(),
            ratingAverage: self::rating($merchant),
            ratingCount: $merchant->rating_count,
            shippingMarkets: self::shippingMarkets($merchant, $activeCountries),
            indexedAt: $indexedAt,
        ), $merchants->all()));

        return new BuiltDocuments(
            SearchIndex::Merchants,
            $documents,
            BuildIds::missing($requested, array_map(static fn (MerchantDocument $document): int => $document->id, $documents)),
        );
    }

    /**
     * The lower-cased host of a website (`peaksupps.de`,
     * `https://www.example.com/shop` → `www.example.com`); null when absent
     * or unparsable.
     */
    public static function host(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '') {
            return null;
        }

        $host = parse_url(str_contains($website, '://') ? $website : "https://{$website}", PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_strtolower($host) : null;
    }

    /**
     * The public rating the ranking also uses: the credibility-weighted
     * average, else the plain average; none without reviews.
     */
    private static function rating(Merchant $merchant): ?float
    {
        $average = $merchant->weighted_rating ?? $merchant->rating_average;

        return $merchant->rating_count > 0 && $average !== null ? min(5.0, max(0.0, (float) $average)) : null;
    }

    /**
     * @param  array<int, string>  $activeCountries  country id => market code
     * @return list<string> sorted market codes
     */
    private static function shippingMarkets(Merchant $merchant, array $activeCountries): array
    {
        $codes = [];

        foreach ($merchant->shippingZones as $zone) {
            /** @var MerchantShippingZone $zone */
            if (isset($activeCountries[$zone->country_id])) {
                $codes[$activeCountries[$zone->country_id]] = true;
            }
        }

        $codes = array_map(strval(...), array_keys($codes));
        sort($codes);

        return $codes;
    }
}
