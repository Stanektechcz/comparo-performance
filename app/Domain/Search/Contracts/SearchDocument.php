<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\SearchEntityType;
use InvalidArgumentException;

/**
 * One document as engines receive it: the public payload a document class
 * produced, its folded searchable text (database engine storage) and the
 * schema version. Created only by the document classes in
 * App\Domain\Search\Documents (via their builders).
 */
final readonly class SearchDocument
{
    /**
     * @param  array<string, mixed>  $payload  JSON-safe, public fields only
     */
    public function __construct(
        public string $id,
        public SearchEntityType $entityType,
        public array $payload,
        public string $searchableText,
        public int $schemaVersion,
    ) {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1) {
            throw new InvalidArgumentException('A search document id is 1–64 characters of [A-Za-z0-9_-].');
        }

        if (($payload['id'] ?? null) === null || (string) $payload['id'] !== $id) {
            throw new InvalidArgumentException("The payload id does not match document [{$id}].");
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Schema versions start at 1.');
        }
    }
}
