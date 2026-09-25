<?php

namespace App\Http\Requests\Merchant;

use App\Http\Controllers\Merchant\Support\MerchantScope;
use App\Models\MerchantProduct;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * For Form Requests on `/merchant/matching/listings/{listing}/…`: resolves
 * {listing} in the active merchant's scope (a foreign id is a 404) and
 * authorises a MerchantProductPolicy ability before validation.
 *
 * @mixin FormRequest
 */
trait ResolvesMerchantListing
{
    private ?MerchantProduct $resolvedListing = null;

    /**
     * @throws ModelNotFoundException<MerchantProduct>
     */
    public function listing(): MerchantProduct
    {
        return $this->resolvedListing ??= app(MerchantScope::class)->listing((int) $this->route('listing'));
    }

    protected function allowsOnListing(string $ability): bool
    {
        return Gate::allows($ability, $this->listing());
    }
}
