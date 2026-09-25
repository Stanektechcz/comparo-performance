<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\Engine\MatchResult;
use App\Domain\Matching\Engine\ProductMatcher;
use App\Models\MerchantProduct;
use App\Models\Product;

/**
 * Live top-N candidates for one listing under the active policy, for the
 * side-by-side comparison UI: every narrowed candidate is scored in isolation
 * and the best are returned (score descending, product id ascending). Nothing
 * is written.
 */
final class MatchPreview
{
    public const int MAX_LIMIT = 20;

    public function __construct(
        private readonly CandidateProducts $candidates,
        private readonly ActiveMatchingPolicy $policies,
        private readonly ProductMatcher $matcher,
    ) {}

    /**
     * @return list<PreviewCandidate>
     */
    public function for(MerchantProduct $listing, int $limit = 5): array
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $facts = ListingFacts::of($listing);
        $policy = $this->policies->current()->policy;
        $aliases = $this->candidates->aliasSets();

        $results = array_map(
            fn ($candidate): MatchResult => $this->matcher->scoreCandidate($facts, $candidate, $aliases, $policy),
            $this->candidates->for($facts),
        );

        usort($results, static fn (MatchResult $a, MatchResult $b): int => [$b->score, $a->bestProductId] <=> [$a->score, $b->bestProductId]);
        $results = array_slice($results, 0, $limit);

        $products = Product::query()
            ->with('brand:id,name')
            ->whereIn('id', array_map(static fn (MatchResult $result): int => (int) $result->bestProductId, $results))
            ->get(['id', 'brand_id', 'name', 'slug', 'pack_label', 'ean', 'status'])
            ->keyBy('id');

        $preview = [];

        foreach ($results as $result) {
            $product = $products->get((int) $result->bestProductId);

            if ($product instanceof Product) {
                $preview[] = new PreviewCandidate(
                    ProductSummary::fromModel($product),
                    $result->score,
                    $result->level,
                    $result->bucket,
                    MatchComponents::parts($result->parts),
                );
            }
        }

        return $preview;
    }
}
