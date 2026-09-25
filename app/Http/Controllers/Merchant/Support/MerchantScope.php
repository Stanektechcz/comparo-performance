<?php

namespace App\Http\Controllers\Merchant\Support;

use App\Domain\Feeds\Queries\FeedRunHistory;
use App\Domain\Feeds\Queries\MerchantFeedSources;
use App\Domain\Merchants\MerchantContext;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MerchantProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the integer ids of `/merchant/*` URLs through queries scoped to
 * the ACTIVE MerchantContext merchant. Another merchant's id — including one
 * of a second merchant the same user belongs to — simply does not exist
 * here: ModelNotFoundException renders as 404, never 403, so ids cannot be
 * probed. Authorisation (policies) happens after resolution.
 */
final class MerchantScope
{
    public function __construct(
        private readonly MerchantContext $context,
        private readonly MerchantFeedSources $sources,
        private readonly FeedRunHistory $runs,
    ) {}

    public function merchantId(): int
    {
        return $this->context->merchantId;
    }

    /**
     * @throws ModelNotFoundException<FeedSource>
     */
    public function feed(int $feedId): FeedSource
    {
        return $this->sources->find($this->context->merchantId, $feedId);
    }

    /**
     * A run of this merchant that also belongs to the given source.
     *
     * @throws ModelNotFoundException<FeedRun>
     */
    public function run(FeedSource $feed, int $runId): FeedRun
    {
        $run = $this->runs->find($this->context->merchantId, $runId);

        if ($run->feed_source_id !== $feed->id) {
            throw (new ModelNotFoundException)->setModel(FeedRun::class, [$runId]);
        }

        return $run;
    }

    /**
     * No merchant-scoped single-listing query exists in App\Domain\Matching
     * yet (MerchantMatchingQueue only pages), so this is the one read helper
     * kept in the HTTP layer: the merchant id comes from the context, never
     * from the request.
     *
     * @throws ModelNotFoundException<MerchantProduct>
     */
    public function listing(int $listingId): MerchantProduct
    {
        return MerchantProduct::query()
            ->where('merchant_id', $this->context->merchantId)
            ->findOrFail($listingId);
    }

    /**
     * The authenticated member (routes require `auth` + `merchant.context`).
     */
    public static function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpException(403);
        }

        return $user;
    }
}
