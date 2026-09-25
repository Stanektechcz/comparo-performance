<?php

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Domain\Feeds\Normalisation\FeedRowMapper;
use App\Domain\Feeds\Normalisation\NormalisationContext;
use App\Domain\Feeds\Normalisation\NormalisedFeedItem;
use App\Domain\Feeds\Parsing\CsvFeedParser;
use App\Domain\Feeds\Parsing\ParseOptions;
use App\Domain\Feeds\Parsing\RawFeedRow;
use App\Domain\Feeds\Parsing\XmlFeedParser;
use App\Domain\Feeds\Validation\DuplicateSkuTracker;
use App\Domain\Feeds\Validation\FeedIssue;
use App\Domain\Feeds\Validation\RowRejection;
use App\Domain\Offers\Availability;

const FEED_MINOR_UNITS = ['EUR' => 2, 'CZK' => 2, 'PLN' => 2, 'HUF' => 2, 'JPY' => 0];

function feedContext(array $overrides = []): NormalisationContext
{
    return new NormalisationContext(
        minorUnits: FEED_MINOR_UNITS,
        defaultCurrency: $overrides['defaultCurrency'] ?? null,
        availabilityMap: $overrides['availabilityMap'] ?? [],
        merchantDomain: array_key_exists('merchantDomain', $overrides) ? $overrides['merchantDomain'] : 'peaksupps.de',
        maxDiscountRatio: $overrides['maxDiscountRatio'] ?? 0.9,
    );
}

/**
 * A valid row (Whey Isolate 90 by IRONFORGE, a real seed product) with overrides.
 *
 * @param  array<string, string|null>  $overrides  null removes the field
 */
function feedRow(array $overrides = [], int $line = 2): RawFeedRow
{
    $fields = array_merge([
        'merchant_sku' => 'PEA-186',
        'title' => 'Whey Isolate 90 - Vanilla 900g',
        'brand' => 'IRONFORGE',
        'ean' => '4006381333931',
        'price' => '40.54',
        'old_price' => '54.80',
        'currency' => 'EUR',
        'availability' => 'in_stock',
        'stock' => '172',
        'url' => 'https://peaksupps.de/p/whey-isolate-90',
        'image_url' => 'https://peaksupps.de/img/whey-isolate-90.jpg',
        'pack_size' => '900 g',
        'variant' => 'Vanilla',
        'category' => 'Protein | Isolate',
        'shipping' => '3.40',
        'updated_at' => '2026-09-24T18:30:00+02:00',
    ], $overrides);

    return new RawFeedRow($line, array_filter($fields, fn ($value) => $value !== null));
}

function feedMapping(): FieldMapping
{
    return FieldMapping::suggest([
        'merchant_sku', 'title', 'brand', 'ean', 'price', 'old_price', 'currency', 'availability', 'stock', 'url',
        'image_url', 'pack_size', 'variant', 'category', 'shipping', 'updated_at', 'external_id',
    ]);
}

function mapFeedRow(RawFeedRow $row, ?NormalisationContext $context = null): NormalisedFeedItem|RowRejection
{
    return (new FeedRowMapper)->map($row, feedMapping(), $context ?? feedContext());
}

/**
 * @param  list<FeedIssue>  $issues
 * @return list<string>
 */
function issueCodes(array $issues): array
{
    return array_map(fn (FeedIssue $issue) => $issue->code->value, $issues);
}

it('normalises a valid row into the canonical item', function () {
    $item = mapFeedRow(feedRow(['title' => '<b>Whey Isolate 90</b>&nbsp;-  Vanilla&amp;Cream   900g']));

    expect($item)->toBeInstanceOf(NormalisedFeedItem::class)
        ->and($item->lineNumber)->toBe(2)
        ->and($item->merchantSku)->toBe('PEA-186')
        ->and($item->title)->toBe('Whey Isolate 90 - Vanilla&Cream 900g')
        ->and($item->price->minor)->toBe(4054)
        ->and($item->price->currency)->toBe('EUR')
        ->and($item->currency)->toBe('EUR')
        ->and($item->referencePrice?->minor)->toBe(5480)
        ->and($item->availability)->toBe(Availability::InStock)
        ->and($item->stockQuantity)->toBe(172)
        ->and($item->gtin)->toBe('4006381333931')
        ->and($item->gtinValid)->toBeTrue()
        ->and($item->brandRaw)->toBe('IRONFORGE')
        ->and($item->packRaw)->toBe('900 g')
        ->and($item->variantRaw)->toBe('Vanilla')
        ->and($item->categoryRaw)->toBe('Protein | Isolate')
        ->and($item->shippingHint?->minor)->toBe(340)
        ->and($item->sourceUpdatedAtIso())->toBe('2026-09-24T16:30:00+00:00')
        ->and($item->warnings)->toBe([])
        ->and($item->contentHash)->toMatch('/^[0-9a-f]{64}$/');
});

