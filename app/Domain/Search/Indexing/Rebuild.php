<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\Contracts\SearchIndex;

/**
 * An in-progress full rebuild of one index ({@see RebuildMarker}): its id,
 * the logical `<index>_tmp` twin being filled and the watermark — when the
 * rebuild started; every entity the outbox indexes from then on is recorded
 * under the id.
 */
final readonly class Rebuild
{
    public function __construct(
        public SearchIndex $index,
        public string $id,
        public string $temporary,
        public string $watermark,
    ) {}
}
