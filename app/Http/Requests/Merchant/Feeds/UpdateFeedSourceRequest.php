<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Http\Requests\Merchant\ResolvesMerchantFeed;

/**
 * Updated feed settings (owner/manager). The edit form never receives the
 * stored URL (it may carry tokens), so an empty URL keeps the stored one —
 * unless the source has none yet (it was an upload feed).
 */
class UpdateFeedSourceRequest extends FeedSourceRequest
{
    use ResolvesMerchantFeed;

    public function authorize(): bool
    {
        return $this->allowsOnFeed('update');
    }

    /**
     * @return list<string>
     */
    protected function urlPresenceRules(): array
    {
        $url = $this->feed()->url;

        return $url !== null && $url !== '' ? ['nullable'] : ['required'];
    }
}