it('parses merchant money formats into minor units', function (string $price, string $currency, int $minor) {
    $item = mapFeedRow(feedRow(['price' => $price, 'currency' => $currency, 'old_price' => null]));

    expect($item)->toBeInstanceOf(NormalisedFeedItem::class)
        ->and($item->price->minor)->toBe($minor)
        ->and($item->price->currency)->toBe($currency);
})->with([
    ['43,50', 'EUR', 4350], ['43.5', 'EUR', 4350], ['1 234,56', 'CZK', 123456], ['1,234.56', 'EUR', 123456],
    ['1.234,56', 'EUR', 123456], ['€ 43,50', 'EUR', 4350], ['43,50 EUR', 'EUR', 4350], ["1\u{00A0}049,00", 'CZK', 104900],
    ['12990', 'JPY', 12990],
]);

it('takes the currency from the price text or the source default', function () {
    $fromPrice = mapFeedRow(feedRow(['currency' => null, 'price' => '1 049,00 CZK', 'old_price' => null]));
    $fromDefault = mapFeedRow(feedRow(['currency' => null, 'old_price' => null]), feedContext(['defaultCurrency' => 'PLN']));

    expect($fromPrice->price->currency)->toBe('CZK')
        ->and($fromDefault->price->currency)->toBe('PLN');
});

it('drops a reference price that is not above the price or not parseable, without an error', function (?string $reference) {
    $item = mapFeedRow(feedRow(['old_price' => $reference]));

    expect($item)->toBeInstanceOf(NormalisedFeedItem::class)
        ->and($item->referencePrice)->toBeNull();
})->with(['40.54', '39.00', 'n/a', '0', null]);

