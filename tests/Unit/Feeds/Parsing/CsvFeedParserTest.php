<?php

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\Parsing\CsvFeedParser;
use App\Domain\Feeds\Parsing\FeedParseException;
use App\Domain\Feeds\Parsing\FeedParserFactory;
use App\Domain\Feeds\Parsing\JsonFeedParser;
use App\Domain\Feeds\Parsing\ParseOptions;
use App\Domain\Feeds\Parsing\RawFeedRow;
use App\Domain\Feeds\Parsing\XmlFeedParser;

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
function csvRows(string $path, ?ParseOptions $options = null): array
{
    return [...(new CsvFeedParser)->rows($path, $options ?? new ParseOptions(FeedFormat::Csv))];
}

function csvTempFile(object $test, string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'feed');
    file_put_contents($path, $bytes);
    $test->temp[] = $path;

    return $path;
}

it('reads a UTF-8 CSV with a BOM, quoted delimiters, doubled quotes and embedded newlines', function () {
    $rows = csvRows($this->fixtures.'comma-utf8-bom.csv');

    expect($rows)->toHaveCount(3)
        ->and(array_keys($rows[0]->fields))->toContain('merchant_sku')
        ->and($rows[0]->lineNumber)->toBe(2)
        ->and($rows[0]->get('product_name'))->toBe('Whey Isolate 90, Vanilla "900 g"')
        ->and($rows[1]->get('product_name'))->toBe("Native Whey Concentrate\nUnflavoured")
        ->and($rows[1]->get('price'))->toBe('1,032.90')
        // the quoted newline spans lines 3–4, so the next record starts on physical line 5
        ->and($rows[2]->lineNumber)->toBe(5)
        ->and($rows[2]->get('stock'))->toBe('')
        ->and($rows[2]->get('brand'))->toBe('Titan Range');
});

it('converts a Windows-1250 semicolon CSV to UTF-8 and detects the delimiter', function () {
    $rows = csvRows($this->fixtures.'semicolon-cp1250.csv', new ParseOptions(FeedFormat::Csv, 'Windows-1250'));

    expect($rows)->toHaveCount(2)
        ->and(array_keys($rows[0]->fields))->toBe(['kód', 'název', 'výrobce', 'ean', 'cena', 'měna', 'dostupnost', 'url'])
        ->and($rows[0]->get('název'))->toBe('Whey Isolate 90 – vanilka 900 g')
        ->and($rows[1]->get('název'))->toBe('Kreatin monohydrát mikronizovaný 1000 g')
        ->and($rows[1]->get('cena'))->toBe('699,90');
});

it('refuses Windows-1250 bytes read as UTF-8', function () {
    csvRows($this->fixtures.'semicolon-cp1250.csv');
})->throws(FeedParseException::class, 'UNSUPPORTED_ENCODING at line 1');

it('detects tab-separated files', function () {
    $rows = csvRows($this->fixtures.'tab.tsv');

    expect($rows)->toHaveCount(2)
        ->and($rows[1]->get('title'))->toBe('Native Whey Concentrate 1000 g')
        ->and($rows[1]->get('image_link'))->toBe('');
});

it('detects the delimiter among ; , TAB and |', function (string $delimiter) {
    $path = csvTempFile($this, "sku{$delimiter}title\nPEA-186{$delimiter}Whey Isolate 90\n");

    expect(csvRows($path)[0]->fields)->toBe(['sku' => 'PEA-186', 'title' => 'Whey Isolate 90']);
})->with([';', ',', "\t", '|']);

it('honours a configured delimiter and CRLF line endings', function () {
    $path = csvTempFile($this, "sku|title\r\nPEA-186|Whey Isolate 90, Vanilla\r\n\r\nPEA-210|Creatine\r\n");
    $rows = csvRows($path, new ParseOptions(FeedFormat::Csv, delimiter: '|'));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->get('title'))->toBe('Whey Isolate 90, Vanilla')
        ->and($rows[1]->lineNumber)->toBe(4);
});

it('skips lines of empty cells and fills short rows with empty strings', function () {
    $path = csvTempFile($this, "sku;title;brand\n;;\nPEA-186;Whey Isolate 90\n");
    $rows = csvRows($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->fields)->toBe(['sku' => 'PEA-186', 'title' => 'Whey Isolate 90', 'brand' => '']);
});

it('fails a header-only or empty file as EMPTY_FEED', function (string $contents) {
    csvRows(csvTempFile($this, $contents));
})->with(['header only' => "sku;title\n", 'empty' => ''])->throws(FeedParseException::class, 'EMPTY_FEED');

it('fails an unterminated quoted field as PARSER_ERROR with its line', function () {
    try {
        csvRows(csvTempFile($this, "sku,title\nPEA-186,\"Whey Isolate 90\nPEA-210,Creatine\n"));
        $this->fail('Expected a parse failure.');
    } catch (FeedParseException $exception) {
        expect($exception->errorCode)->toBe(FeedErrorCode::ParserError)
            ->and($exception->params)->toBe(['format' => 'CSV'])
            ->and($exception->lineNumber)->toBe(2);
    }
});

it('stops at the row limit with ROW_LIMIT_EXCEEDED', function () {
    $path = csvTempFile($this, "sku,title\nA,1\nB,2\nC,3\n");

    csvRows($path, new ParseOptions(FeedFormat::Csv, maxRows: 2));
})->throws(FeedParseException::class, 'ROW_LIMIT_EXCEEDED');

it('streams rows lazily', function () {
    $rows = (new CsvFeedParser)->rows($this->fixtures.'comma-utf8-bom.csv', new ParseOptions(FeedFormat::Csv));

    expect($rows)->toBeInstanceOf(Generator::class)
        ->and($rows->current()->get('merchant_sku'))->toBe('PEA-186');
});

it('rejects unsupported encodings when options are built', function (string $encoding) {
    new ParseOptions(FeedFormat::Csv, $encoding);
})->with(['UTF-16', 'Shift_JIS', 'KOI8-R', ''])->throws(FeedParseException::class, 'UNSUPPORTED_ENCODING');

it('accepts the supported encodings and their aliases', function (string $alias, string $canonical) {
    expect((new ParseOptions(FeedFormat::Csv, $alias))->encoding)->toBe($canonical);
})->with([
    ['utf-8', 'UTF-8'], ['UTF8', 'UTF-8'], ['cp1250', 'Windows-1250'], ['Windows-1252', 'Windows-1252'],
    ['iso-8859-1', 'ISO-8859-1'], ['LATIN2', 'ISO-8859-2'],
]);

it('builds the parser for each format', function () {
    expect(FeedParserFactory::for(FeedFormat::Csv))->toBeInstanceOf(CsvFeedParser::class)
        ->and(FeedParserFactory::for(FeedFormat::Xml))->toBeInstanceOf(XmlFeedParser::class)
        ->and(FeedParserFactory::for(FeedFormat::Json))->toBeInstanceOf(JsonFeedParser::class);
});
