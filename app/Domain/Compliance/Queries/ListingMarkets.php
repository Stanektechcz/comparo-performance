<?php

namespace App\Domain\Compliance\Queries;

use App\Domain\Compliance\MarketComplianceHold;
use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Platform\Markets\MarketContext;
use App\Models\Country;
use App\Models\MerchantProduct;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;

/**
 * The market a listing is sold in is its feed source's country. Builds the
 * {@see MarketComplianceHold} that matching decisions pass to the Matching
 * actions.
 */
final class ListingMarkets
{
    public function __construct(private readonly ComplianceResolver $resolver) {}

    public function marketOf(MerchantProduct $listing): ?MarketContext
    {
        $listing->loadMissing('feedSource.country.currency');

        return self::market($listing->feedSource?->country);
    }

    public function holdForListing(MerchantProduct $listing): ComplianceHoldCheck
    {
        return $this->holdForMarket($this->marketOf($listing));
    }

    /**
     * Held when the product is blocked in any source listing's market (BACKLOG F-07).
     */
    public function holdForCandidate(ProductCandidate $candidate): ComplianceHoldCheck
    {
        $sources = $candidate->sources()->with('merchantProduct.feedSource.country.currency')->get();
        $markets = [];

        foreach ($sources as $source) {
            /** @var ProductCandidateSource $source */
            $market = self::market($source->merchantProduct->feedSource?->country);

            if ($market !== null) {
                $markets[$market->code] = $market;
            }
        }

        return new MarketComplianceHold($this->resolver, array_values($markets));
    }

    public function holdForMarket(?MarketContext $market): ComplianceHoldCheck
    {
        return new MarketComplianceHold($this->resolver, $market === null ? [] : [$market]);
    }

    public static function market(?Country $country): ?MarketContext
    {
        if ($country === null) {
            return null;
        }

        return new MarketContext(
            countryId: $country->id,
            code: $country->code,
            name: $country->name,
            currency: $country->currency->code,
            locale: $country->default_locale.'-'.$country->code,
        );
    }
}
