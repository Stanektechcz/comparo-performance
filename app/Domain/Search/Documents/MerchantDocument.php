<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Local\EntryAttributes;
use App\Domain\Search\Local\SearchableEntry;
use InvalidArgumentException;

/**
 * The `merchants` index document ("shop" results): public name, slug,
 * website host, a public trust summary (verified, public rating) and the
 * active markets the merchant ships to (A-28). Never risk events, fraud or
 * commercial data.
 */
final readonly class MerchantDocument implements IndexDocument
{
    /**
     * @param  list<string>  $shippingMarkets  active market codes with a shipping zone, sorted
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $websiteHost,
        public bool $verified,
        public ?float $ratingAverage,
        public int $ratingCount,
        public array $shippingMarkets,
        public string $indexedAt,
    ) {
        foreach ($shippingMarkets as $market) {
            if (preg_match('/^[A-Z]{2}$/', $market) !== 1) {
                throw new InvalidArgumentException("Invalid market code [{$market}].");
            }
        }
    }

    public static function index(): SearchIndex
    {
        return SearchIndex::Merchants;
    }

    public static function fromArray(array $payload): static
    {
        $rating = Payload::map($payload, 'rating');

        return new self(
            id: (int) $payload['id'],
            slug: (string) $payload['slug'],
            name: (string) $payload['name'],
            websiteHost: Payload::optionalString($payload, 'website_host'),
            verified: (bool) ($payload['verified'] ?? false),
            ratingAverage: Payload::optionalFloat($rating, 'average'),
            ratingCount: (int) ($rating['count'] ?? 0),
            shippingMarkets: Payload::strings($payload, 'shipping_markets'),
            indexedAt: (string) $payload['indexed_at'],
        );
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'merchant',
            'slug' => $this->slug,
            'name' => $this->name,
            'website_host' => $this->websiteHost,
            'verified' => $this->verified,
            'rating' => ['average' => $this->ratingAverage, 'count' => $this->ratingCount],
            'shipping_markets' => $this->shippingMarkets,
            'indexed_at' => $this->indexedAt,
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }

    public function shipsTo(string $market): bool
    {
        return in_array($market, $this->shippingMarkets, true);
    }

    public function toSearchableEntry(): SearchableEntry
    {
        return SearchableEntry::shop($this->id, $this->name, $this->websiteHost, new EntryAttributes(
            ratingAverage: $this->ratingAverage,
            ratingCount: $this->ratingCount,
        ));
    }

    public function searchableText(): string
    {
        return Payload::searchableText([$this->name, $this->websiteHost]);
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->id(), self::index()->entityType(), $this->toArray(), $this->searchableText(), self::SCHEMA_VERSION);
    }
}
