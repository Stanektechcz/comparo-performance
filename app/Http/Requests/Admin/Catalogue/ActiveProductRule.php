<?php

namespace App\Http\Requests\Admin\Catalogue;

use App\Domain\Catalog\ProductStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared validation for a `product_id` a staff decision links to: it must be
 * an ACTIVE canonical product (merged, draft or archived products are refused
 * before the domain action runs).
 */
final class ActiveProductRule
{
    public const string MESSAGE = 'Choose an active catalogue product.';

    public static function make(): Exists
    {
        return Rule::exists('products', 'id')->where('status', ProductStatus::Active->value);
    }
}
