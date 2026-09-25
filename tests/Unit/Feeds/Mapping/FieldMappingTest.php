<?php

use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Mapping\FieldMapping;

it('suggests the Heureka SHOPITEM mapping', function () {
    $mapping = FieldMapping::suggest([
        'ITEM_ID', 'PRODUCTNAME', 'PRODUCT', 'DESCRIPTION', 'URL', 'IMGURL', 'PRICE_VAT', 'MANUFACTURER',
        'CATEGORYTEXT', 'EAN', 'PRODUCTNO', 'DELIVERY_DATE',
    ]);

    expect($mapping->toArray())->toBe([
        'merchant_sku' => 'ITEM_ID',
        'gtin' => 'EAN',
        'title' => 'PRODUCTNAME',
        'brand' => 'MANUFACTURER',
        'category' => 'CATEGORYTEXT',
        'price' => 'PRICE_VAT',
        'availability' => 'DELIVERY_DATE',
        'product_url' => 'URL',
        'image_url' => 'IMGURL',
    ])->and($mapping->missingRequired(hasDefaultCurrency: true))->toBe([])
        ->and($mapping->missingRequired())->toBe([FeedField::Currency]);
});

it('falls back to PRODUCTNO and PRODUCT when ITEM_ID and PRODUCTNAME are absent', function () {
    $mapping = FieldMapping::suggest(['PRODUCTNO', 'PRODUCT']);

    expect($mapping->sourceFor(FeedField::MerchantSku))->toBe('PRODUCTNO')
        ->and($mapping->sourceFor(FeedField::Title))->toBe('PRODUCT');
});

it('suggests English, Google and Czech column names', function (array $headers, array $expected) {
    $mapping = FieldMapping::suggest($headers);

    foreach ($expected as $field => $source) {
        expect($mapping->sourceFor(FeedField::from($field)))->toBe($source);
    }
})->with([
    'spec CSV' => [
        ['merchant_sku', 'product_name', 'brand', 'ean', 'price', 'currency', 'availability', 'stock', 'url'],
        ['merchant_sku' => 'merchant_sku', 'title' => 'product_name', 'gtin' => 'ean', 'stock' => 'stock', 'product_url' => 'url'],
    ],
    'Google' => [
        ['g:id', 'title', 'g:price', 'g:sale_price', 'g:link', 'g:image_link', 'g:gtin', 'g:brand', 'g:availability'],
        ['merchant_sku' => 'g:id', 'price' => 'g:price', 'product_url' => 'g:link', 'image_url' => 'g:image_link', 'gtin' => 'g:gtin'],
    ],
    'Czech' => [
        ['kód', 'název', 'výrobce', 'ean', 'cena', 'měna', 'dostupnost', 'odkaz', 'původní cena'],
        ['merchant_sku' => 'kód', 'title' => 'název', 'brand' => 'výrobce', 'price' => 'cena', 'currency' => 'měna', 'availability' => 'dostupnost', 'product_url' => 'odkaz', 'reference_price' => 'původní cena'],
    ],
    'English extras' => [
        ['SKU', 'Name', 'Old Price', 'Pack Size', 'Flavour', 'Shipping Cost', 'Last Updated', 'Stock Status'],
        ['merchant_sku' => 'SKU', 'title' => 'Name', 'reference_price' => 'Old Price', 'pack_size' => 'Pack Size', 'variant' => 'Flavour', 'shipping_hint' => 'Shipping Cost', 'updated_at' => 'Last Updated', 'availability' => 'Stock Status'],
    ],
]);

it('lists required fields without a source', function () {
    expect(FieldMapping::suggest(['title', 'foo'])->missingRequired())->toBe([
        FeedField::MerchantSku, FeedField::Price, FeedField::Currency, FeedField::Availability, FeedField::ProductUrl,
    ]);
});

it('round-trips through arrays and stays immutable', function () {
    $mapping = FieldMapping::fromArray(['title' => 'PRODUCTNAME', 'merchant_sku' => 'ITEM_ID']);
    $changed = $mapping->with(FeedField::Price, 'PRICE_VAT')->with(FeedField::Title, null);

    expect($mapping->toArray())->toBe(['merchant_sku' => 'ITEM_ID', 'title' => 'PRODUCTNAME'])
        ->and($changed->toArray())->toBe(['merchant_sku' => 'ITEM_ID', 'price' => 'PRICE_VAT'])
        ->and(FieldMapping::fromArray($changed->toArray())->toArray())->toBe($changed->toArray());
});

it('rejects unknown fields and blank sources', function (array $map) {
    FieldMapping::fromArray($map);
})->with([
    'unknown field' => [['colour' => 'COLOR']],
    'blank source' => [['title' => ' ']],
    'non-string source' => [['title' => 5]],
])->throws(InvalidArgumentException::class);
