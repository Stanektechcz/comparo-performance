<?php

/*
|--------------------------------------------------------------------------
| Merchant feed messages
|--------------------------------------------------------------------------
|
| One actionable message per App\Domain\Feeds\FeedErrorCode. Placeholders are
| exactly FeedErrorCode::messageParams(); :field is a canonical field key that
| can be rendered with the "fields" labels below. Never add resolved IP
| addresses, credentials or server internals to these messages.
|
*/

return [
    'errors' => [
        // Run-fatal
        'UNREACHABLE_URL' => 'We could not connect to the feed URL. Check that the address is spelled correctly and that the server is online and publicly reachable, then run the import again.',
        'HTTP_ERROR' => 'The feed server answered with HTTP status :status instead of the feed. Check that the URL points directly to the feed file and does not redirect more than 3 times, then run the import again.',
        'FETCH_TIMEOUT' => 'The feed server did not deliver the feed within :seconds seconds. Make sure the feed is generated in advance (not on request) or try again later.',
        'BLOCKED_DESTINATION' => 'This feed URL points to an address Comparo is not allowed to fetch. Only public http:// or https:// addresses on the standard ports (80 and 443), without a user name in the URL, can be used.',
        'AUTH_FAILED' => 'The feed server refused access. Check the user name, password, token or header in the feed credentials and save them again.',
        'PAYLOAD_TOO_LARGE' => 'The feed is larger than the :limit_mb MB limit. Split it into several feeds or remove unused fields, then run the import again.',
        'UNSUPPORTED_CONTENT_TYPE' => 'The feed URL returned ":content_type" content instead of an XML, CSV or JSON feed. Check that the URL points to the feed file itself and not to a web page.',
        'UNSUPPORTED_ENCODING' => 'The feed could not be read as :encoding text. Export it as UTF-8, or choose the encoding it was saved with (UTF-8, Windows-1250, Windows-1252, ISO-8859-1 or ISO-8859-2).',
        'PARSER_ERROR' => 'The feed is not valid :format and could not be read. Fix the file at the reported line (DOCTYPE and ENTITY declarations are not allowed in XML) and run the import again.',
        'EMPTY_FEED' => 'The feed contains no products. Check that the export is not empty and that the product element or header row is set correctly.',
        'ROW_LIMIT_EXCEEDED' => 'The feed has more than :limit products, which is the maximum per feed. Split it into several feeds.',
        'REJECT_THRESHOLD_EXCEEDED' => 'More than :percent% of rows failed validation, so nothing was published. Fix the rejected rows below and run the import again.',
        'STALLED' => 'The import stopped responding and was cancelled. Nothing was published; run the import again.',

        // Row-reject
        'MISSING_SKU' => 'This row has no merchant SKU, so it was skipped. Give every product a unique SKU in the mapped SKU column.',
        'MISSING_REQUIRED_FIELD' => 'The required field ":field" is empty or not mapped, so the row was skipped. Fill it in or map the right column.',
        'INVALID_PRICE' => 'The value ":value" in ":field" is not a valid positive price for this currency, so the row was skipped. Use a number such as 43.50 or 43,50 with no more decimals than the currency allows.',
        'INVALID_CURRENCY' => 'The currency ":value" is not supported, so the row was skipped. Use a three-letter ISO code such as EUR, CZK or PLN.',
        'INVALID_AVAILABILITY' => 'The availability ":value" is not recognised, so the row was skipped. Use in_stock, low_stock, preorder or out_of_stock, or add this value to the availability mapping of the feed.',
        'INVALID_URL' => 'The link in ":field" is not a valid http:// or https:// address (maximum 2048 characters), so the row was skipped.',
        'DUPLICATE_SKU' => 'The SKU ":sku" already appeared on line :first_line. Only the first occurrence was imported; give every product a unique SKU.',
        'SKU_OWNED_BY_OTHER_SOURCE' => 'The SKU ":sku" is already published by another of your feeds, so this row was skipped. Remove it from one of the feeds or use a different SKU.',
        'FIELD_TOO_LONG' => 'The value in ":field" is longer than :max characters, so the row was skipped. Shorten it in your export.',
        'IMPOSSIBLE_DISCOUNT' => 'The price is :percent% below the reference (old) price, which is not plausible, so the row was skipped. Check the price and the old price.',

        // Row-warning
        'INVALID_GTIN' => 'The GTIN/EAN ":value" has an invalid length or check digit. The product was imported, but matching is less reliable until the code is corrected.',
        'MISSING_GTIN' => 'This product has no GTIN/EAN. It was imported, but adding the barcode makes matching to the catalogue much more reliable.',
        'UNKNOWN_BRAND' => 'The brand ":brand" is not in the Comparo catalogue yet. The product was imported and will be reviewed before it can be matched by brand.',
        'INVALID_STOCK' => 'The stock quantity ":value" is not a whole number, so it was ignored. The product was imported with its availability only.',
        'INVALID_IMAGE_URL' => 'The image link is not a valid http:// or https:// address, so it was ignored. The product was imported without an image.',
        'URL_DOMAIN_MISMATCH' => 'The product link points to :host, which is not your shop domain :domain. The product was imported; please link to your own shop.',
    ],

    'fields' => [
        'external_id' => 'external ID',
        'merchant_sku' => 'merchant SKU',
        'gtin' => 'GTIN/EAN',
        'title' => 'product name',
        'brand' => 'brand',
        'category' => 'category',
        'variant' => 'variant',
        'pack_size' => 'pack size',
        'price' => 'price',
        'reference_price' => 'old price',
        'currency' => 'currency',
        'stock' => 'stock',
        'availability' => 'availability',
        'product_url' => 'product URL',
        'image_url' => 'image URL',
        'shipping_hint' => 'shipping cost',
        'updated_at' => 'last updated',
    ],
];
