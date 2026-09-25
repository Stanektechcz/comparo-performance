<?php

use App\Domain\Matching\Engine\MatchBucket;
use App\Domain\Matching\Queries\MatchPreview;
use App\Models\MatchingDecision;
use Tests\Feature\Matching\MatchingScenario as Scenario;

it('ranks live candidates by score with their evidence', function () {
    $exact = Scenario::product();
    $sibling = Scenario::product(name: 'Whey Isolate', pack: '2 kg');
    $otherLine = Scenario::product(name: 'Creatine Monohydrate', pack: '500 g');
    Scenario::product(brand: 'Unrelated Brand', name: 'Omega 3');
    $listing = Scenario::listing(Scenario::autoFacts($exact));

    $preview = app(MatchPreview::class)->for($listing);

    expect(array_map(fn ($candidate): int => $candidate->product->id, $preview))->toBe([$exact->id, $sibling->id, $otherLine->id])
        ->and($preview[0]->bucket)->toBe(MatchBucket::Auto)
        ->and($preview[0]->score)->toBeGreaterThan($preview[1]->score)
        ->and($preview[1]->score)->toBeGreaterThanOrEqual($preview[2]->score)
        ->and($preview[0]->parts[0])->toBe(['signal' => 'ean_exact', 'points' => 50, 'label' => 'EAN exact match', 'params' => []])
        ->and(collect($preview[1]->parts)->pluck('signal')->all())->toContain('pack_differs')
        ->and($preview[0]->product->brandName)->toBe('Acme Nutrition');
});

it('limits the preview and writes nothing', function () {
    $exact = Scenario::product();
    Scenario::product(name: 'Clear Whey');
    Scenario::product(name: 'Vegan Protein');
    $listing = Scenario::listing(Scenario::autoFacts($exact));

    $preview = app(MatchPreview::class)->for($listing, 2);

    expect($preview)->toHaveCount(2)
        ->and($preview[0]->product->id)->toBe($exact->id)
        ->and(MatchingDecision::query()->count())->toBe(0)
        ->and($listing->fresh()->product_id)->toBeNull();
});

it('returns no candidates when neither EAN nor brand evidence exists', function () {
    Scenario::product();

    expect(app(MatchPreview::class)->for(Scenario::listing()))->toBe([]);
});