it('rejects each row-level error with its code', function (array $overrides, string $code, ?string $field, array $params = []) {
    $result = mapFeedRow(feedRow($overrides));

    expect($result)->toBeInstanceOf(RowRejection::class)
        ->and($result->codes())->toContain($code);

    $issue = collect($result->issues)->first(fn (FeedIssue $issue) => $issue->code->value === $code);

    expect($issue->field?->value)->toBe($field)
        ->and($issue->params)->toMatchArray($params)
        ->and(array_keys($issue->params))->toEqualCanonicalizing($issue->code->messageParams());
})->with([
    'MISSING_SKU' => [['merchant_sku' => '  '], 'MISSING_SKU', 'merchant_sku'],
    'MISSING_REQUIRED_FIELD title' => [['title' => '<p> </p>'], 'MISSING_REQUIRED_FIELD', 'title', ['field' => 'title']],
    'MISSING_REQUIRED_FIELD price' => [['price' => null], 'MISSING_REQUIRED_FIELD', 'price', ['field' => 'price']],
    'MISSING_REQUIRED_FIELD currency' => [['currency' => null], 'MISSING_REQUIRED_FIELD', 'currency', ['field' => 'currency']],
    'MISSING_REQUIRED_FIELD availability' => [['availability' => ''], 'MISSING_REQUIRED_FIELD', 'availability', ['field' => 'availability']],
    'MISSING_REQUIRED_FIELD product_url' => [['url' => null], 'MISSING_REQUIRED_FIELD', 'product_url', ['field' => 'product_url']],
    'INVALID_PRICE text' => [['price' => 'call us'], 'INVALID_PRICE', 'price', ['field' => 'price', 'value' => 'call us']],
    'INVALID_PRICE zero' => [['price' => '0,00'], 'INVALID_PRICE', 'price', ['value' => '0,00']],
    'INVALID_PRICE negative' => [['price' => '-40.54'], 'INVALID_PRICE', 'price'],
    'INVALID_PRICE too many decimals' => [['price' => '40.545'], 'INVALID_PRICE', 'price'],
    'INVALID_PRICE decimals on JPY' => [['price' => '4054.50', 'currency' => 'JPY'], 'INVALID_PRICE', 'price'],
    'INVALID_CURRENCY unknown' => [['currency' => 'XYZ'], 'INVALID_CURRENCY', 'currency', ['value' => 'XYZ']],
    'INVALID_CURRENCY conflicting price code' => [['price' => '40.54 CZK'], 'INVALID_CURRENCY', 'price', ['value' => 'CZK']],
    'INVALID_AVAILABILITY' => [['availability' => 'maybe tomorrow'], 'INVALID_AVAILABILITY', 'availability', ['value' => 'maybe tomorrow']],
    'INVALID_URL scheme' => [['url' => 'ftp://peaksupps.de/p/whey'], 'INVALID_URL', 'product_url', ['field' => 'product_url']],
    'INVALID_URL relative' => [['url' => '/p/whey-isolate-90'], 'INVALID_URL', 'product_url'],
    'INVALID_URL javascript' => [['url' => 'javascript:alert(1)'], 'INVALID_URL', 'product_url'],
    'INVALID_URL too long' => [['url' => 'https://peaksupps.de/'.str_repeat('a', 2048)], 'INVALID_URL', 'product_url'],
    'INVALID_URL userinfo' => [['url' => 'https://user:pw@peaksupps.de/p/whey'], 'INVALID_URL', 'product_url'],
    'FIELD_TOO_LONG sku' => [['merchant_sku' => str_repeat('S', 129)], 'FIELD_TOO_LONG', 'merchant_sku', ['field' => 'merchant_sku', 'max' => 128]],
    'FIELD_TOO_LONG brand' => [['brand' => str_repeat('B', 256)], 'FIELD_TOO_LONG', 'brand', ['field' => 'brand', 'max' => 255]],
    'IMPOSSIBLE_DISCOUNT' => [['price' => '1.00', 'old_price' => '40.00'], 'IMPOSSIBLE_DISCOUNT', 'reference_price', ['percent' => 97]],
    'IMPOSSIBLE_DISCOUNT at exactly 90 %' => [['price' => '4.00', 'old_price' => '40.00'], 'IMPOSSIBLE_DISCOUNT', 'reference_price', ['percent' => 90]],
]);

it('keeps the row but records each row-level warning', function (array $overrides, string $code, array $params, Closure $assert) {
    $item = mapFeedRow(feedRow($overrides));

    expect($item)->toBeInstanceOf(NormalisedFeedItem::class)
        ->and(issueCodes($item->warnings))->toBe([$code])
        ->and($item->warnings[0]->params)->toBe($params);

    $assert($item);
})->with([
    'INVALID_GTIN check digit' => [['ean' => '5901234123458'], 'INVALID_GTIN', ['value' => '5901234123458'],
        fn (NormalisedFeedItem $item) => expect([$item->gtin, $item->gtinValid])->toBe(['5901234123458', false])],
    'INVALID_GTIN prototype 11-digit EAN kept for exact matching' => [['ean' => '85910475146'], 'INVALID_GTIN', ['value' => '85910475146'],
        fn (NormalisedFeedItem $item) => expect([$item->gtin, $item->gtinValid])->toBe(['85910475146', false])],
    'INVALID_GTIN 10 digits' => [['ean' => '8591047514'], 'INVALID_GTIN', ['value' => '8591047514'],
        fn (NormalisedFeedItem $item) => expect($item->gtin)->toBe('8591047514')],
    'INVALID_GTIN letters' => [['ean' => 'n/a'], 'INVALID_GTIN', ['value' => 'n/a'],
        fn (NormalisedFeedItem $item) => expect($item->gtin)->toBeNull()],
    'MISSING_GTIN' => [['ean' => null], 'MISSING_GTIN', [],
        fn (NormalisedFeedItem $item) => expect($item->gtin)->toBeNull()],
    'INVALID_STOCK' => [['stock' => 'lots'], 'INVALID_STOCK', ['value' => 'lots'],
        fn (NormalisedFeedItem $item) => expect($item->stockQuantity)->toBeNull()],
    'INVALID_STOCK negative' => [['stock' => '-3'], 'INVALID_STOCK', ['value' => '-3'],
        fn (NormalisedFeedItem $item) => expect($item->stockQuantity)->toBeNull()],
    'INVALID_IMAGE_URL' => [['image_url' => 'not a url'], 'INVALID_IMAGE_URL', [],
        fn (NormalisedFeedItem $item) => expect($item->imageUrl)->toBeNull()],
    'URL_DOMAIN_MISMATCH' => [['url' => 'https://other-shop.example/p/whey'], 'URL_DOMAIN_MISMATCH', ['host' => 'other-shop.example', 'domain' => 'peaksupps.de'],
        fn (NormalisedFeedItem $item) => expect($item->productUrl)->toBe('https://other-shop.example/p/whey')],
]);

