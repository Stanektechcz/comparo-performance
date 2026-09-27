<?php

namespace App\Domain\Reviews;

/**
 * What a review (or rating aggregate) is about: a product or a shop. Exactly
 * one of product_id / merchant_id is set, matching this type (CHECK).
 */
enum ReviewSubjectType: string
{
    case Product = 'product';
    case Merchant = 'merchant';
}
