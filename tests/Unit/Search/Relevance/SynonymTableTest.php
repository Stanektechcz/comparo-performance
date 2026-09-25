<?php

use App\Domain\Search\Relevance\SynonymExpansion;
use App\Domain\Search\Relevance\SynonymTable;

it('expands sibling terms of every group a query term triggers by substring', function (string $foldedQuery, array $terms) {
    expect(SynonymExpansion::prototype()->termsFor($foldedQuery))->toBe($terms);
})->with([
    'protein group' => ['whey', ['isolate', 'casein', 'protein']],
    'substring trigger (A-26)' => ['pumpkin', ['pre-workout', 'preworkout', 'stim']],
    'two groups' => ['creatine protein', ['whey', 'isolate', 'casein', 'monohydrate', 'creapure', 'kreatin']],
    'no group' => ['zinc', []],
]);

it('matches a text containing any expansion term after folding', function () {
    $expansion = SynonymExpansion::prototype();

    expect($expansion->matches(['kreatin'], 'KREATIN Pulver'))->toBeTrue()
        ->and($expansion->matches(['casein'], 'Caséin Night'))->toBeTrue()
        ->and($expansion->matches(['casein'], 'Whey isolate'))->toBeFalse()
        ->and($expansion->matches([], 'anything'))->toBeFalse();
});

it('rejects malformed synonym groups', function (array $groups) {
    new SynonymTable($groups);
})->throws(InvalidArgumentException::class)->with([
    'empty group' => [['protein' => []]],
    'blank term' => [['protein' => ['whey', ' ']]],
    'unnamed group' => [['' => ['whey']]],
]);