it('accepts the merchant domain, its subdomains and www', function (string $url) {
    expect(mapFeedRow(feedRow(['url' => $url]))->warnings)->toBe([]);
})->with(['https://peaksupps.de/p/a', 'https://www.peaksupps.de/p/a', 'http://shop.peaksupps.de/p/a', 'https://PEAKSUPPS.DE/p/a']);

it('does not treat look-alike domains as the merchant domain', function () {
    expect(issueCodes(mapFeedRow(feedRow(['url' => 'https://evilpeaksupps.de/p/a']))->warnings))->toBe(['URL_DOMAIN_MISMATCH']);
});

it('reports every problem of a row, not only the first', function () {
    $result = mapFeedRow(feedRow(['merchant_sku' => '', 'price' => 'abc', 'availability' => 'maybe', 'url' => 'ftp://x']));

    expect($result->codes())->toBe(['MISSING_SKU', 'INVALID_PRICE', 'INVALID_AVAILABILITY', 'INVALID_URL'])
        ->and($result->merchantSku)->toBeNull()
        ->and($result->lineNumber)->toBe(2);
});

it('maps availability synonyms and merchant overrides', function (string $raw, Availability $expected) {
    $context = feedContext(['availabilityMap' => ['Expedice do 5 dnů' => 'preorder', 'Ask us' => Availability::OutOfStock]]);

    expect(mapFeedRow(feedRow(['availability' => $raw]), $context)->availability)->toBe($expected);
})->with([
    ['in_stock', Availability::InStock], ['In Stock', Availability::InStock], ['available', Availability::InStock],
    ['Skladem', Availability::InStock], ['na skladě', Availability::InStock], ['0', Availability::InStock],
    ['24h', Availability::InStock], ['3', Availability::InStock], ['https://schema.org/InStock', Availability::InStock],
    ['low_stock', Availability::LowStock], ['Limited availability', Availability::LowStock],
    ['preorder', Availability::Preorder], ['pre-order', Availability::Preorder], ['backorder', Availability::Preorder],
    ['na dotaz', Availability::Preorder], ['7', Availability::Preorder], ['2026-10-15', Availability::Preorder],
    ['out_of_stock', Availability::OutOfStock], ['Sold out', Availability::OutOfStock], ['vyprodáno', Availability::OutOfStock],
    ['expedice do 5 dnů', Availability::Preorder], ['ASK US', Availability::OutOfStock],
]);

it('truncates long titles to 255 characters', function () {
    $item = mapFeedRow(feedRow(['title' => 'Whey Isolate 90 '.str_repeat('é', 400)]));

    expect(mb_strlen($item->title))->toBeLessThanOrEqual(FeedRowMapper::MAX_TITLE_LENGTH);
});

it('parses updated_at only from fixed formats and never from relative ones', function (?string $raw, ?string $expected) {
    expect(mapFeedRow(feedRow(['updated_at' => $raw]))->sourceUpdatedAtIso())->toBe($expected);
})->with([
    ['2026-09-24', '2026-09-24T00:00:00+00:00'],
    ['2026-09-24 18:30:00', '2026-09-24T18:30:00+00:00'],
    ['2026-09-24T18:30:00Z', '2026-09-24T18:30:00+00:00'],
    ['24.09.2026 18:30', '2026-09-24T18:30:00+00:00'],
    ['2026-02-31', null],
    ['yesterday', null],
    ['now', null],
    [null, null],
]);

it('hashes content deterministically and ignores line numbers, warnings and timestamps', function () {
    $a = mapFeedRow(feedRow([], 2));
    $b = mapFeedRow(feedRow(['updated_at' => '2026-09-25', 'stock' => '172'], 9));
    $c = mapFeedRow(feedRow(['price' => '40.55']));

    expect($a->contentHash)->toBe($b->contentHash)
        ->and($a->contentHash)->not->toBe($c->contentHash);
});

