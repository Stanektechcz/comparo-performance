<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Local\SearchableEntry;

/**
 * A typed search document. Instances come from the *DocumentBuilder classes
 * (the only factories reading the database) or from `fromArray()` of a
 * stored payload (the database engine). Payloads carry public fields only:
 * never commission, plan, campaign, sponsor, affiliate or internal risk data.
 */
interface IndexDocument
{
    public const int SCHEMA_VERSION = 1;

    public static function index(): SearchIndex;

    /**
     * @param  array<string, mixed>  $payload  a payload produced by toArray()
     */
    public static function fromArray(array $payload): static;

    public function id(): string;

    /**
     * The engine payload (JSON-safe).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * The entry the local (database) engine scores with prototype relevance.
     */
    public function toSearchableEntry(): SearchableEntry;

    /**
     * The folded texts this document is found by (database engine storage).
     */
    public function searchableText(): string;

    public function toSearchDocument(): SearchDocument;
}
