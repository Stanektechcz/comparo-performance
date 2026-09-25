<?php

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\Parsing\FeedParseException;
use App\Domain\Feeds\Parsing\JsonFeedParser;
use App\Domain\Feeds\Parsing\ParseOptions;
use App\Domain\Feeds\Parsing\RawFeedRow;

beforeEach(function () {
    $this->fixtures = dirname(__DIR__, 3).'/Fixtures/Feeds/';
    $this->temp = [];
});

afterEach(function () {
    foreach ($this->temp as $path) {
        @unlink($path);
    }
});

/**
 * @return list<RawFeedRow>
 */
function jsonRows(string $path, ?ParseOptions $options = null): array
{
    return [...(new JsonFeedParser)->rows($path, $options ?? new ParseOptions(FeedFormat::Json))];
}

function jsonTempFile(object $test, string $json): string
{
    $path = tempnam(sys_get_temp_dir(), 'feed');
    file_put_contents($path, $json);
    $test->temp[] = $path;

    return $path;
}

it('reads a top-level array, turning numbers into exact strings and skipping lists', function () {
    $rows = jsonRows($this->fixtures.'items-array.json');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->lineNumber)->toBe(1)
        ->and($rows[0]->get('price'))->toBe('40.54')
        ->and($rows[0]->get('stock'))->toBe('172')
        ->and($rows[0]->get('tags'))->toBeNull()
        ->and($rows[1]->lineNumber)->toBe(2)
        ->and($rows[1]->get('price'))->toBe('74,90');
});

it('reads a {"products": [...]} wrapper and flattens nested objects one level', function () {
    $rows = jsonRows($this->fixtures.'items-wrapped.json');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->get('name'))->toBe('Creatine Monohydrate Micronized 1000 g')
        ->and($rows[0]->get('shipping.price'))->toBe('3.40');
});

it('reads an {"items": [...]} wrapper', function () {
    $rows = jsonRows(jsonTempFile($this, '{"items":[{"sku":"PEA-186","available":true}]}'));

    expect($rows[0]->fields)->toBe(['sku' => 'PEA-186', 'available' => 'true']);
});

it('rejects malformed JSON and unknown shapes as PARSER_ERROR', function (string $json) {
    try {
        jsonRows(jsonTempFile($this, $json));
        $this->fail('Expected a parse failure.');
    } catch (FeedParseException $exception) {
        expect($exception->errorCode)->toBe(FeedErrorCode::ParserError)
            ->and($exception->params)->toBe(['format' => 'JSON']);
    }
})->with([
    'truncated' => '[{"sku":"PEA-186"',
    'object without items' => '{"data":[{"sku":"PEA-186"}]}',
    'scalar' => '"PEA-186"',
    'too deep' => str_repeat('[', 20).str_repeat(']', 20),
]);

it('fails an empty list as EMPTY_FEED', function () {
    jsonRows(jsonTempFile($this, '{"products":[]}'));
})->throws(FeedParseException::class, 'EMPTY_FEED');

it('refuses files above the JSON size cap before decoding', function () {
    $path = jsonTempFile($this, '['.str_repeat('{"sku":"PEA-186"},', 100).'{"sku":"x"}]');

    try {
        jsonRows($path, new ParseOptions(FeedFormat::Json, maxJsonBytes: 1024));
        $this->fail('Expected a parse failure.');
    } catch (FeedParseException $exception) {
        expect($exception->errorCode)->toBe(FeedErrorCode::PayloadTooLarge);
    }
});

it('enforces the row limit', function () {
    jsonRows(jsonTempFile($this, '[{"a":1},{"a":2},{"a":3}]'), new ParseOptions(FeedFormat::Json, maxRows: 2));
})->throws(FeedParseException::class, 'ROW_LIMIT_EXCEEDED');

it('yields an empty row for a non-object item so the mapper can reject it', function () {
    $rows = jsonRows(jsonTempFile($this, '[{"sku":"PEA-186"}, 42]'));

    expect($rows[1]->fields)->toBe([])
        ->and($rows[1]->lineNumber)->toBe(2);
});