it('lets the first occurrence of a SKU win', function () {
    $tracker = new DuplicateSkuTracker;
    $rows = [...(new CsvFeedParser)->rows(dirname(__DIR__, 3).'/Fixtures/Feeds/duplicate-sku.csv', new ParseOptions(FeedFormat::Csv))];
    $results = array_map(fn (RawFeedRow $row) => $tracker->track(mapFeedRow($row)), $rows);

    expect($results[0])->toBeInstanceOf(NormalisedFeedItem::class)
        ->and($results[0]->price->minor)->toBe(4054)
        ->and($results[1])->toBeInstanceOf(NormalisedFeedItem::class)
        ->and($results[2])->toBeInstanceOf(RowRejection::class)
        ->and($results[2]->lineNumber)->toBe(4)
        ->and($results[2]->issues[0]->code)->toBe(FeedErrorCode::DuplicateSku)
        ->and($results[2]->issues[0]->field)->toBe(FeedField::MerchantSku)
        ->and($results[2]->issues[0]->params)->toBe(['sku' => 'PEA-186', 'first_line' => 2])
        ->and($tracker->count())->toBe(2);
});

it('maps the invalid-rows fixture line by line', function () {
    $rows = [...(new CsvFeedParser)->rows(dirname(__DIR__, 3).'/Fixtures/Feeds/invalid-rows.csv', new ParseOptions(FeedFormat::Csv))];
    $mapping = FieldMapping::suggest(array_keys($rows[0]->fields));
    $outcomes = [];

    foreach ($rows as $row) {
        $result = (new FeedRowMapper)->map($row, $mapping, feedContext());
        $outcomes[$row->lineNumber] = $result instanceof RowRejection ? $result->codes() : issueCodes($result->warnings);
    }

    expect($outcomes)->toBe([
        2 => [],
        3 => ['MISSING_SKU'],
        4 => ['MISSING_REQUIRED_FIELD'],
        5 => ['INVALID_PRICE'],
        6 => ['INVALID_CURRENCY'],
        7 => ['INVALID_AVAILABILITY'],
        8 => ['INVALID_URL'],
        9 => ['FIELD_TOO_LONG'],
        10 => ['IMPOSSIBLE_DISCOUNT'],
        11 => ['URL_DOMAIN_MISMATCH', 'INVALID_GTIN', 'INVALID_STOCK', 'INVALID_IMAGE_URL'],
    ]);
});

it('maps the Heureka fixture with a default currency and domain', function () {
    $rows = [...(new XmlFeedParser)->rows(dirname(__DIR__, 3).'/Fixtures/Feeds/heureka-shopitem.xml', new ParseOptions(FeedFormat::Xml))];
    $mapping = FieldMapping::suggest(array_keys($rows[0]->fields));
    $context = feedContext(['defaultCurrency' => 'EUR']);
    $items = array_map(fn (RawFeedRow $row) => (new FeedRowMapper)->map($row, $mapping, $context), $rows);

    expect($items)->each->toBeInstanceOf(NormalisedFeedItem::class)
        ->and($items[0]->price->minor)->toBe(4054)
        ->and($items[0]->brandRaw)->toBe('IRONFORGE')
        ->and($items[0]->availability)->toBe(Availability::InStock)
        ->and(issueCodes($items[0]->warnings))->toBe(['INVALID_GTIN'])
        ->and($items[1]->price->minor)->toBe(7490)
        ->and($items[1]->title)->toBe('Whey Isolate 90 – Chocolate 1.8 kg & shaker')
        ->and($items[1]->availability)->toBe(Availability::Preorder)
        ->and($items[1]->gtinValid)->toBeTrue()
        ->and($items[2]->brandRaw)->toBe('Titan Range')
        ->and($items[2]->availability)->toBe(Availability::Preorder);
});

it('refuses an invalid context', function (Closure $build) {
    $build();
})->with([
    'unknown default currency' => fn () => new NormalisationContext(FEED_MINOR_UNITS, 'XYZ'),
    'bad minor unit' => fn () => new NormalisationContext(['EUR' => 9]),
    'ratio out of range' => fn () => new NormalisationContext(FEED_MINOR_UNITS, maxDiscountRatio: 1.0),
    'unknown availability override' => fn () => new NormalisationContext(FEED_MINOR_UNITS, availabilityMap: ['x' => 'soon']),
])->throws(InvalidArgumentException::class);
