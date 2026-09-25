<?php

namespace App\Domain\Matching\Engine;

/**
 * The prototype's English explanation labels, reproduced exactly (intel.js
 * match()). Localised rendering belongs to the presentation layer.
 */
final class MatchPartLabel
{
    public static function english(MatchPart $part): string
    {
        return match ($part->signal) {
            MatchSignal::EanExact => 'EAN exact match',
            MatchSignal::BrandExact => 'Brand exact',
            MatchSignal::BrandAlias => 'Brand exact (alias)',
            MatchSignal::BrandInTitle => 'Brand found in title',
            MatchSignal::TitleSimilarity => 'Title similarity '.($part->params['percent'] ?? 0).' %',
            MatchSignal::PackExact => 'Package size match',
            MatchSignal::PackAlternate => 'Known alternate pack',
            MatchSignal::PackDiffers => 'Package size differs ('.($part->params['feed_pack'] ?? '').' vs '.($part->params['product_pack'] ?? '').')',
            MatchSignal::Variant => 'Variant match',
            MatchSignal::Ingredient => 'Ingredient set overlap',
        };
    }
}
