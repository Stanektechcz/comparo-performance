<?php

namespace App\Domain\Search\Queries;

use App\Models\Brand;

/**
 * A brand hit, with its number of listed products when that was requested
 * (VisibleSearchHits::load with `withBrandProductCounts`), otherwise null.
 */
final readonly class VisibleBrand
{
    public function __construct(
        public Brand $brand,
        public ?int $listedProductCount,
    ) {}
}
