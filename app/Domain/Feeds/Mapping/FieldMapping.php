<?php

namespace App\Domain\Feeds\Mapping;

use InvalidArgumentException;

/**
 * Immutable map of canonical feed field => source key (CSV header, XML child
 * element local name or JSON key). Stored as `feed_mappings.field_map`.
 */
final readonly class FieldMapping
{
    /**
     * Synonyms per canonical field in priority order, compared "squashed"
     * (lower case, letters and digits only, namespace prefix removed).
     *
     * @var array<string, list<string>>
     */
    private const array SYNONYMS = [
        'merchant_sku' => ['merchantsku', 'sku', 'itemid', 'productno', 'productcode', 'productid', 'kod', 'kodproduktu', 'code', 'id'],
        'external_id' => ['externalid', 'externalref', 'guid', 'uuid'],
        'gtin' => ['gtin', 'ean', 'ean13', 'gtin13', 'gtin14', 'gtin12', 'gtin8', 'upc', 'barcode', 'eancode', 'carovykod'],
        'title' => ['title', 'productname', 'product', 'name', 'itemname', 'nazev', 'nazevproduktu'],
        'brand' => ['brand', 'manufacturer', 'vyrobce', 'znacka', 'producer', 'marke', 'hersteller'],
        'category' => ['category', 'categorytext', 'producttype', 'categoryname', 'kategorie', 'googleproductcategory'],
        'variant' => ['variant', 'variantname', 'flavour', 'flavor', 'prichut', 'varianta', 'geschmack'],
        'pack_size' => ['packsize', 'pack', 'packagesize', 'size', 'weight', 'baleni', 'velikostbaleni', 'hmotnost', 'gramaz'],
        'price' => ['pricevat', 'price', 'cena', 'cenasdph', 'currentprice', 'preis'],
        'reference_price' => ['referenceprice', 'oldprice', 'originalprice', 'regularprice', 'listprice', 'rrp', 'msrp', 'puvodnicena', 'beznacena'],
        'currency' => ['currency', 'currencycode', 'mena', 'wahrung'],
        'stock' => ['stock', 'stockquantity', 'quantity', 'qty', 'inventory', 'pocetkusu', 'mnozstvi'],
        'availability' => ['availability', 'deliverydate', 'stockstatus', 'availabilitystatus', 'dostupnost', 'verfugbarkeit'],
        'product_url' => ['producturl', 'url', 'link', 'productlink', 'deeplink', 'odkaz'],
        'image_url' => ['imageurl', 'imgurl', 'imagelink', 'image', 'img', 'picture', 'obrazek'],
        'shipping_hint' => ['shippinghint', 'shipping', 'shippingcost', 'shippingprice', 'deliveryprice', 'doprava', 'postovne'],
        'updated_at' => ['updatedat', 'lastupdated', 'updated', 'lastmodified', 'modified', 'datemodified', 'lastmod'],
    ];

    /**
     * @param  array<string, string>  $sources  canonical field value => source key
     */
    private function __construct(private array $sources) {}

    /**
     * Suggest a mapping for the given source keys. Each source key is used for
     * at most one field; fields claim keys in their synonym priority order.
     *
     * @param  list<string>  $headers
     */
    public static function suggest(array $headers): self
    {
        $bySquashed = [];

        foreach ($headers as $header) {
            $bySquashed[self::squash($header)] ??= $header;
        }

        $sources = [];
        $claimed = [];

        foreach (FeedField::cases() as $field) {
            foreach (self::SYNONYMS[$field->value] as $synonym) {
                $header = $bySquashed[$synonym] ?? null;

                if ($header !== null && ! isset($claimed[$header])) {
                    $sources[$field->value] = $header;
                    $claimed[$header] = true;

                    break;
                }
            }
        }

        return new self($sources);
    }

    /**
     * @param  array<array-key, mixed>  $map  canonical field => source key
     *
     * @throws InvalidArgumentException for unknown fields or blank source keys
     */
    public static function fromArray(array $map): self
    {
        $sources = [];

        foreach ($map as $field => $source) {
            if (FeedField::tryFrom((string) $field) === null) {
                throw new InvalidArgumentException("Unknown canonical feed field [{$field}].");
            }

            if (! is_string($source) || trim($source) === '') {
                throw new InvalidArgumentException("The source key for [{$field}] must be a non-empty string.");
            }

            $sources[(string) $field] = $source;
        }

        return new self($sources);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $ordered = [];

        foreach (FeedField::cases() as $field) {
            if (isset($this->sources[$field->value])) {
                $ordered[$field->value] = $this->sources[$field->value];
            }
        }

        return $ordered;
    }

    public function sourceFor(FeedField $field): ?string
    {
        return $this->sources[$field->value] ?? null;
    }

    public function with(FeedField $field, ?string $source): self
    {
        $sources = $this->sources;
        unset($sources[$field->value]);

        if ($source !== null && trim($source) !== '') {
            $sources[$field->value] = $source;
        }

        return new self($sources);
    }

    /**
     * Required canonical fields without a source key.
     *
     * @return list<FeedField>
     */
    public function missingRequired(bool $hasDefaultCurrency = false): array
    {
        return array_values(array_filter(
            FeedField::cases(),
            fn (FeedField $field): bool => $field->isRequired()
                && ! isset($this->sources[$field->value])
                && ! ($field === FeedField::Currency && $hasDefaultCurrency),
        ));
    }

    private static function squash(string $header): string
    {
        $local = str_contains($header, ':') ? substr($header, strrpos($header, ':') + 1) : $header;
        $ascii = strtr(mb_strtolower(trim($local)), ['á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
            'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);

        return preg_replace('/[^a-z0-9]+/', '', $ascii) ?? '';
    }
}
