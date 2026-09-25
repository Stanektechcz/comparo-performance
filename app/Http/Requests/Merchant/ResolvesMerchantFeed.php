<?php

namespace App\Http\Requests\Merchant;

use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * For Form Requests on `/merchant/feeds/{feed}/…`: resolves {feed} in the
 * active merchant's scope (a foreign id is a 404) and authorises the given
 * FeedSourcePolicy ability BEFORE the input is validated, so a foreign or
 * forbidden request never learns anything from validation messages.
 *
 * @mixin FormRequest
 */
trait ResolvesMerchantFeed
{
    private ?FeedSource $resolvedFeed = null;

    /**
     * @throws ModelNotFoundException<FeedSource>
     */
    public function feed(): FeedSource
    {
        return $this->resolvedFeed ??= app(MerchantScope::class)->feed((int) $this->route('feed'));
    }

    protected function allowsOnFeed(string $ability): bool
    {
        return Gate::allows($ability, $this->feed());
    }
}
