<?php

namespace App\Domain\Search\Documents;

use App\Domain\Search\Contracts\SearchDocument;
use App\Domain\Search\Contracts\SearchIndex;

/**
 * A builder's answer for a batch of ids: documents to upsert (in id order)
 * and ids to delete (missing, merged, retired or otherwise not listed).
 *
 * @template-covariant TDocument of IndexDocument
 */
final readonly class BuiltDocuments
{
    /**
     * @param  list<TDocument>  $documents
     * @param  list<string>  $deletedIds
     */
    public function __construct(
        public SearchIndex $index,
        public array $documents,
        public array $deletedIds,
    ) {}

    /**
     * @return list<SearchDocument>
     */
    public function searchDocuments(): array
    {
        return array_map(static fn (IndexDocument $document): SearchDocument => $document->toSearchDocument(), $this->documents);
    }
}
