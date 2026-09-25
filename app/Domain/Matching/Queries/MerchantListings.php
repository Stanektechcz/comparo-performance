<?php

namespace App\Domain\Matching\Queries;

use App\Models\MerchantProduct;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * One merchant's listings, always scoped by merchant id: another merchant's
 * listing id simply does not exist here (404, never 403).
 */
final class MerchantListings
{
    /**
     * @throws ModelNotFoundException<MerchantProduct> for a missing or foreign id
     */
    public function find(int $merchantId, int $listingId): MerchantProduct
    {
        return MerchantProduct::query()
            ->where('merchant_id', $merchantId)
            ->findOrFail($listingId);
    }
}
