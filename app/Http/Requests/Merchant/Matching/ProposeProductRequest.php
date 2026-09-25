<?php

namespace App\Http\Requests\Merchant\Matching;

use App\Http\Requests\Merchant\ResolvesMerchantListing;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Propose a new catalogue product from one of the merchant's own unmatched
 * or suggested listings (owner/manager). No input: the proposal is built
 * from the listing's facts.
 */
class ProposeProductRequest extends FormRequest
{
    use ResolvesMerchantListing;

    public function authorize(): bool
    {
        return $this->allowsOnListing('propose');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
