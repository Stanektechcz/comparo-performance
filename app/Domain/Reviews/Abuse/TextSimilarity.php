<?php

namespace App\Domain\Reviews\Abuse;

use App\Domain\Shared\JsMath;
use App\Domain\Shared\Text\TitleSimilarity;

/**
 * Review-body similarity as intel.js `dupClusters` reports it:
 * `Math.round(similarity(a, b) * 100)`. The prototype uses the same
 * `similarity` as feed matching, so this reuses {@see TitleSimilarity}
 * (including its documented `constructor`-token deviation).
 */
final class TextSimilarity
{
    public static function percent(?string $a, ?string $b): int
    {
        return JsMath::roundInt(TitleSimilarity::score($a ?? '', $b ?? '') * 100);
    }
}
