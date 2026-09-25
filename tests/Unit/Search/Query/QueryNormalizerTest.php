<?php

use App\Domain\Search\Query\QueryNormalizer;

it('trims JavaScript whitespace once and folds the rest', function (string $raw, string $trimmed, string $folded) {
    $query = (new QueryNormalizer)->normalize($raw);

    expect($query->raw)->toBe($raw)
        ->and($query->trimmed)->toBe($trimmed)
        ->and($query->folded)->toBe($folded);
})->with([
    'trailing space' => ['creatine ', 'creatine', 'creatine'],
    'no-break space and ideographic space' => ["\u{00A0}Whey\u{3000}", 'Whey', 'whey'],
    'tab and newline' => ["\tWHEY isolate\n", 'WHEY isolate', 'whey isolate'],
    'inner whitespace kept' => ['whey  isolate', 'whey  isolate', 'whey  isolate'],
    'accents stripped' => [' Créatine ', 'Créatine', 'creatine'],
]);

it('requires two UTF-16 units after trimming', function (string $raw, bool $searchable) {
    expect((new QueryNormalizer)->normalize($raw)->searchable)->toBe($searchable);
})->with([
    'empty' => ['', false],
    'one letter' => ['a', false],
    'one letter padded' => [' a ', false],
    'two letters' => ['ab', true],
    'one astral character is two units' => ['💪', true],
    'whitespace only' => ["\u{00A0} \u{2003}", false],
]);

it('rejects a minimum length below one', function () {
    new QueryNormalizer(0);
})->throws(InvalidArgumentException::class);
