<?php

use App\Domain\Search\Query\SearchQuery;

it('computes the page offset', function () {
    expect((new SearchQuery('whey', 'DE', page: 3, perPage: 20))->offset())->toBe(40);
});

it('rejects invalid requests', function (array $arguments) {
    new SearchQuery(...$arguments);
})->throws(InvalidArgumentException::class)->with([
    'lower-case market' => [['text' => 'whey', 'market' => 'de']],
    'three-letter market' => [['text' => 'whey', 'market' => 'DEU']],
    'page zero' => [['text' => 'whey', 'market' => 'DE', 'page' => 0]],
    'no results per page' => [['text' => 'whey', 'market' => 'DE', 'perPage' => 0]],
    'too many per page' => [['text' => 'whey', 'market' => 'DE', 'perPage' => 101]],
    'text too long' => [['text' => str_repeat('a', 201), 'market' => 'DE']],
]);
