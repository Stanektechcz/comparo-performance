<?php

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\Parsing\FeedParseException;
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
function xmlRows(string $path, ?ParseOptions $options = null): array
{
    return [...(new XmlFeedParser)->rows($path, $options ?? new ParseOptions(FeedFormat::Xml))];
}

function xmlTempFile(object $test, string $xml): string
{
    $path = tempnam(sys_get_temp_dir(), 'feed');
    file_put_contents($path, $xml);
    $test->temp[] = $path;

    return $path;
}

function xmlFailure(callable $parse): FeedParseException
{
    try {
        $parse();
    } catch (FeedParseException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a FeedParseException.');
}

it('reads Heureka SHOPITEM records with CDATA, nested PARAMs and line numbers', function () {
    $rows = xmlRows($this->fixtures.'heureka-shopitem.xml');

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->lineNumber)->toBe(3)
        ->and($rows[0]->get('ITEM_ID'))->toBe('PEA-186')
        ->and($rows[0]->get('PRICE_VAT'))->toBe('40.54')
        ->and($rows[0]->get('CATEGORYTEXT'))->toBe('Sport | Výživa | Proteiny')
        ->and($rows[0]->get('DESCRIPTION'))->toContain('<b>lactose free</b>')
        ->and($rows[0]->get('PARAM.VAL'))->toBe('Vanilla')
        ->and($rows[1]->get('PRODUCTNAME'))->toBe('Whey Isolate 90 – Chocolate 1.8 kg & shaker')
        ->and($rows[2]->get('DELIVERY_DATE'))->toBe('2026-10-15');
});

it('refuses an external entity (XXE) before it is resolved', function () {
    $exception = xmlFailure(fn () => xmlRows($this->fixtures.'xxe.xml'));

    expect($exception->errorCode)->toBe(FeedErrorCode::ParserError)
        ->and($exception->params)->toBe(['format' => 'XML'])
        ->and($exception->getMessage())->not->toContain('passwd');
});

it('refuses an entity-expansion bomb (billion laughs) quickly', function () {
    $started = hrtime(true);
    $exception = xmlFailure(fn () => xmlRows($this->fixtures.'billion-laughs.xml'));

    expect($exception->errorCode)->toBe(FeedErrorCode::ParserError)
        ->and((hrtime(true) - $started) / 1e9)->toBeLessThan(2.0)
        ->and(memory_get_usage())->toBeLessThan(256 * 1024 * 1024);
});

it('refuses any DOCTYPE, even without entities', function () {
    $path = xmlTempFile($this, "<?xml version=\"1.0\"?>\n<!DOCTYPE SHOP SYSTEM \"http://example.com/shop.dtd\">\n<SHOP><SHOPITEM><ITEM_ID>A</ITEM_ID></SHOPITEM></SHOP>");

    expect(xmlFailure(fn () => xmlRows($path))->errorCode)->toBe(FeedErrorCode::ParserError);
});

it('reports malformed XML as PARSER_ERROR with the line', function () {
    $exception = xmlFailure(fn () => xmlRows($this->fixtures.'malformed.xml'));

    expect($exception->errorCode)->toBe(FeedErrorCode::ParserError)
        ->and($exception->lineNumber)->toBeGreaterThanOrEqual(5);
});

it('detects RSS/Google item records and keys namespaced children by local name', function () {
    $path = xmlTempFile($this, <<<'XML'
        <?xml version="1.0"?>
        <rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
          <channel>
            <title>PeakSupps</title>
            <item>
              <g:id>PEA-186</g:id>
              <title>Whey Isolate 90 Vanilla 900 g</title>
              <g:price>40.54 EUR</g:price>
              <g:availability>in stock</g:availability>
            </item>
          </channel>
        </rss>
        XML);

    $rows = xmlRows($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->fields)->toBe([
            'id' => 'PEA-186',
            'title' => 'Whey Isolate 90 Vanilla 900 g',
            'price' => '40.54 EUR',
            'availability' => 'in stock',
        ]);
});

it('uses a configured record element and exposes record attributes', function () {
    $path = xmlTempFile($this, '<catalog><offer sku="PEA-210"><name>Creatine Monohydrate Micronized</name></offer><offer sku="PEA-211"><name>Creatine HCl Caps</name></offer></catalog>');

    $rows = xmlRows($path, new ParseOptions(FeedFormat::Xml, recordElement: 'offer'));

    expect($rows)->toHaveCount(2)
        ->and($rows[1]->fields)->toBe(['@sku' => 'PEA-211', 'name' => 'Creatine HCl Caps']);
});

it('reads a Windows-1250 XML document declared in its prolog', function () {
    $path = xmlTempFile($this, iconv('UTF-8', 'Windows-1250', "<?xml version=\"1.0\" encoding=\"windows-1250\"?>\n<SHOP><SHOPITEM><PRODUCTNAME>Kreatin monohydrát mikronizovaný</PRODUCTNAME></SHOPITEM></SHOP>"));

    expect(xmlRows($path)[0]->get('PRODUCTNAME'))->toBe('Kreatin monohydrát mikronizovaný');
});

it('fails a document without records as EMPTY_FEED', function () {
    $path = xmlTempFile($this, '<?xml version="1.0"?><SHOP></SHOP>');

    expect(xmlFailure(fn () => xmlRows($path))->errorCode)->toBe(FeedErrorCode::EmptyFeed);
});

it('stops at the row limit', function () {
    $path = xmlTempFile($this, '<SHOP><SHOPITEM><A>1</A></SHOPITEM><SHOPITEM><A>2</A></SHOPITEM></SHOP>');

    expect(xmlFailure(fn () => xmlRows($path, new ParseOptions(FeedFormat::Xml, maxRows: 1)))->errorCode)
        ->toBe(FeedErrorCode::RowLimitExceeded);
});
