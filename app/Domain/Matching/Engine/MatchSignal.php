<?php

namespace App\Domain\Matching\Engine;

/**
 * The closed set of evidence the matcher can find between a feed row and a
 * canonical product (intel.js Product Matching Engine 2.0).
 */
enum MatchSignal: string
{
    case EanExact = 'ean_exact';
    case BrandExact = 'brand_exact';
    case BrandAlias = 'brand_alias';
    case BrandInTitle = 'brand_in_title';
    case TitleSimilarity = 'title_similarity';
    case PackExact = 'pack_exact';
    case PackAlternate = 'pack_alternate';
    case PackDiffers = 'pack_differs';
    case Variant = 'variant';
    case Ingredient = 'ingredient';
}
