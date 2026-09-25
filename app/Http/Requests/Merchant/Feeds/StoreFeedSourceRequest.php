<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Domain\Merchants\MerchantContext;
use App\Models\FeedSource;
use Illuminate\Support\Facades\Gate;

/**
 * A new feed source for the active merchant (owner/manager). URL feeds need
 * their address.
 */
class StoreFeedSourceRequest extends FeedSourceRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [FeedSource::class, app(MerchantContext::class)->merchantId]);
    }

    /**
     * @return list<string>
     */
    protected function urlPresenceRules(): array
    {
        return ['required'];
    }
}
