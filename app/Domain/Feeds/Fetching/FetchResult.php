<?php

namespace App\Domain\Feeds\Fetching;

/**
 * A feed payload stored on local disk. `bytes` and `sha256` describe the
 * stored (gzip-decoded) payload, which is what the parsers read and what the
 * unchanged-checksum shortcut compares.
 */
final readonly class FetchResult
{
    public function __construct(
        public string $path,
        public int $bytes,
        public string $sha256,
        public ?string $contentType,
        public int $status,
        public int $redirects,
        public bool $decompressed,
    ) {}
}
