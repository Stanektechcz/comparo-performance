<?php

namespace App\Domain\Matching\Engine;

use App\Domain\Shared\JsMath;
use App\Domain\Shared\Text\TextFold;
use App\Domain\Shared\Text\TitleSimilarity;

/**
 * Canonical product matching — the pure port of intel.js Product Matching
 * Engine 2.0 (`match(item)`), proven by tests/Unit/Parity/MatchingParityTest.
 *
 * Every candidate is scored independently; the first candidate with the
 * strictly highest score wins, so callers control tie-breaking through the
 * candidate order (the application layer orders by product id). Unlike the
 * prototype, an empty candidate list yields no product (deviation #3).
 */
final class ProductMatcher
{
    private const int MIN_SCORE = 0;

    private const int MAX_SCORE = 100;

    /**
     * @param  list<CandidateProduct>  $candidates
     * @param  list<BrandAliasSet>  $aliases
     */
    public function match(FeedItemFacts $item, array $candidates, array $aliases, MatchingPolicy $policy): MatchResult
    {
        $folded = self::foldItem($item);
        $best = null;

        foreach ($candidates as $candidate) {
            $result = $this->score($item, $folded, $candidate, $aliases, $policy);

            if ($best === null || $result->score > $best->score) {
                $best = $result;
            }
        }

        return $best ?? new MatchResult(
            bestProductId: null,
            score: self::MIN_SCORE,
            rawPoints: 0,
            level: $policy->levelFor(self::MIN_SCORE),
            bucket: $policy->bucketFor(self::MIN_SCORE),
            parts: [],
            policyVersion: $policy->version,
            algorithm: $policy->algorithm,
        );
    }

    /**
     * The score of one candidate in isolation.
     *
     * @param  list<BrandAliasSet>  $aliases
     */
    public function scoreCandidate(FeedItemFacts $item, CandidateProduct $candidate, array $aliases, MatchingPolicy $policy): MatchResult
    {
        return $this->score($item, self::foldItem($item), $candidate, $aliases, $policy);
    }

    /**
     * @param  array{raw: string, brand: string, pack: string, variant: string}  $folded
     * @param  list<BrandAliasSet>  $aliases
     */
    private function score(FeedItemFacts $item, array $folded, CandidateProduct $candidate, array $aliases, MatchingPolicy $policy): MatchResult
    {
        $weights = $policy->weights;
        $parts = [];

        if (self::isPresent($item->ean) && self::isPresent($candidate->ean) && $item->ean === $candidate->ean) {
            $parts[] = new MatchPart(MatchSignal::EanExact, $weights['ean_exact']);
        }

        $brandSignal = self::brandSignal($item, $folded, $candidate, $aliases);

        if ($brandSignal !== null) {
            $points = $brandSignal === MatchSignal::BrandInTitle ? $weights['brand_in_title'] : $weights['brand_exact'];
            $parts[] = new MatchPart($brandSignal, $points);
        }

        $similarity = TitleSimilarity::score(
            $item->rawTitle,
            $candidate->name.' '.$candidate->packLabel.' '.$candidate->brandName,
        );
        $similarityPoints = JsMath::roundInt($similarity * $weights['title_similarity_scale']);

        if ($similarityPoints > 0) {
            $parts[] = new MatchPart(MatchSignal::TitleSimilarity, $similarityPoints, ['percent' => JsMath::roundInt($similarity * 100)]);
        }

        $packPart = self::packPart($item, $folded, $candidate, $policy);

        if ($packPart !== null) {
            $parts[] = $packPart;
        }

        if (self::isPresent($item->variantRaw) && self::containsFolded($candidate->variants, $folded['variant'])) {
            $parts[] = new MatchPart(MatchSignal::Variant, $weights['variant']);
        }

        if (self::hasIngredientOverlap($folded['raw'], $candidate->listedIngredients)) {
            $parts[] = new MatchPart(MatchSignal::Ingredient, $weights['ingredient']);
        }

        $parts = array_values(array_filter($parts, static fn (MatchPart $part): bool => $part->points !== 0));
        $rawPoints = array_sum(array_map(static fn (MatchPart $part): int => $part->points, $parts));
        $score = max(self::MIN_SCORE, min(self::MAX_SCORE, $rawPoints));

        return new MatchResult(
            bestProductId: $candidate->productId,
            score: $score,
            rawPoints: $rawPoints,
            level: $policy->levelFor($score),
            bucket: $policy->bucketFor($score),
            parts: $parts,
            policyVersion: $policy->version,
            algorithm: $policy->algorithm,
        );
    }

    /**
     * Brand evidence requires a feed brand: exact (folded) name, an alias of
     * the first alias set registered for the candidate's brand, or — failing
     * both — the candidate's brand name inside the feed title.
     *
     * @param  array{raw: string, brand: string, pack: string, variant: string}  $folded
     * @param  list<BrandAliasSet>  $aliases
     */
    private static function brandSignal(FeedItemFacts $item, array $folded, CandidateProduct $candidate, array $aliases): ?MatchSignal
    {
        if (! self::isPresent($item->brandRaw)) {
            return null;
        }

        $brandName = TextFold::fold($candidate->brandName);

        if ($folded['brand'] === $brandName) {
            return MatchSignal::BrandExact;
        }

        foreach ($aliases as $aliasSet) {
            if ($aliasSet->canonicalBrandName === $candidate->brandName) {
                if (self::containsFolded($aliasSet->aliases, $folded['brand'])) {
                    return MatchSignal::BrandAlias;
                }

                break;
            }
        }

        return str_contains($folded['raw'], $brandName) ? MatchSignal::BrandInTitle : null;
    }

    /**
     * @param  array{raw: string, brand: string, pack: string, variant: string}  $folded
     */
    private static function packPart(FeedItemFacts $item, array $folded, CandidateProduct $candidate, MatchingPolicy $policy): ?MatchPart
    {
        if (! self::isPresent($item->packRaw)) {
            return null;
        }

        if ($folded['pack'] === TextFold::fold($candidate->packLabel)) {
            return new MatchPart(MatchSignal::PackExact, $policy->weights['pack_exact']);
        }

        if (self::containsFolded($candidate->alternatePacks, $folded['pack'])) {
            return new MatchPart(MatchSignal::PackAlternate, $policy->weights['pack_alternate']);
        }

        return new MatchPart(MatchSignal::PackDiffers, $policy->weights['pack_differs'], [
            'feed_pack' => (string) $item->packRaw,
            'product_pack' => $candidate->packLabel,
        ]);
    }

    /**
     * True when the first word of any listed ingredient occurs in the folded
     * title (an empty first word always occurs, as in the prototype).
     *
     * @param  list<string>  $ingredients
     */
    private static function hasIngredientOverlap(string $foldedTitle, array $ingredients): bool
    {
        foreach ($ingredients as $ingredient) {
            if (str_contains($foldedTitle, explode(' ', TextFold::fold($ingredient))[0])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $values
     */
    private static function containsFolded(array $values, string $folded): bool
    {
        foreach ($values as $value) {
            if (TextFold::fold($value) === $folded) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{raw: string, brand: string, pack: string, variant: string}
     */
    private static function foldItem(FeedItemFacts $item): array
    {
        return [
            'raw' => TextFold::fold($item->rawTitle),
            'brand' => TextFold::fold($item->brandRaw ?? ''),
            'pack' => TextFold::fold($item->packRaw ?? ''),
            'variant' => TextFold::fold($item->variantRaw ?? ''),
        ];
    }

    /**
     * JavaScript truthiness for an optional string: only null and '' are absent.
     */
    private static function isPresent(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}
